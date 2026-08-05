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
 * The evidence corpus: allowlisted sources, chunked and embedded.
 *
 * Retrieval is cosine similarity over locally stored embeddings. Live keyword
 * search against third-party APIs is deliberately NOT a channel here. Measured
 * on 2026-07-31: PubMed relevance-searching for foundational anatomy returned
 * CT-gantry papers, NCBI Bookshelf restricted to StatPearls returned a video
 * laryngoscopy article for an anatomical-position query, two of three claims
 * retrieved nothing at all, and NCBI then began returning HTTP 429.
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class corpus {

    /** @var int Target characters per chunk. */
    const CHUNK_CHARS = 1200;

    /** @var int Characters of overlap between adjacent chunks. */
    const CHUNK_OVERLAP = 200;

    /** @var ai_client The AI client. */
    protected $client;

    /**
     * Constructor.
     *
     * @param ai_client|null $client Optional client override.
     */
    public function __construct(?ai_client $client = null) {
        $this->client = $client ?? new ai_client();
    }

    /**
     * How many sources and passages the corpus holds.
     *
     * @return array [sources, chunks]
     */
    public static function status(): array {
        global $DB;
        return [
            $DB->count_records('local_emdverify_source', ['enabled' => 1]),
            $DB->count_records('local_emdverify_chunk'),
        ];
    }

    /**
     * Does the corpus contain only tier-3 material?
     *
     * The UI must say so plainly. A tier-3-only corpus can show that the
     * pipeline works; it cannot settle a clinical question.
     *
     * @return bool True when nothing above tier 3 is enabled.
     */
    public static function is_tier3_only(): bool {
        global $DB;
        return !$DB->record_exists_select('local_emdverify_source',
            'enabled = 1 AND tier < 3');
    }

    /**
     * Fetch, chunk and embed one source.
     *
     * @param \stdClass $source Row from local_emdverify_source.
     * @return int Number of chunks stored.
     */
    public function ingest(\stdClass $source): int {
        global $DB;

        $text = $this->fetch($source);
        if ($text === '') {
            return 0;
        }

        $DB->delete_records('local_emdverify_chunk', ['sourceid' => $source->id]);
        $now = time();
        $idx = 0;
        foreach ($this->chunk($text) as $piece) {
            $DB->insert_record('local_emdverify_chunk', (object) [
                'sourceid' => $source->id,
                'chunkidx' => $idx++,
                'content' => $piece,
                'embedding' => json_encode($this->client->embed($piece)),
                'timecreated' => $now,
            ]);
        }

        $source->numchunks = $idx;
        $source->timefetched = $now;
        $source->timemodified = $now;
        $DB->update_record('local_emdverify_source', $source);
        return $idx;
    }

    /**
     * Retrieve the best-matching passages for a claim.
     *
     * @param array $claimvec Embedding of the claim.
     * @param int $topk How many to return.
     * @param float $minscore Similarity floor.
     * @return array List of [score, chunk row + source metadata].
     */
    public function retrieve(array $claimvec, int $topk, float $minscore): array {
        global $DB;

        $sql = "SELECT c.id, c.content, c.embedding, s.title, s.ref, s.tier
                  FROM {local_emdverify_chunk} c
                  JOIN {local_emdverify_source} s ON s.id = c.sourceid
                 WHERE s.enabled = 1";
        $scored = [];
        foreach ($DB->get_recordset_sql($sql) as $row) {
            $vec = json_decode($row->embedding, true);
            if (!is_array($vec)) {
                continue;
            }
            unset($row->embedding);
            $scored[] = [ai_client::cosine($claimvec, $vec), $row];
        }

        usort($scored, fn($a, $b) => $b[0] <=> $a[0]);
        $scored = array_slice($scored, 0, $topk);

        return array_values(array_filter($scored, fn($pair) => $pair[0] >= $minscore));
    }

    /**
     * Retrieve raw text for a source.
     *
     * @param \stdClass $source The source row.
     * @return string Plain text, or '' on failure.
     */
    protected function fetch(\stdClass $source): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $url = $source->sourcetype === 'wikipedia'
            ? 'https://en.wikipedia.org/w/index.php?action=raw&title=' . rawurlencode($source->ref)
            : $source->ref;

        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 60, 'CURLOPT_FOLLOWLOCATION' => true]);
        $body = $curl->get($url);
        if ($curl->get_errno() !== 0 || !is_string($body)) {
            return '';
        }

        if ($source->sourcetype === 'wikipedia') {
            return $this->clean_wikitext($body);
        }
        return $this->clean_html($body);
    }

    /**
     * Strip wiki markup down to prose.
     *
     * @param string $raw Wikitext.
     * @return string Plain text.
     */
    protected function clean_wikitext(string $raw): string {
        $s = preg_replace('/<ref[^>]*>.*?<\/ref>/s', ' ', $raw);
        $s = preg_replace('/<ref[^>]*\/>/', ' ', $s);
        $s = preg_replace('/\{\{[^{}]*\}\}/', ' ', $s);
        $s = preg_replace('/\[\[(?:[^|\]]*\|)?([^\]]*)\]\]/', '$1', $s);
        $s = preg_replace("/'{2,}/", '', $s);
        return $this->clean_html($s);
    }

    /**
     * Strip tags and collapse whitespace.
     *
     * @param string $raw HTML or mixed markup.
     * @return string Plain text.
     */
    protected function clean_html(string $raw): string {
        $s = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/s', ' ', $raw);
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /**
     * Split text into sentence-aware overlapping windows.
     *
     * Chunking mid-sentence severs claims from their qualifiers, which is how
     * an adjudicator ends up judging "the feet are together" without ever
     * seeing "(or slightly separated)".
     *
     * @param string $text Source text.
     * @return array List of chunks.
     */
    protected function chunk(string $text): array {
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        $chunks = [];
        $cur = '';
        foreach ($sentences as $sentence) {
            if ($cur !== '' && (\core_text::strlen($cur) + \core_text::strlen($sentence) + 1)
                    > self::CHUNK_CHARS) {
                $chunks[] = trim($cur);
                $cur = \core_text::substr($cur, -self::CHUNK_OVERLAP);
            }
            $cur .= ' ' . $sentence;
        }
        if (trim($cur) !== '') {
            $chunks[] = trim($cur);
        }
        return array_values(array_filter($chunks,
            fn($c) => \core_text::strlen($c) > 120));
    }
}
