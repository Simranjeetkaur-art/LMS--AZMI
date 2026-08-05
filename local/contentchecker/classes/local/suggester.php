<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_contentchecker\local;

use local_contentchecker\api\ai_backend;
use local_contentchecker\api\client_factory;
use local_contentchecker\image\source_registry;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads an activity and proposes illustrations for it.
 *
 * The order matters and is the whole point: the content is analysed FIRST, and
 * only then is anything searched for. Searching a registry by keyword without
 * reading the page returns whatever happens to share a word with the title;
 * asking the model what this passage is actually teaching produces search terms
 * that match the teaching point.
 *
 * Nothing is inserted here. Every proposal is a candidate an editor chooses
 * from, on the same principle as AI content suggestions.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suggester {

    /** @var int Concepts to ask for per activity. */
    const MAX_CONCEPTS = 6;

    /** @var int Stock images offered per concept. */
    const IMAGES_PER_CONCEPT = 6;

    /**
     * Minimum overlapping words before a registered asset is offered.
     *
     * One shared word is noise. Measured: a concept about "the four-part
     * structure of a medical term" matched "B-DNA dodecamer" and "COX-1 with
     * ibuprofen" purely on the word "structure", which is worse than offering
     * nothing because it implies the tool understood the page.
     *
     * @var int
     */
    const MIN_ASSET_SCORE = 2;

    /**
     * Words too common in a medical corpus to carry any signal.
     *
     * @var array
     */
    const STOPWORDS = ['structure', 'structures', 'medical', 'human', 'anatomy',
        'anatomical', 'system', 'systems', 'body', 'clinical', 'model', 'diagram',
        'terminology', 'term', 'terms', 'cell', 'cells', 'function', 'functions',
        'process', 'part', 'parts', 'analysis', 'overview', 'introduction'];

    /** @var ai_backend The AI backend. */
    protected $client;

    /**
     * Constructor.
     *
     * @param ai_backend|null $client Optional backend override.
     */
    public function __construct(?ai_backend $client = null) {
        $this->client = $client ?? client_factory::make();
    }

    /**
     * Schema for the analysis step.
     *
     * @return array JSON Schema.
     */
    public static function schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'concepts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'concept' => ['type' => 'string'],
                            'mediatype' => ['type' => 'string',
                                'enum' => ['model3d', 'diagram', 'image']],
                            'reason' => ['type' => 'string'],
                            'searchterms' => ['type' => 'string'],
                        ],
                        'required' => ['concept', 'mediatype', 'reason', 'searchterms'],
                    ],
                ],
            ],
            'required' => ['concepts'],
        ];
    }

    /**
     * Analyse an activity and gather candidate illustrations.
     *
     * @param int $cmid Course module id.
     * @param \context $context Context the request is made from.
     * @return array List of concept objects, each with its candidates.
     */
    public function suggest(int $cmid, \context $context): array {
        $items = content_source::for_cm($cmid);
        if (!$items) {
            return [];
        }

        // One pass over the whole activity rather than per block: an editor
        // wants a handful of good ideas for the page, not one per paragraph.
        $text = '';
        foreach ($items as $item) {
            $text .= "\n\n" . $item->text;
        }
        $text = \core_text::substr(trim($text), 0, 6000);

        $concepts = $this->analyse($text);

        foreach ($concepts as $concept) {
            $concept->assets = $this->matching_assets($concept);

            if ($concept->mediatype === 'diagram') {
                // A photo library indexes photographs of things that exist. It
                // has nothing for "the four-part structure of a medical term",
                // and searching anyway returned "Nikon D800 vs Canon 5D Mark III
                // Battery" because both phrases contain "comparison". The right
                // answer for an abstract relationship is to draw one.
                $concept->diagram = $this->generate_diagram($concept);
                $concept->images = [];
            } else {
                $concept->diagram = '';
                $concept->images = $this->matching_images($concept, $context);
            }
        }

        return $concepts;
    }

    /**
     * Ask the model what this activity is teaching and what would illustrate it.
     *
     * @param string $text The activity's prose.
     * @return array List of concept objects.
     */
    protected function analyse(string $text): array {
        $model = get_config('local_contentchecker', 'model_questions') ?: 'qwen3.5:35b';
        $max = self::MAX_CONCEPTS;

        $prompt = "You are helping a medical educator illustrate course content.\n"
            . "Read the PASSAGE and identify up to {$max} concepts that would be "
            . "genuinely clearer with a visual. Prefer concepts where a picture "
            . "does work that prose cannot.\n\n"
            . "For each concept set `mediatype`:\n"
            . "  model3d  - spatial anatomy a learner must rotate to understand\n"
            . "             (organs, bones, molecular structures)\n"
            . "  diagram  - a process, pathway, classification or relationship\n"
            . "             (flowcharts, cycles, hierarchies)\n"
            . "  image    - an appearance a learner must recognise\n"
            . "             (clinical signs, specimens, equipment)\n\n"
            . "`searchterms` is ONE short phrase of 2-4 plain words for an image search. "
            . "Not a sentence, NOT a comma-separated list of alternatives -- a "
            . "single phrase, e.g. \"cardiac anatomy\" or \"knee ligaments\".\n"
            . "`reason` is one short sentence on what the visual adds.\n"
            . "Do NOT suggest a visual for administrative text such as learning "
            . "objectives, welcomes or assessment instructions.\n\n"
            . "PASSAGE:\n" . $text;

        try {
            $result = $this->client->generate_json($model, $prompt, self::schema(),
                pipeline::atomise_budget());
        } catch (\Throwable $e) {
            throw new \moodle_exception('error:suggestfailed', 'local_contentchecker', '',
                $e->getMessage());
        }

        $concepts = [];
        foreach (($result['concepts'] ?? []) as $c) {
            $name = trim((string) ($c['concept'] ?? ''));
            if ($name === '') {
                continue;
            }
            $concepts[] = (object) [
                'concept' => $name,
                'mediatype' => in_array($c['mediatype'] ?? '',
                    ['model3d', 'diagram', 'image'], true) ? $c['mediatype'] : 'image',
                'reason' => trim((string) ($c['reason'] ?? '')),
                'searchterms' => self::clean_terms(
                    (string) ($c['searchterms'] ?? ''), $name),
                'assets' => [],
                'images' => [],
                'broadened' => false,
            ];
        }

        return array_slice($concepts, 0, self::MAX_CONCEPTS);
    }

    /**
     * The distinctive words in a phrase, for matching against the registry.
     *
     * A plain "longer than three characters" filter throws away exactly the
     * terms that identify a scientific asset. Measured: a concept called "ATP
     * Molecular Structure" failed to match the registered "Mitochondrial ATP
     * synthase" because ATP is three characters, while the useless word
     * "molecular" survived. Acronyms and alphanumeric identifiers are kept
     * whatever their length.
     *
     * Returned weighted, because the two kinds of match are not equally
     * informative: "ATP" appearing in an asset name almost certainly means it
     * is the right asset, whereas "cardiac" might be coincidence. Scoring them
     * the same forced a threshold that either admitted noise or rejected a
     * single decisive acronym.
     *
     * @param string $phrase Original-case text.
     * @return array keyword => weight.
     */
    protected static function keywords(string $phrase): array {
        $tokens = preg_split('/\W+/u', $phrase, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keep = [];

        foreach ($tokens as $token) {
            $lower = \core_text::strtolower($token);

            // ATP, DNA, RNA, ECG, CBC ... and identifiers like p53 or 5ARA.
            $isacronym = (bool) preg_match('/^[A-Z]{2,6}$/', $token)
                || (bool) preg_match('/\d/', $token);

            if ($isacronym) {
                // On its own, enough to clear the threshold.
                $keep[$lower] = self::MIN_ASSET_SCORE;
                continue;
            }
            if (\core_text::strlen($token) > 3 && !in_array($lower, self::STOPWORDS, true)) {
                $keep[$lower] = 1;
            }
        }

        return $keep;
    }

    /**
     * Reduce whatever the model returned to one searchable phrase.
     *
     * Asked for a phrase, models routinely return a comma-separated list of
     * alternatives. Passing that whole string to an image API searches for all
     * of it at once and reliably returns nothing, which looks like "no images
     * exist" rather than "we asked badly".
     *
     * @param string $terms The raw searchterms value.
     * @param string $fallback The concept name, used when terms are unusable.
     * @return string A short search phrase.
     */
    protected static function clean_terms(string $terms, string $fallback): string {
        $terms = trim($terms);
        if ($terms === '') {
            $terms = $fallback;
        }

        // Take the first alternative only.
        foreach ([',', ';', '/', '|'] as $sep) {
            if (strpos($terms, $sep) !== false) {
                $terms = substr($terms, 0, strpos($terms, $sep));
            }
        }

        $words = preg_split('/\s+/u', trim($terms), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_slice($words, 0, 4);

        return $words ? implode(' ', $words) : $fallback;
    }

    /**
     * Registered 3D models and diagrams that match a concept.
     *
     * Matched on the words of the search terms rather than the whole phrase,
     * because a registry entry is called "Knee Anatomy" while the concept is
     * "the knee joint and its ligaments".
     *
     * @param \stdClass $concept The concept.
     * @return array Asset rows, best first.
     */
    protected function matching_assets(\stdClass $concept): array {
        global $DB;

        $words = self::keywords($concept->searchterms . ' ' . $concept->concept);

        if (!$words) {
            return [];
        }

        $assets = $DB->get_records('local_cchecker_assets', ['enabled' => 1], 'name');
        $scored = [];

        foreach ($assets as $asset) {
            $haystack = \core_text::strtolower(
                $asset->name . ' ' . $asset->description);
            $score = 0;
            foreach ($words as $word => $weight) {
                if (strpos($haystack, $word) !== false) {
                    $score += $weight;
                }
            }
            // Ranking only: applied after the threshold so a type match can
            // reorder genuine hits but never promote a one-word coincidence.
            if ($score >= self::MIN_ASSET_SCORE
                    && $asset->assettype === $concept->mediatype) {
                $score++;
            }
            if ($score >= self::MIN_ASSET_SCORE) {
                $scored[] = [$score, $asset];
            }
        }

        usort($scored, fn($a, $b) => $b[0] <=> $a[0]);

        return array_map(fn($pair) => (object) [
            'id' => (int) $pair[1]->id,
            'name' => $pair[1]->name,
            'assettype' => $pair[1]->assettype,
            'licence' => (string) $pair[1]->licence,
            'attribution' => (string) $pair[1]->attribution,
            'score' => $pair[0],
        ], array_slice($scored, 0, 4));
    }

    /**
     * Draw a diagram for a concept instead of searching for one.
     *
     * Mermaid is used because it is text: the editor can read what will be
     * drawn before accepting it, it degrades to a legible definition if the
     * renderer is unavailable, and it stays diffable in course content rather
     * than becoming an opaque binary.
     *
     * @param \stdClass $concept The concept.
     * @return string Mermaid source, or '' when nothing usable came back.
     */
    protected function generate_diagram(\stdClass $concept): string {
        $model = get_config('local_contentchecker', 'model_questions') ?: 'qwen3.5:35b';

        $schema = [
            'type' => 'object',
            'properties' => ['mermaid' => ['type' => 'string']],
            'required' => ['mermaid'],
        ];

        $prompt = "Write a Mermaid diagram illustrating this concept from a "
            . "medical course.\n\n"
            . "CONCEPT: {$concept->concept}\n"
            . "WHY IT HELPS: {$concept->reason}\n\n"
            . "RULES:\n"
            . "- Output ONLY Mermaid source in the `mermaid` field. No markdown "
            . "fences, no prose.\n"
            . "- Start with a diagram type: `graph LR`, `graph TD` or `flowchart TD`.\n"
            . "- Node IDs must be ONE word with no spaces: write "
            . "`CombiningVowel[Combining vowel]`, never `Combining Vowel[...]`. "
            . "A space in an ID breaks the diagram.\n"
            . "- Keep node labels under 6 words.\n"
            . "- Use at most 10 nodes; a crowded diagram teaches nothing.\n"
            . "- Do not invent facts beyond the concept described.\n";

        try {
            $result = $this->client->generate_json($model, $prompt, $schema,
                pipeline::atomise_budget());
        } catch (\Throwable $e) {
            return '';
        }

        $source = trim((string) ($result['mermaid'] ?? ''));

        // Models routinely wrap the answer in a code fence despite being told
        // not to; stripping it is cheaper than a retry.
        $source = preg_replace('/^```(?:mermaid)?\s*|\s*```$/m', '', $source);
        $source = trim((string) $source);

        // Anything that does not start with a diagram declaration will not
        // render, and showing an editor broken source is worse than showing
        // none.
        if (!preg_match('/^(graph|flowchart|sequenceDiagram|classDiagram|mindmap)\b/i',
                $source)) {
            return '';
        }

        return $source;
    }

    /**
     * Keep only images whose own title relates to the concept.
     *
     * Applied to broadened searches only. The narrow query already asked for
     * exactly the concept, so its results need no second opinion; a broadened
     * one asked for something looser and will happily return a holiday snap
     * that shares one word.
     *
     * @param array $results image_result objects from a source.
     * @param \stdClass $concept The concept being illustrated.
     * @return array The subset worth showing.
     */
    protected function relevant_only(array $results, \stdClass $concept): array {
        $wanted = self::keywords($concept->searchterms . ' ' . $concept->concept);
        if (!$wanted) {
            return $results;
        }

        $keep = [];
        foreach ($results as $result) {
            $haystack = \core_text::strtolower($result->title . ' ' . $result->attribution);
            foreach (array_keys($wanted) as $word) {
                if (strpos($haystack, $word) !== false) {
                    $keep[] = $result;
                    break;
                }
            }
        }

        return $keep;
    }

    /**
     * Progressively broader queries to try for one concept.
     *
     * @param \stdClass $concept The concept.
     * @return array Queries, most specific first.
     */
    protected function query_ladder(\stdClass $concept): array {
        $words = preg_split('/\s+/u', $concept->searchterms, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $ladder = [$concept->searchterms];

        if (count($words) > 2) {
            $ladder[] = implode(' ', array_slice($words, 0, 2));
        }

        // Last resort: the single most distinctive word, which is the longest
        // one that is not a filler term.
        $distinctive = '';
        foreach ($words as $word) {
            $lower = \core_text::strtolower($word);
            if (in_array($lower, self::STOPWORDS, true) || \core_text::strlen($word) < 5) {
                continue;
            }
            if (\core_text::strlen($word) > \core_text::strlen($distinctive)) {
                $distinctive = $word;
            }
        }
        if ($distinctive !== '') {
            $ladder[] = $distinctive;
        }

        return array_values(array_unique($ladder));
    }

    /**
     * Openly licensed images that match a concept.
     *
     * @param \stdClass $concept The concept.
     * @param \context $context Context the search is made from.
     * @return array Image results.
     */
    protected function matching_images(\stdClass $concept, \context $context): array {
        $sources = source_registry::available();
        if (!isset($sources['openverse'])) {
            return [];
        }

        // An image library indexes pictures of things, not of ideas. A precise
        // phrase such as "medical term structure diagram" returns nothing while
        // "medical terminology" returns plenty, so a miss falls back to
        // progressively broader queries rather than reporting no images exist.
        $concept->broadened = false;
        foreach ($this->query_ladder($concept) as $i => $query) {
            try {
                $results = $sources['openverse']->search($query, 1, $context);
            } catch (\Throwable $e) {
                // A rate-limited image search must not lose the whole suggestion.
                return [];
            }
            // A broader query buys recall at the cost of precision, so anything
            // it returns has to earn its place: an image whose own title shares
            // no distinctive word with the concept is not an illustration of
            // it. Without this, "the four-part structure of a medical term"
            // was offered "redneck medical terms".
            $relevant = $i > 0
                ? $this->relevant_only($results, $concept)
                : $results;

            if ($relevant) {
                $concept->broadened = $i > 0;
                return array_map(fn($r) => $r->to_array(),
                    array_slice($relevant, 0, self::IMAGES_PER_CONCEPT));
            }
        }

        return [];
    }
}
