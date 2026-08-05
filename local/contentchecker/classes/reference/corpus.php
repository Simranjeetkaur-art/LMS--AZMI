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

namespace local_contentchecker\reference;

use local_contentchecker\api\ai_backend;
use local_contentchecker\api\client_factory;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Server-side fetching, chunking and embedding of allowlisted reference sources.
 *
 * This is the plugin's own reference layer. It exists because live keyword
 * search against third-party APIs was measured and rejected as a retrieval
 * channel: PubMed relevance-searching for foundational anatomy returned
 * CT-gantry papers, NCBI Bookshelf returned a video laryngoscopy article for an
 * anatomical-position query, two of three claims retrieved nothing at all, and
 * NCBI then began answering HTTP 429. Retrieval here is cosine similarity over
 * passages that were deliberately ingested, so what gets cited is always
 * something an admin chose to trust.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class corpus {

    /** @var int Target characters per chunk. */
    const CHUNK_CHARS = 1200;

    /** @var int Characters of overlap carried into the next chunk. */
    const CHUNK_OVERLAP = 200;

    /** @var ai_backend Backend used for embeddings. */
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
     * Fetch, chunk and embed one source.
     *
     * @param \stdClass $source Row from local_cchecker_sources.
     * @return int Number of chunks stored.
     */
    public function ingest(\stdClass $source): int {
        global $DB;

        $text = $this->fetch($source);
        if ($text === '') {
            $source->lasterror = get_string('error:sourcefetch', 'local_contentchecker');
            $source->timemodified = time();
            $DB->update_record('local_cchecker_sources', $source);
            return 0;
        }

        // Replace wholesale rather than diffing: a re-ingest means the upstream
        // text changed, and a partial update would leave stale passages citable.
        $DB->delete_records('local_cchecker_chunks', ['sourceid' => $source->id]);

        $now = time();
        $idx = 0;
        foreach ($this->chunk($text) as $piece) {
            $DB->insert_record('local_cchecker_chunks', (object) [
                'sourceid' => $source->id,
                'chunkidx' => $idx++,
                'content' => $piece,
                'embedding' => json_encode($this->client->embed($piece)),
                'timecreated' => $now,
            ]);
        }

        $source->numchunks = $idx;
        $source->lasterror = null;
        $source->timefetched = $now;
        $source->timemodified = $now;
        $DB->update_record('local_cchecker_sources', $source);

        return $idx;
    }

    /**
     * Retrieve the best-matching passages for a claim vector.
     *
     * @param array $claimvector Embedding of the claim.
     * @param int $topk How many to return.
     * @param float $minscore Similarity floor.
     * @return array List of [score, passage] pairs.
     */
    public function retrieve(array $claimvector, int $topk, float $minscore): array {
        global $DB;

        $sql = "SELECT c.id, c.content, c.embedding, s.title, s.ref AS url, s.tier
                  FROM {local_cchecker_chunks} c
                  JOIN {local_cchecker_sources} s ON s.id = c.sourceid
                 WHERE s.enabled = 1";

        $scored = [];
        $rs = $DB->get_recordset_sql($sql);
        foreach ($rs as $row) {
            $vec = json_decode($row->embedding, true);
            if (!is_array($vec)) {
                continue;
            }
            unset($row->embedding);
            $scored[] = [self::cosine($claimvector, $vec), $row];
        }
        $rs->close();

        usort($scored, fn($a, $b) => $b[0] <=> $a[0]);
        $scored = array_slice($scored, 0, $topk);

        // The floor is applied after the sort so a claim with nothing relevant
        // returns empty rather than being judged on the least-bad passage.
        return array_values(array_filter($scored, fn($pair) => $pair[0] >= $minscore));
    }

    /**
     * Cosine similarity between two vectors.
     *
     * @param array $a First vector.
     * @param array $b Second vector.
     * @return float Similarity in [-1, 1].
     */
    public static function cosine(array $a, array $b): float {
        return \local_contentchecker\api\gpu_client::cosine($a, $b);
    }

    /**
     * Does the corpus contain anything above tier 3?
     *
     * A tier-3-only corpus can show that the pipeline behaves; it cannot settle
     * a clinical question, and the UI carries a standing warning while that is
     * the case.
     *
     * @return bool True when at least one enabled source is tier 1 or 2.
     */
    public static function has_authoritative_source(): bool {
        global $DB;
        return $DB->record_exists_select('local_cchecker_sources',
            'enabled = 1 AND tier < 3 AND numchunks > 0');
    }

    /**
     * How many usable passages exist.
     *
     * @return int Chunk count across enabled sources.
     */
    public static function chunk_count(): int {
        global $DB;
        return $DB->count_records_sql(
            "SELECT COUNT(c.id)
               FROM {local_cchecker_chunks} c
               JOIN {local_cchecker_sources} s ON s.id = c.sourceid
              WHERE s.enabled = 1");
    }

    /**
     * Retrieve raw text for a source.
     *
     * @param \stdClass $source The source row.
     * @return string Plain text, or '' on failure.
     */
    protected function fetch(\stdClass $source): string {
        if ($source->sourcetype === 'upload') {
            return $this->fetch_upload($source);
        }

        $url = $source->sourcetype === 'wikipedia'
            ? 'https://en.wikipedia.org/w/index.php?action=raw&title='
                . rawurlencode($source->ref)
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
        if ($source->sourcetype === 'youtube') {
            return $this->clean_html($body);
        }
        return $this->clean_html($body);
    }

    /**
     * Read an admin-uploaded reference document.
     *
     * @param \stdClass $source The source row.
     * @return string Plain text, or '' when the file is gone.
     */
    protected function fetch_upload(\stdClass $source): string {
        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_system::instance()->id,
            'local_contentchecker', 'source', $source->id, 'itemid', false);
        $text = '';
        foreach ($files as $file) {
            $text .= ' ' . $this->clean_html($file->get_content());
        }
        return trim($text);
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
     * Chunking mid-sentence severs a claim from its qualifiers, which is how an
     * adjudicator ends up judging "the feet are together" without ever seeing
     * "(or slightly separated)" -- a real false positive this guards against.
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
