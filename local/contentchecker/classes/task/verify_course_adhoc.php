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
use local_contentchecker\local\pipeline;
use local_contentchecker\local\queue;

/**
 * Runs one check from a background verification job.
 *
 * A job queues one of these per week, so cron makes progress in bounded steps
 * rather than holding a single task open for hours.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class verify_course_adhoc extends \core\task\adhoc_task {

    /**
     * A name for the task list.
     *
     * @return string Localised name.
     */
    public function get_name(): string {
        return get_string('task:verifycourse', 'local_contentchecker');
    }

    /**
     * Run the check.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $data = (array) $this->get_custom_data();
        $checkid = (int) ($data['checkid'] ?? 0);
        $jobid = (int) ($data['jobid'] ?? 0);

        $check = $DB->get_record('local_cchecker_checks', ['id' => $checkid]);
        if (!$check) {
            mtrace("Check {$checkid} no longer exists; nothing to do.");
            return;
        }
        if ($check->status === 'complete') {
            mtrace("Check {$checkid} already complete; skipping.");
            job_manager::check_finished($jobid);
            return;
        }

        // The same cap that throttles live requests applies here. A background
        // job must not saturate the GPU and lock editors out of the live check
        // they are sitting and waiting for. Waiting is fine in cron.
        $lock = queue::acquire(120);
        if (!$lock) {
            // Leaving the task unfinished makes cron retry it later, which is
            // exactly the wanted behaviour when the box is simply busy.
            throw new \moodle_exception('error:gpubusy', 'local_contentchecker');
        }

        try {
            mtrace("Verifying course {$check->courseid} section {$check->sectionnum}...");
            (new pipeline())->execute($checkid);
        } finally {
            $lock->release();
        }

        $check = $DB->get_record('local_cchecker_checks', ['id' => $checkid]);
        \local_contentchecker\event\check_completed::create([
            'context' => \context_course::instance((int) $check->courseid),
            'objectid' => $checkid,
            'other' => [
                'status' => $check->status,
                'numflagged' => (int) $check->numflagged,
            ],
        ])->trigger();

        mtrace("Check {$checkid} finished: {$check->status}, "
            . "{$check->numflagged} item(s) flagged.");

        job_manager::check_finished($jobid);
    }
}
