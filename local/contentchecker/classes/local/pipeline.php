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
use local_contentchecker\reference\fetcher;
use local_contentchecker\reference\fetcher_factory;

defined('MOODLE_INTERNAL') || die();

/**
 * Atomise course prose, retrieve reference material, adjudicate.
 *
 * Two guarantees this design makes, both of which matter more in a medical
 * context than raw throughput:
 *
 * **Nothing is cited that was not retrieved.** The adjudicator only ever sees
 * passages pulled from the configured reference layer, and a supporting quote
 * must be at least 20 characters and present verbatim in a cited source before
 * the UI shows it as trustworthy. Model-invented citations are the
 * characteristic failure of this kind of tool.
 *
 * **No verdict without evidence.** If nothing clears the similarity floor the
 * claim is reported as needs_source rather than judged on weak evidence.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pipeline {

    /** @var int Characters of course prose per atomiser call. */
    const SEGMENT_CHARS = 1600;

    /** @var array Verdicts that put an item into "Needs review". */
    const FLAGGED = ['contradicted', 'contested', 'partially_supported', 'outdated'];

    /** @var ai_backend The AI backend. */
    protected $client;

    /** @var fetcher The reference-fetching strategy. */
    protected $fetcher;

    /** @var int Segments whose extraction call failed during this run. */
    protected $extractionfailures = 0;

    /** @var string|null The last extraction failure, surfaced on the check row. */
    protected $lastextractionerror = null;

    /**
     * Output token ceiling for claim extraction.
     *
     * Deliberately much larger than the adjudicator's. Extraction emits one
     * object per assertion, each repeating a full source sentence verbatim, so
     * its output scales with the passage; a verdict is a fixed handful of short
     * fields. Sharing one ceiling silently truncated extraction.
     *
     * @return int Tokens.
     */
    public static function atomise_budget(): int {
        $configured = (int) get_config('local_contentchecker', 'numpredict_atomise');
        if ($configured > 0) {
            return $configured;
        }
        // Roughly four times the segment size in tokens, which cleared the
        // longest segment this plugin produces with headroom to spare.
        return 3000;
    }

    /**
     * Constructor.
     *
     * @param ai_backend|null $client Optional backend override.
     * @param fetcher|null $fetcher Optional fetcher override.
     */
    public function __construct(?ai_backend $client = null, ?fetcher $fetcher = null) {
        $this->client = $client ?? client_factory::make();
        $this->fetcher = $fetcher ?? fetcher_factory::make($this->client);
    }

    /**
     * Schema for the atomiser.
     *
     * `source_sentence` is what makes an approved correction applicable: the
     * claim itself is rewritten to stand alone, so it will not appear verbatim
     * in the page and cannot be used to locate the text to replace.
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
                            'source_sentence' => ['type' => 'string'],
                            'kind' => ['type' => 'string', 'enum' =>
                                ['factual', 'definitional', 'procedural', 'pedagogical']],
                            'checkable' => ['type' => 'boolean'],
                        ],
                        'required' => ['text', 'source_sentence', 'kind', 'checkable'],
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
     * Execute a queued check.
     *
     * @param int $checkid Row id in local_cchecker_checks.
     * @return void
     */
    public function execute(int $checkid): void {
        global $DB;

        $check = $DB->get_record('local_cchecker_checks', ['id' => $checkid], '*', MUST_EXIST);
        $check->status = 'running';
        $check->timestarted = time();
        $check->model = get_config('local_contentchecker', 'model_adjudicate') ?: 'qwen3.5:35b';
        $DB->update_record('local_cchecker_checks', $check);

        try {
            $items = $this->items_for($check);
            $claims = $this->dedupe($this->atomise($items));
            $this->adjudicate_all($check, $claims);

            $check->status = 'complete';
            $check->progress = 100;
            $check->numitems = count($items);

            // A run that could not read some of its content finished, but it
            // did not verify what it could not read. Say so on the record
            // rather than letting it pass as a clean result.
            if ($this->extractionfailures > 0) {
                $check->errormsg = get_string('error:extractionpartial',
                    'local_contentchecker', (object) [
                        'count' => $this->extractionfailures,
                        'reason' => (string) $this->lastextractionerror,
                    ]);
            }
            [$insql, $inparams] = $DB->get_in_or_equal(self::FLAGGED, SQL_PARAMS_NAMED, 'v');
            $check->numflagged = $DB->count_records_select('local_cchecker_suggestions',
                "checkid = :checkid AND verdict {$insql}",
                ['checkid' => $check->id] + $inparams);
        } catch (\Throwable $e) {
            $check->status = 'failed';
            $check->errormsg = $e->getMessage();
        }

        $check->timefinished = time();
        $DB->update_record('local_cchecker_checks', $check);
    }

    /**
     * The content items a check covers.
     *
     * @param \stdClass $check The check row.
     * @return array List of content_source items.
     */
    protected function items_for(\stdClass $check): array {
        if ($check->cmid) {
            return content_source::for_cm((int) $check->cmid);
        }
        if ($check->sectionnum >= 0) {
            return content_source::for_section((int) $check->courseid, (int) $check->sectionnum);
        }
        return content_source::for_course((int) $check->courseid);
    }

    /**
     * Split each item's prose into standalone claims.
     *
     * @param array $items content_source items.
     * @return array List of claim arrays.
     */
    protected function atomise(array $items): array {
        $model = get_config('local_contentchecker', 'model_atomise') ?: 'qwen3.5:latest';
        $claims = [];

        foreach ($items as $item) {
            foreach ($this->segments($item->text) as $segment) {
                // The `checkable` instruction is spelled out at this length for a
                // measured reason. The terser wording that preceded it made
                // qwen3.5:latest -- the DEFAULT atomiser -- classify claims as
                // `kind: factual` and then mark every one `checkable: false`.
                // Every claim was therefore discarded, the run completed with
                // zero findings, and the week went green: a "Verified OK" badge
                // on content nothing had actually read. qwen3.5:35b got it right
                // on the same prompt, so testing only the large model hid it.
                $prompt = "You are preparing university medical course content for fact-checking.\n"
                    . "Split the passage into atomic, independently verifiable claims. "
                    . "One assertion each.\n"
                    . "Rewrite `text` to stand alone without the surrounding passage.\n"
                    . "Set `source_sentence` to the sentence from the passage the claim came "
                    . "from, copied VERBATIM, character for character. Never paraphrase it.\n\n"
                    . "`kind` classifies the claim:\n"
                    . "  factual      - a statement of fact about the world (MOST claims)\n"
                    . "  definitional - states what a term means\n"
                    . "  procedural   - describes how something is done\n"
                    . "  pedagogical  - course admin: welcomes, learning objectives,\n"
                    . "                 headings, navigation. NOT medical content.\n\n"
                    . "`checkable` MUST be true for factual, definitional and procedural\n"
                    . "claims, because those can be checked against a reference. Set it\n"
                    . "false ONLY for pedagogical claims. Anatomy, physiology and clinical\n"
                    . "statements are ALWAYS checkable=true.\n"
                    . "Do NOT add information that is not in the passage.\n\n"
                    . "PASSAGE:\n" . $segment;

                try {
                    // Extraction needs a far bigger output budget than a
                    // verdict does: it emits one object per assertion and each
                    // echoes a whole source sentence, so the response grows
                    // with the passage. The shared 600-token ceiling truncated
                    // a 650-character passage mid-string, json_decode failed,
                    // and this catch swallowed it -- the run then finished with
                    // zero claims and reported success.
                    $result = $this->client->generate_json($model, $prompt,
                        self::claim_schema(), self::atomise_budget());
                } catch (\Throwable $e) {
                    // Still tolerated so one bad segment cannot lose the rest of
                    // the run, but no longer silent: the count is reported on
                    // the check so "found nothing" is distinguishable from
                    // "could not read anything".
                    $this->extractionfailures++;
                    $this->lastextractionerror = $e->getMessage();
                    continue;
                }

                foreach (($result['claims'] ?? []) as $claim) {
                    if (empty($claim['text']) || !self::is_checkable($claim)) {
                        continue;
                    }
                    // Same anti-hallucination gate as the supporting quote: if
                    // the "verbatim" sentence is not actually in the passage,
                    // we must not offer to rewrite the page using it.
                    $sentence = (string) ($claim['source_sentence'] ?? '');
                    $claim['source_sentence'] = $this->locate($sentence, $segment);
                    $claim['item'] = $item;
                    $claims[] = $claim;
                }
            }
        }
        return $claims;
    }

    /**
     * Should this claim be sent for adjudication?
     *
     * Deliberately does NOT gate on `checkable` alone. A model that classifies
     * a claim as factual, definitional or procedural and then marks it
     * uncheckable has contradicted itself, and honouring that contradiction
     * throws away real medical content. The observed cost of getting this wrong
     * is asymmetric: dropping a genuine claim yields a green "Verified OK" on
     * unchecked material, whereas keeping a borderline one costs a little GPU
     * time and shows the reviewer one extra row.
     *
     * `kind` is therefore the gate, and `checkable` is honoured only when the
     * two agree.
     *
     * @param array $claim A claim from the atomiser.
     * @return bool True when it should be adjudicated.
     */
    protected static function is_checkable(array $claim): bool {
        $kind = $claim['kind'] ?? 'factual';

        // Course framing is genuinely not checkable against a medical source.
        if ($kind === 'pedagogical') {
            return false;
        }

        return true;
    }

    /**
     * Collapse near-identical claims.
     *
     * The same assertion normally recurs across the overview, the lecture and
     * the reading. Adjudicating it three times costs GPU time and triples the
     * review burden for no extra signal.
     *
     * @param array $claims Claims with text.
     * @return array Unique claims, each with a 'vec' key.
     */
    protected function dedupe(array $claims): array {
        $threshold = (float) (get_config('local_contentchecker', 'dedupe') ?: 0.95);
        $kept = [];
        foreach ($claims as $claim) {
            try {
                $claim['vec'] = $this->client->embed($claim['text']);
            } catch (\Throwable $e) {
                $claim['vec'] = [];
                $kept[] = $claim;
                continue;
            }
            foreach ($kept as $existing) {
                if ($existing['vec'] && \local_contentchecker\api\gpu_client::cosine(
                        $claim['vec'], $existing['vec']) > $threshold) {
                    continue 2;
                }
            }
            $kept[] = $claim;
        }
        return $kept;
    }

    /**
     * Retrieve reference material and judge every claim.
     *
     * @param \stdClass $check The check row.
     * @param array $claims Deduped claims.
     * @return void
     */
    protected function adjudicate_all(\stdClass $check, array $claims): void {
        global $DB;

        $topk = (int) (get_config('local_contentchecker', 'topk') ?: 3);
        $minscore = (float) (get_config('local_contentchecker', 'minscore') ?: 0.55);
        $total = max(1, count($claims));
        $now = time();

        foreach ($claims as $i => $claim) {
            $item = $claim['item'];
            $matches = $this->fetcher->retrieve($claim['text'], $claim['vec'], $topk, $minscore);

            $record = (object) [
                'checkid' => $check->id,
                'cmid' => $item->cmid,
                'itemtype' => $item->modname,
                'itemname' => \core_text::substr($item->name, 0, 255),
                'claim' => $claim['text'],
                'kind' => $claim['kind'] ?? 'factual',
                'verdict' => 'needs_source',
                'quorum' => '',
                'topscore' => $matches ? $matches[0][0] : 0,
                'currenttext' => $claim['source_sentence'],
                'suggestedtext' => null,
                'sourceref' => $matches ? $matches[0][1]->url : null,
                'quote' => null,
                'quoteverbatim' => 0,
                'confidence' => 0,
                'reasoning' => null,
                'decision' => 'pending',
                'applied' => 0,
                'timecreated' => $now,
            ];

            if ($matches || !$this->fetcher->supplies_passages()) {
                try {
                    $this->judge($record, $claim['text'], $matches);
                } catch (\Throwable $e) {
                    // One unparseable claim must not abort the whole check.
                    $record->verdict = 'error';
                    $record->quorum = \core_text::substr($e->getMessage(), 0, 90);
                }
            }

            $suggestionid = $DB->insert_record('local_cchecker_suggestions', $record);
            foreach ($matches as [$score, $passage]) {
                $DB->insert_record('local_cchecker_evidence', (object) [
                    'suggestionid' => $suggestionid,
                    'title' => \core_text::substr($passage->title, 0, 255),
                    'url' => $passage->url,
                    'tier' => $passage->tier,
                    'score' => $score,
                    'snippet' => \core_text::substr($passage->content, 0, 600),
                ]);
            }

            $DB->set_field('local_cchecker_checks', 'progress',
                (int) round(100 * ($i + 1) / $total), ['id' => $check->id]);
        }
    }

    /**
     * Judge one claim, escalating to a quorum when the verdict is costly to get wrong.
     *
     * Pass 1 shows the adjudicator all retrieved passages together. Only
     * contradicted and partially_supported escalate to pass 2, where each
     * passage is judged alone; if those independent judgements disagree the
     * verdict is downgraded to contested.
     *
     * This exists because of a measured false positive. Judged against a single
     * passage, the adjudicator marked the course line "standing upright with
     * feet together" as CONTRADICTED at confidence 1.0. The reference actually
     * reads "feet together (or slightly separated)" -- the course was right and
     * the machine was certain and wrong. Red-flagging correct content burns
     * faculty trust far faster than a missed flag does, which is also why
     * confidence is recorded for inspection but never used to rank, filter or
     * gate anything.
     *
     * @param \stdClass $record Suggestion record to populate, by reference.
     * @param string $claimtext The claim.
     * @param array $matches Retrieved [score, passage] pairs.
     * @return void
     */
    protected function judge(\stdClass $record, string $claimtext, array $matches): void {
        $model = get_config('local_contentchecker', 'model_adjudicate') ?: 'qwen3.5:35b';

        $passages = array_map(fn($m) => $m[1], $matches);
        $verdict = $this->ask($model, $claimtext, $passages);

        $record->verdict = $verdict['verdict'];
        $record->quorum = get_string('quorum:single', 'local_contentchecker');
        $record->reasoning = $verdict['reasoning'] ?? '';
        $record->confidence = $verdict['confidence'] ?? 0;
        $record->quote = $verdict['supporting_quote'] ?? '';
        $record->quoteverbatim = $this->quote_is_verbatim($record->quote, $passages) ? 1 : 0;

        // Only a correction that actually differs from the live text is worth
        // showing as a diff.
        $correction = trim((string) ($verdict['suggested_correction'] ?? ''));
        if ($correction !== '' && $correction !== trim((string) $record->currenttext)) {
            $record->suggestedtext = $correction;
        }

        if (!in_array($record->verdict, ['contradicted', 'partially_supported'], true)) {
            return;
        }

        $singles = [];
        foreach ($passages as $passage) {
            $singles[] = $this->ask($model, $claimtext, [$passage])['verdict'];
        }
        if (!$singles) {
            return;
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
     * @param array $passages Reference passages.
     * @return array Decoded verdict.
     */
    protected function ask(string $model, string $claimtext, array $passages): array {
        $sources = '';
        foreach ($passages as $i => $passage) {
            $sources .= '[SOURCE ' . ($i + 1) . " | {$passage->title}]\n{$passage->content}\n\n";
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
     * Does a quote actually appear in a source?
     *
     * The length floor matters: an empty string is a substring of everything,
     * so a naive check passes every time the model declines to quote.
     *
     * @param string|null $quote The claimed quote.
     * @param array $passages Reference passages.
     * @return bool True when the quote is genuinely present.
     */
    protected function quote_is_verbatim(?string $quote, array $passages): bool {
        $needle = trim(preg_replace('/\s+/u', ' ', (string) $quote), " \t\n\r\0\x0B\"");
        if (\core_text::strlen($needle) < 20) {
            return false;
        }
        foreach ($passages as $passage) {
            $hay = preg_replace('/\s+/u', ' ', $passage->content);
            if (stripos($hay, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return a sentence only if it is genuinely present in the passage.
     *
     * A source sentence that the model paraphrased cannot be used to locate
     * text for replacement, so it is discarded rather than approximated. The
     * suggestion then still shows in the ledger for a human to act on by hand.
     *
     * @param string $sentence The claimed verbatim sentence.
     * @param string $segment The passage it should have come from.
     * @return string|null The sentence, or null when it is not really there.
     */
    protected function locate(string $sentence, string $segment): ?string {
        $needle = trim($sentence);
        if (\core_text::strlen($needle) < 20) {
            return null;
        }
        return strpos($segment, $needle) !== false ? $needle : null;
    }

    /**
     * Split item text on paragraph boundaries.
     *
     * @param string $text Item prose.
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

    /**
     * The structured result for a completed check.
     *
     * This is the contract the spec defines for a verification call, assembled
     * from the persisted rows so a live call and a background job return the
     * same shape.
     *
     * @param int $checkid The check id.
     * @return array {status, issues[], sources_checked[]}.
     */
    public static function result_for(int $checkid): array {
        global $DB;

        $check = $DB->get_record('local_cchecker_checks', ['id' => $checkid]);
        if (!$check) {
            return ['status' => 'error', 'issues' => [], 'sources_checked' => []];
        }
        if ($check->status === 'failed') {
            return [
                'status' => 'error',
                'error' => $check->errormsg,
                'issues' => [],
                'sources_checked' => [],
            ];
        }
        if ($check->status !== 'complete') {
            return [
                'status' => $check->status,
                'progress' => (int) $check->progress,
                'issues' => [],
                'sources_checked' => [],
            ];
        }

        [$insql, $inparams] = $DB->get_in_or_equal(self::FLAGGED, SQL_PARAMS_NAMED, 'v');
        $rows = $DB->get_records_select('local_cchecker_suggestions',
            "checkid = :checkid AND verdict {$insql}",
            ['checkid' => $checkid] + $inparams, 'id ASC');

        $issues = [];
        foreach ($rows as $row) {
            $issues[] = [
                'id' => (int) $row->id,
                'claim' => $row->claim,
                'current_text' => $row->currenttext,
                'suggested_text' => $row->suggestedtext,
                'source_reference' => $row->sourceref,
                'confidence' => (float) $row->confidence,
                'verdict' => $row->verdict,
                'decision' => $row->decision,
            ];
        }

        $sources = $DB->get_records_sql(
            "SELECT DISTINCT e.title, e.url, e.tier
               FROM {local_cchecker_evidence} e
               JOIN {local_cchecker_suggestions} s ON s.id = e.suggestionid
              WHERE s.checkid = :checkid",
            ['checkid' => $checkid]);

        // A completed run that extracted nothing at all from real content is an
        // anomaly, not a clean bill of health -- it is what a broken atomiser
        // prompt or a silently empty model response looks like. Reporting it as
        // "ok" would put a green badge on unread material, so it gets its own
        // state and the reviewer is told to look.
        $anyclaims = $DB->record_exists('local_cchecker_suggestions',
            ['checkid' => $checkid]);
        $status = $issues ? 'needs_review' : 'ok';
        if (!$issues && !$anyclaims && (int) $check->numitems > 0) {
            $status = 'empty';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'sources_checked' => array_values(array_map(fn($s) => [
                'title' => $s->title,
                'url' => $s->url,
                'tier' => (int) $s->tier,
            ], $sources)),
        ];
    }
}
