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

namespace local_contentchecker;

defined('MOODLE_INTERNAL') || die();

/**
 * Keeps plugin data in step with the courses and activities it describes.
 *
 * Without this, deleting a course leaves its checks, suggestions, evidence,
 * questions and layout choices behind forever. That is not merely untidy:
 * suggestions store verbatim course text, so orphaned rows are content that
 * outlives the course it belonged to, and the site-wide report would join
 * against courses that no longer exist.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Remove everything this plugin stored about a deleted course.
     *
     * @param \core\event\course_deleted $event The event.
     * @return void
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        self::purge_course((int) $event->objectid);
    }

    /**
     * Remove everything this plugin stored about a deleted activity.
     *
     * A course module can be deleted without its course going anywhere, and its
     * suggestions and questions are meaningless once the content is gone.
     *
     * @param \core\event\course_module_deleted $event The event.
     * @return void
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;

        $cmid = (int) $event->objectid;

        // Evidence hangs off suggestions, so it goes first or it is orphaned
        // in turn.
        $suggestionids = $DB->get_fieldset_select('local_cchecker_suggestions', 'id',
            'cmid = :cmid', ['cmid' => $cmid]);
        if ($suggestionids) {
            [$insql, $params] = $DB->get_in_or_equal($suggestionids, SQL_PARAMS_NAMED, 's');
            $DB->delete_records_select('local_cchecker_evidence', "suggestionid {$insql}", $params);
            $DB->delete_records_select('local_cchecker_suggestions', "id {$insql}", $params);
        }

        $DB->delete_records('local_cchecker_questions', ['cmid' => $cmid]);
        $DB->delete_records('local_cchecker_templates', ['cmid' => $cmid]);
        $DB->delete_records('local_cchecker_checks', ['cmid' => $cmid]);

        // The audit trail is deliberately NOT deleted here. It records who
        // changed which clinical sentence and when, which is an accreditation
        // record that has to outlive the activity; the privacy provider is what
        // unlinks it from a person on request.
    }

    /**
     * Delete all plugin data for one course.
     *
     * Public so the cleanup task and tests can reuse it.
     *
     * @param int $courseid The course.
     * @return void
     */
    public static function purge_course(int $courseid): void {
        global $DB;

        $checkids = $DB->get_fieldset_select('local_cchecker_checks', 'id',
            'courseid = :courseid', ['courseid' => $courseid]);

        if ($checkids) {
            [$insql, $params] = $DB->get_in_or_equal($checkids, SQL_PARAMS_NAMED, 'c');

            $suggestionids = $DB->get_fieldset_select('local_cchecker_suggestions', 'id',
                "checkid {$insql}", $params);
            if ($suggestionids) {
                [$ssql, $sparams] = $DB->get_in_or_equal($suggestionids, SQL_PARAMS_NAMED, 's');
                $DB->delete_records_select('local_cchecker_evidence',
                    "suggestionid {$ssql}", $sparams);
                $DB->delete_records_select('local_cchecker_suggestions',
                    "id {$ssql}", $sparams);
            }

            $DB->delete_records_select('local_cchecker_checks', "id {$insql}", $params);
        }

        $DB->delete_records('local_cchecker_questions', ['courseid' => $courseid]);
        $DB->delete_records('local_cchecker_templates', ['courseid' => $courseid]);

        // Audit rows are kept for the reason given above.
    }
}
