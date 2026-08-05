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

namespace local_emdverify\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Atomise course prose, retrieve evidence, adjudicate.
 *
 * Runs only from an adhoc task. A single week is a few hundred model calls;
 * this must never touch a page request.
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pipeline {

    /** @var int Characters of course prose per atomiser call. */
    const SEGMENT_CHARS = 1600;

    /** @var ai_client The AI client. */
    protected $client;

    /** @var corpus The evidence corpus. */
    protected $corpus;

    /**
     * Constructor.
     *
     * @param ai_client|null $client Optional client override.
     * @param corpus|null $corpus Optional corpus override.
     */
    public function __construct(?ai_client $client = null, ?corpus $corpus = null) {
        $this->client = $client ?? new ai_client();
        $this->corpus = $corpus ?? new corpus($this->client);
    }

    /**
     * Schema for the atomiser.
     *
     * @return array JSON Schema.
     */
    public static function claim_schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'claims' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string'],
                            'kind' => ['type' => 'string', 'enum' =>
                                ['factual', 'definitional', 'procedural', 'pedagogical']],
                            'checkable' => ['type' => 'boolean'],
                        ],
                        'required' => ['text', 'kind', 'checkable'],
                    ],
                ],
            ],
            'required' => ['claims'],
        ];
    }

    /**
     * Schema for the adjudicator.
     *
     * @return array JSON Schema.
     */
    public static function verdict_schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'verdict' => ['type' => 'string', 'enum' => [
                    'supported', 'partially_supported', 'contradicted',
                    'unsupported', 'outdated']],
                'supporting_quote' => ['type' => 'string'],
                'reasoning' => ['type' => 'string'],
                'confidence' => ['type' => 'number'],
                'suggested_correction' => ['type' => 'string'],
            ],
            'required' => ['verdict', 'reasoning', 'confidence'],
        ];
    }

    /**
     * Execute a queued run.
     *
     * @param int $runid Row id in local_emdverify_run.
     * @return void
     */
    public function execute(int $runid): void {
        global $DB;

        $run = $DB->get_record('local_emdverify_run', ['id' => $runid], '*', MUST_EXIST);
        $run->status = 'running';
        $run->timestarted = time();
        $run->model = get_config('local_emdverify', 'model_adjudicate') ?: 'qwen3.5:35b';
        $DB->update_record('local_emdverify_run', $run);

        try {
            $claims = $this->atomise($run);
            $claims = $this->dedupe($claims);
            $this->adjudicate_all($run, $claims);

            $run->status = 'complete';
            $run->progress = 100;
            $run->numclaims = count($claims);
            $run->numflagged = $DB->count_records_select('local_emdverify_claim',
                'runid = :runid AND verdict IN (:v1, :v2, :v3, :v4)',
                ['runid' => $run->id, 'v1' => 'contradicted', 'v2' => 'contested',
                 'v3' => 'partially_supported', 'v4' => 'outdated']);
        } catch (\Throwable $e) {
            $run->status = 'failed';
            $run->errormsg = $e->getMessage();
        }

        $run->timefinished = time();
        $DB->update_record('local_emdverify_run', $run);
    }

    /**
     * Split the week's page prose into standalone claims.
     *
     * @param \stdClass $run The run row.
     * @return array List of claim arrays.
     */
    protected function atomise(\stdClass $run): array {
        $model = get_config('local_emdverify', 'model_atomise') ?: 'qwen3.5:latest';
        $claims = [];

        foreach ($this->week_pages($run->courseid, $run->sectionnum) as $page) {
            foreach ($this->segments($page->text) as $segment) {
                $prompt = "You are preparing university medical course content for fact-checking.\n"
                    . "Split the passage into atomic, independently verifiable claims. "
                    . "One assertion each.\n"
                    . "Rewrite each claim to stand alone without the surrounding text.\n"
                    . "Set `checkable` false for pedagogical framing, learning objectives, "
                    . "welcomes, headings and navigation text.\n"
                    . "Do NOT add information that is not in the passage.\n\n"
                    . "PASSAGE:\n" . $segment;

                $result = $this->client->generate_json($model, $prompt, self::claim_schema());
                foreach (($result['claims'] ?? []) as $claim) {
                    if (empty($claim['checkable'])) {
                        continue;
                    }
                    $claim['cmid'] = $page->cmid;
                    $claim['pagename'] = $page->name;
                    $claims[] = $claim;
                }
            }
        }
        return $claims;
    }

    /**
     * Collapse near-identical claims.
     *
     * The same assertion normally recurs across the overview, the lecture and
     * the reading; adjudicating it three times wastes GPU and triples the
     * review burden for no extra signal.
     *
     * @param array $claims Claims with text.
     * @return array Unique claims, each with a 'vec' key.
     */
    protected function dedupe(array $claims): array {
        $threshold = (float) (get_config('local_emdverify', 'dedupe') ?: 0.95);
        $kept = [];
        foreach ($claims as $claim) {
            $claim['vec'] = $this->client->embed($claim['text']);
            foreach ($kept as $existing) {
                if (ai_client::cosine($claim['vec'], $existing['vec']) > $threshold) {
                    continue 2;
                }
            }
            $kept[] = $claim;
        }
        return $kept;
    }

    /**
     * Retrieve evidence and judge every claim.
     *
     * @param \stdClass $run The run row.
     * @param array $claims Deduped claims.
     * @return void
     */
    protected function adjudicate_all(\stdClass $run, array $claims): void {
        global $DB;

        $topk = (int) (get_config('local_emdverify', 'topk') ?: 3);
        $minscore = (float) (get_config('local_emdverify', 'minscore') ?: 0.55);
        $total = max(1, count($claims));
        $now = time();

        foreach ($claims as $i => $claim) {
            $matches = $this->corpus->retrieve($claim['vec'], $topk, $minscore);

            $record = (object) [
                'runid' => $run->id,
                'cmid' => $claim['cmid'],
                'pagename' => $claim['pagename'],
                'claimtext' => $claim['text'],
                'kind' => $claim['kind'] ?? 'factual',
                'verdict' => 'needs_source',
                'quorum' => '',
                'topscore' => $matches ? $matches[0][0] : 0,
                'quote' => null,
                'quoteverbatim' => 0,
                'confidence' => 0,
                'correction' => null,
                'reasoning' => null,
                'timecreated' => $now,
            ];

            if ($matches) {
                try {
                    $this->judge($record, $claim['text'], $matches);
                } catch (\Throwable $e) {
                    // One unparseable claim must not abort the whole run.
                    $record->verdict = 'error';
                    $record->quorum = substr($e->getMessage(), 0, 90);
                }
            }

            $claimid = $DB->insert_record('local_emdverify_claim', $record);
            foreach ($matches as [$score, $chunk]) {
                $DB->insert_record('local_emdverify_evidence', (object) [
                    'claimid' => $claimid,
                    'title' => $chunk->title,
                    'url' => $chunk->ref,
                    'tier' => $chunk->tier,
                    'score' => $score,
                    'snippet' => \core_text::substr($chunk->content, 0, 600),
                ]);
            }

            $run->progress = (int) round(100 * ($i + 1) / $total);
            $DB->set_field('local_emdverify_run', 'progress', $run->progress,
                ['id' => $run->id]);
        }
    }

    /**
     * Judge one claim, escalating to a quorum when the verdict is costly to get wrong.
     *
     * Pass 1 shows the adjudicator all retrieved passages together. Only
     * `contradicted` and `partially_supported` escalate to pass 2, where each
     * passage is judged alone; if those independent judgements disagree the
     * verdict is downgraded to `contested`.
     *
     * This exists because of a measured false positive. Judged against a single
     * passage, the adjudicator marked the course line "standing upright with
     * feet together" as CONTRADICTED at confidence 1.0. The reference actually
     * reads "feet together (or slightly separated)" — the course was right.
     * A red flag on correct content costs far more trust than a missed one.
     *
     * @param \stdClass $record Claim record to populate, by reference.
     * @param string $claimtext The claim.
     * @param array $matches Retrieved [score, chunk] pairs.
     * @return void
     */
    protected function judge(\stdClass $record, string $claimtext, array $matches): void {
        $model = get_config('local_emdverify', 'model_adjudicate') ?: 'qwen3.5:35b';

        $passages = array_map(fn($m) => $m[1], $matches);
        $verdict = $this->ask($model, $claimtext, $passages);

        $record->verdict = $verdict['verdict'];
        $record->quorum = get_string('quorum', 'local_emdverify');
        $record->reasoning = $verdict['reasoning'] ?? '';
        $record->confidence = $verdict['confidence'] ?? 0;
        $record->correction = $verdict['suggested_correction'] ?? '';
        $record->quote = $verdict['supporting_quote'] ?? '';
        $record->quoteverbatim = $this->quote_is_verbatim($record->quote, $passages) ? 1 : 0;

        if (!in_array($record->verdict, ['contradicted', 'partially_supported'], true)) {
            return;
        }

        $singles = [];
        foreach ($passages as $passage) {
            $singles[] = $this->ask($model, $claimtext, [$passage])['verdict'];
        }
        $agree = count(array_filter($singles, fn($v) => $v === $record->verdict));
        $record->quorum = $agree . '/' . count($singles) . ' (' . implode(', ', $singles) . ')';

        $anysupport = (bool) array_filter($singles, fn($v) => $v === 'supported');
        if ($agree < count($singles) && ($anysupport || $agree <= count($singles) / 2)) {
            $record->verdict = 'contested';
        }
    }

    /**
     * One adjudication call.
     *
     * @param string $model Model name.
     * @param string $claimtext The claim.
     * @param array $passages Chunk rows.
     * @return array Decoded verdict.
     */
    protected function ask(string $model, string $claimtext, array $passages): array {
        $sources = '';
        foreach ($passages as $i => $chunk) {
            $sources .= "[SOURCE " . ($i + 1) . " | {$chunk->title}]\n{$chunk->content}\n\n";
        }

        $prompt = "You are a medical content fact-checker for a university course.\n"
            . "Judge the CLAIM using ONLY the SOURCES below.\n\n"
            . "RULES (these override everything else):\n"
            . "- Use ONLY the sources. Do NOT use your own knowledge.\n"
            . "- If the sources do not address the claim, verdict MUST be \"unsupported\".\n"
            . "- `supporting_quote` must be copied VERBATIM from a source, or \"\".\n"
            . "- If a source permits the claim as one of several accepted variants, that is\n"
            . "  \"supported\", NOT \"contradicted\".\n"
            . "- Only use \"contradicted\" if a source directly and unambiguously conflicts.\n"
            . "- Never invent a citation, PMID or URL.\n"
            // Length limits are load-bearing, not style. A schema constrains
            // shape but not length, and an unbounded `reasoning` field ran past
            // the token ceiling and returned truncated, unparseable JSON.
            . "- Keep `reasoning` under 40 words. Keep `suggested_correction` under 40 words,\n"
            . "  and use \"\" unless the verdict is \"contradicted\".\n\n"
            . "CLAIM:\n{$claimtext}\n\nSOURCES:\n{$sources}";

        return $this->client->generate_json($model, $prompt, self::verdict_schema());
    }

    /**
     * Does the quote actually appear in a source?
     *
     * The length floor matters: an empty string is a substring of everything,
     * so a naive check passes every time the model declines to quote.
     *
     * @param string|null $quote The claimed quote.
     * @param array $passages Chunk rows.
     * @return bool True when the quote is genuinely present.
     */
    protected function quote_is_verbatim(?string $quote, array $passages): bool {
        $needle = trim(preg_replace('/\s+/u', ' ', (string) $quote), " \t\n\r\0\x0B\"");
        if (\core_text::strlen($needle) < 20) {
            return false;
        }
        foreach ($passages as $chunk) {
            $hay = preg_replace('/\s+/u', ' ', $chunk->content);
            if (stripos($hay, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * The week's page activities as plain text.
     *
     * @param int $courseid Course id.
     * @param int $sectionnum Section number.
     * @return array List of objects with cmid, name, text.
     */
    protected function week_pages(int $courseid, int $sectionnum): array {
        global $DB;

        $section = $DB->get_record('course_sections',
            ['course' => $courseid, 'section' => $sectionnum], '*', MUST_EXIST);

        $sql = "SELECT cm.id AS cmid, p.name, p.content
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'page'
                  JOIN {page} p ON p.id = cm.instance
                 WHERE cm.course = :courseid AND cm.section = :sectionid";

        $pages = [];
        foreach ($DB->get_records_sql($sql,
                ['courseid' => $courseid, 'sectionid' => $section->id]) as $row) {
            $text = trim(preg_replace('/\n{3,}/', "\n\n", html_to_text($row->content, 0, false)));
            if (\core_text::strlen($text) < 200) {
                continue; // Stubs such as a bare video embed have nothing to verify.
            }
            $pages[] = (object) ['cmid' => $row->cmid, 'name' => $row->name, 'text' => $text];
        }
        return $pages;
    }

    /**
     * Split page text on paragraph boundaries.
     *
     * @param string $text Page prose.
     * @return array List of segments.
     */
    protected function segments(string $text): array {
        $paragraphs = preg_split('/\n\s*\n/', $text) ?: [];
        $segments = [];
        $cur = '';
        foreach ($paragraphs as $para) {
            if ($cur !== '' && (\core_text::strlen($cur) + \core_text::strlen($para))
                    > self::SEGMENT_CHARS) {
                $segments[] = trim($cur);
                $cur = '';
            }
            $cur .= "\n\n" . $para;
        }
        if (trim($cur) !== '') {
            $segments[] = trim($cur);
        }
        return $segments;
    }
}
