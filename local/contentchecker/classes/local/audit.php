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

defined('MOODLE_INTERNAL') || die();

/**
 * The queryable audit trail.
 *
 * Every AI suggestion and every human decision on it is recorded here with the
 * user, the timestamp and the before/after text. This is deliberately a plain
 * table rather than only a Moodle event: standard logstores are subject to
 * retention policies and can be pruned, and an accreditation record of who
 * changed which piece of clinical content must outlive that.
 *
 * Moodle events are also fired alongside, so the usual reports still work.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit {

    /**
     * Record one auditable action.
     *
     * @param string $objecttype suggestion|question|template|asset|pronounce.
     * @param int $objectid Row id of the object.
     * @param string $action What happened.
     * @param array $options courseid, cmid, before, after, detail, userid.
     * @return int The audit row id.
     */
    public static function log(string $objecttype, int $objectid, string $action,
            array $options = []): int {
        global $DB, $USER;

        return $DB->insert_record('local_cchecker_audit', (object) [
            'courseid' => (int) ($options['courseid'] ?? 0),
            'cmid' => (int) ($options['cmid'] ?? 0),
            'objecttype' => $objecttype,
            'objectid' => $objectid,
            'action' => $action,
            'beforetext' => $options['before'] ?? null,
            'aftertext' => $options['after'] ?? null,
            'detail' => isset($options['detail']) ? json_encode($options['detail']) : null,
            'userid' => (int) ($options['userid'] ?? $USER->id),
            'timecreated' => time(),
        ]);
    }

    /**
     * The audit trail for one object, newest first.
     *
     * @param string $objecttype Object type.
     * @param int $objectid Object id.
     * @return array Audit rows joined to the acting user's name.
     */
    public static function history(string $objecttype, int $objectid): array {
        global $DB;

        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        return $DB->get_records_sql(
            "SELECT a.*, {$namefields}
               FROM {local_cchecker_audit} a
               JOIN {user} u ON u.id = a.userid
              WHERE a.objecttype = :objecttype AND a.objectid = :objectid
           ORDER BY a.timecreated DESC, a.id DESC",
            ['objecttype' => $objecttype, 'objectid' => $objectid]);
    }

    /**
     * The audit trail for a course, newest first.
     *
     * @param int $courseid Course id.
     * @param int $limit Maximum rows.
     * @return array Audit rows joined to the acting user's name.
     */
    public static function for_course(int $courseid, int $limit = 200): array {
        global $DB;

        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        return $DB->get_records_sql(
            "SELECT a.*, {$namefields}
               FROM {local_cchecker_audit} a
               JOIN {user} u ON u.id = a.userid
              WHERE a.courseid = :courseid
           ORDER BY a.timecreated DESC, a.id DESC",
            ['courseid' => $courseid], 0, $limit);
    }
}
