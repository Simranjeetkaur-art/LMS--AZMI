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

namespace local_contentchecker\task;

use local_contentchecker\reference\corpus;

/**
 * Re-ingests reference sources whose upstream text may have moved on.
 *
 * Runs weekly and off-peak: every ingest is a run of embedding calls against
 * the same single GPU that live checks use.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_corpus extends \core\task\scheduled_task {

    /** @var int Re-ingest a source when it was last fetched longer ago than this. */
    const MAX_AGE = 30 * DAYSECS;

    /**
     * A name for the task list.
     *
     * @return string Localised name.
     */
    public function get_name(): string {
        return get_string('task:refreshcorpus', 'local_contentchecker');
    }

    /**
     * Re-ingest stale sources.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        if (!\local_contentchecker\api\gpu_client::is_configured()) {
            mtrace('No AI endpoint configured; skipping corpus refresh.');
            return;
        }

        $cutoff = time() - self::MAX_AGE;
        $sources = $DB->get_records_select('local_cchecker_sources',
            'enabled = 1 AND (timefetched IS NULL OR timefetched < :cutoff)',
            ['cutoff' => $cutoff]);

        if (!$sources) {
            mtrace('No stale reference sources.');
            return;
        }

        $corpus = new corpus();
        foreach ($sources as $source) {
            try {
                $chunks = $corpus->ingest($source);
                mtrace("Re-ingested '{$source->title}': {$chunks} passage(s).");
            } catch (\Throwable $e) {
                // One unreachable source must not stop the rest refreshing.
                mtrace("Failed to ingest '{$source->title}': " . $e->getMessage());
            }
        }
    }
}
