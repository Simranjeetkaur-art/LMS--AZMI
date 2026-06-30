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

namespace local_azmsi\local;

/**
 * Site-wide role capability adjustments for AZMSI policy.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class role_policy {

    /**
     * Calendar caps students must not hold.
     *
     * Every logged-in user also carries the authenticated-user role, which
     * grants moodle/calendar:manageownentries by default. CAP_PROHIBIT on the
     * student role overrides that inheritance so students can view calendar
     * events but cannot create personal or manual entries.
     *
     * Faculty (teacher/editingteacher), instructors and admins retain create
     * access via their role archetypes (manageentries / managegroupentries /
     * manageownentries).
     */
    private const STUDENT_CALENDAR_PROHIBIT = [
        'moodle/calendar:manageownentries',
    ];

    /**
     * Apply AZMSI calendar create/view policy to core roles.
     *
     * Idempotent — safe to call from upgrade.php.
     *
     * @return void
     */
    public static function apply_calendar_policy(): void {
        global $DB;

        $systemcontext = \context_system::instance();
        $studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

        foreach (self::STUDENT_CALENDAR_PROHIBIT as $capability) {
            assign_capability($capability, CAP_PROHIBIT, $studentroleid, $systemcontext->id, true);
        }
    }
}
