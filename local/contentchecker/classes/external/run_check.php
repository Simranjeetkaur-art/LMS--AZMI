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

namespace local_contentchecker\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_contentchecker\local\pipeline;
use local_contentchecker\local\queue;
use local_contentchecker\task\verify_course_adhoc;

/**
 * Starts an on-demand verification check.
 *
 * Scope decides how the work runs, because the two scopes differ by an order
 * of magnitude:
 *
 *  - ONE ACTIVITY runs inline, inside this request, holding a GPU slot. It is
 *    a handful of claims and the editor genuinely can wait for it.
 *  - ONE WEEK is queued as an adhoc task and polled. A week is several hundred
 *    model calls; running that inline would hit the PHP time limit and give
 *    the editor a dead spinner instead of an answer.
 *
 * Both return a check id immediately, and the dashboard polls the same status
 * endpoint either way, so the UI does not need to know which path was taken.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_check extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number, or -1', VALUE_DEFAULT, -1),
            'cmid' => new external_value(PARAM_INT, 'Course module id, or 0', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Start the check.
     *
     * @param int $courseid Course id.
     * @param int $sectionnum Section number, or -1.
     * @param int $cmid Course module id, or 0.
     * @return array The new check's id and status.
     */
    public static function execute(int $courseid, int $sectionnum = -1, int $cmid = 0): array {
        global $DB, $USER;

        [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'cmid' => $cmid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'cmid' => $cmid,
        ]);

        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/contentchecker:manage', $context);

        // A course module id from the client is not trusted to belong to the
        // course it was sent with.
        if ($cmid) {
            $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
            if ((int) $cm->course !== $courseid) {
                throw new \moodle_exception('error:cmnotincourse', 'local_contentchecker');
            }
            // Record the activity's OWN week. Storing -1 here would mean "whole
            // course", and the dashboard would then treat a check of one
            // activity as having covered every week -- turning all ten weeks
            // green because somebody verified a single page.
            $sectionnum = (int) $DB->get_field('course_sections', 'section',
                ['id' => $cm->section]);
        }

        $checkid = $DB->insert_record('local_cchecker_checks', (object) [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'cmid' => $cmid,
            'jobid' => 0,
            'runmode' => 'live',
            'status' => 'queued',
            'usermodified' => (int) $USER->id,
            'timequeued' => time(),
        ]);

        if ($cmid) {
            $lock = queue::acquire(0);
            if (!$lock) {
                $DB->delete_records('local_cchecker_checks', ['id' => $checkid]);
                throw new \moodle_exception('error:gpubusy', 'local_contentchecker');
            }
            try {
                \core_php_time_limit::raise(600);
                (new pipeline())->execute($checkid);
            } finally {
                $lock->release();
            }
        } else {
            $task = new verify_course_adhoc();
            $task->set_custom_data(['checkid' => $checkid, 'jobid' => 0]);
            $task->set_userid((int) $USER->id);
            \core\task\manager::queue_adhoc_task($task);
        }

        $check = $DB->get_record('local_cchecker_checks', ['id' => $checkid], '*', MUST_EXIST);

        return [
            'checkid' => $checkid,
            'status' => $check->status,
            'inline' => (bool) $cmid,
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure The return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'checkid' => new external_value(PARAM_INT, 'The new check id'),
            'status' => new external_value(PARAM_ALPHA, 'queued|running|complete|failed'),
            'inline' => new external_value(PARAM_BOOL, 'True when the check already ran'),
        ]);
    }
}
