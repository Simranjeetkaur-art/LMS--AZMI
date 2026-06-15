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
 * WS: student dashboard rollup.
 *
 * Justification: composes core_enrol_get_users_courses + grades + completion +
 * calendar into one payload for the dashboard (S4). Cached in cache_azmsi.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_student_overview extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Target user id (0 = current user)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Return the dashboard rollup.
     *
     * @param int $userid
     * @return array
     */
    public static function execute(int $userid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['userid' => $userid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/azmsi:ws_student', $context);

        $targetid = $params['userid'] ?: $USER->id;

        // Shared composition (cached in cache_azmsi, invalidated by observers/tasks).
        $data = \local_azmsi\local\overview::for_user($targetid);

        // Return the typed subset this WS declares; the block consumes the full set.
        return [
            'fullname' => $data['fullname'],
            'average'  => (float) $data['average'],
            'courses'  => array_map(static fn($c) => [
                'id'       => (int) $c['id'],
                'name'     => $c['name'],
                'progress' => (int) $c['progress'],
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
            'fullname' => new external_value(PARAM_TEXT, 'Student full name'),
            'average'  => new external_value(PARAM_FLOAT, 'Current overall average percent'),
            'courses'  => new external_multiple_structure(
                new external_single_structure([
                    'id'       => new external_value(PARAM_INT, 'Course id'),
                    'name'     => new external_value(PARAM_TEXT, 'Course name'),
                    'progress' => new external_value(PARAM_INT, 'Completion percent'),
                ])
            ),
        ]);
    }
}
