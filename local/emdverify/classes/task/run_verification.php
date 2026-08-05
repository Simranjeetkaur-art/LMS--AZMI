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

namespace local_emdverify\task;

defined('MOODLE_INTERNAL') || die();

use local_emdverify\local\pipeline;

/**
 * Run one queued verification pass.
 *
 * Adhoc, never cron-scheduled and never in a page request: a single week is
 * several hundred model calls against a GPU that serialises per model.
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_verification extends \core\task\adhoc_task {

    /**
     * Descriptive name.
     *
     * @return string Task name.
     */
    public function get_name(): string {
        return get_string('pluginname', 'local_emdverify');
    }

    /**
     * Execute the run.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        if (empty($data->runid)) {
            mtrace('local_emdverify: no runid in custom data, nothing to do.');
            return;
        }
        mtrace('local_emdverify: starting run ' . $data->runid);
        (new pipeline())->execute((int) $data->runid);
        mtrace('local_emdverify: finished run ' . $data->runid);
    }

    /**
     * Queue a run for one course week.
     *
     * @param int $courseid Course id.
     * @param int $sectionnum Section number.
     * @param int $userid Who asked for it.
     * @return int The new run id.
     */
    public static function queue(int $courseid, int $sectionnum, int $userid): int {
        global $DB;

        $runid = $DB->insert_record('local_emdverify_run', (object) [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'status' => 'queued',
            'usermodified' => $userid,
            'timequeued' => time(),
        ]);

        $task = new self();
        $task->set_custom_data(['runid' => $runid]);
        $task->set_component('local_emdverify');
        \core\task\manager::queue_adhoc_task($task);

        return $runid;
    }
}
