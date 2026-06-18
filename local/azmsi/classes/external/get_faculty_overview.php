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

namespace local_azmsi\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * WS: faculty dashboard overview.
 *
 * Justification (AGENT_03 rule): composes, in one round-trip, figures that no
 * single core WS returns together — courses taught with live student counts +
 * gradebook class average, the cross-course ungraded-submission queue, the agenda,
 * and class-health rollups. Reuses \local_azmsi\local\faculty so the in-LMS page
 * and the WS share one computation.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_faculty_overview extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Teacher user id (0 = current user)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Return the faculty overview.
     *
     * @param int $userid
     * @return array
     */
    public static function execute(int $userid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['userid' => $userid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/azmsi:viewfacultyportal', $context);

        $targetid = $params['userid'] ?: $USER->id;
        $data = \local_azmsi\local\faculty::overview_for($targetid);

        return [
            'coursecount'  => (int) $data['coursecount'],
            'studenttotal' => (int) $data['studenttotal'],
            'queuetotal'   => (int) $data['queuetotal'],
            'ontracktotal' => (int) $data['ontracktotal'],
            'atrisktotal'  => (int) $data['atrisktotal'],
            'courses'      => array_map(static fn($c) => [
                'id'       => (int) $c['id'],
                'name'     => $c['name'],
                'code'     => $c['code'],
                'students' => (int) $c['students'],
                'ungraded' => (int) $c['ungraded'],
                'classavg' => (float) ($c['classavg'] ?? 0),
            ], $data['courses']),
        ];
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'coursecount'  => new external_value(PARAM_INT, 'Number of courses taught'),
            'studenttotal' => new external_value(PARAM_INT, 'Total students across taught courses'),
            'queuetotal'   => new external_value(PARAM_INT, 'Total ungraded submissions awaiting grading'),
            'ontracktotal' => new external_value(PARAM_INT, 'Students on track (>=60% course grade)'),
            'atrisktotal'  => new external_value(PARAM_INT, 'At-risk students (<60% course grade)'),
            'courses'      => new external_multiple_structure(
                new external_single_structure([
                    'id'       => new external_value(PARAM_INT, 'Course id'),
                    'name'     => new external_value(PARAM_TEXT, 'Course name'),
                    'code'     => new external_value(PARAM_TEXT, 'Course code (idnumber)'),
                    'students' => new external_value(PARAM_INT, 'Enrolled student count'),
                    'ungraded' => new external_value(PARAM_INT, 'Ungraded submissions in this course'),
                    'classavg' => new external_value(PARAM_FLOAT, 'Class average percent (0 = none)'),
                ])
            ),
        ]);
    }
}
