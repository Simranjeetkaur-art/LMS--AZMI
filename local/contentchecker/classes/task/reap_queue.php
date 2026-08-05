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

use local_contentchecker\local\job_manager;

/**
 * Closes out checks abandoned by a process that died mid-run.
 *
 * A PHP worker killed by an OOM or a deploy leaves its check row stuck at
 * "running" forever, which shows the editor a spinner that never resolves and
 * keeps its job permanently incomplete. Anything that has not moved in a long
 * time is marked failed so the UI tells the truth and the job can finish.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reap_queue extends \core\task\scheduled_task {

    /**
     * Seconds a check may sit at "running" before it is presumed dead.
     *
     * Generous on purpose: a cold load of the 35b adjudicator plus a week of
     * claims is legitimately slow, and killing a live run is worse than
     * showing a stale spinner for a while longer.
     *
     * @var int
     */
    const STALE_AFTER = 7200;

    /**
     * A name for the task list.
     *
     * @return string Localised name.
     */
    public function get_name(): string {
        return get_string('task:reapqueue', 'local_contentchecker');
    }

    /**
     * Mark abandoned checks as failed and settle their jobs.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $cutoff = time() - self::STALE_AFTER;
        $stale = $DB->get_records_select('local_cchecker_checks',
            'status = :running AND timestarted IS NOT NULL AND timestarted < :cutoff',
            ['running' => 'running', 'cutoff' => $cutoff]);

        foreach ($stale as $check) {
            $check->status = 'failed';
            $check->errormsg = get_string('error:abandoned', 'local_contentchecker');
            $check->timefinished = time();
            $DB->update_record('local_cchecker_checks', $check);

            mtrace("Marked abandoned check {$check->id} as failed.");
            job_manager::check_finished((int) $check->jobid);
        }
    }
}
