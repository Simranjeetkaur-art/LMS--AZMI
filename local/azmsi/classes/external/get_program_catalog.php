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
 * WS: full program catalog (Year -> Quarter -> Course tree).
 *
 * Justification (per AGENT_03 rule): no single core function returns the
 * composed Year/Quarter/Course tree with per-quarter live/planned status; this
 * aggregates course custom fields into one round-trip for the website + portal.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_program_catalog extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return the program catalog tree.
     *
     * @return array
     */
    public static function execute(): array {
        $context = \context_system::instance();
        self::validate_context($context);

        // TODO (AGENT_03): read course custom fields (year/quarter/credits/status)
        // and build the tree. Return empty structure for now — nothing hardcoded.
        return [
            'program' => 'eMD',
            'years'   => [],
        ];
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'program' => new external_value(PARAM_TEXT, 'Program short name, e.g. eMD'),
            'years'   => new external_multiple_structure(
                new external_single_structure([
                    'name'     => new external_value(PARAM_TEXT, 'Year title'),
                    'quarters' => new external_multiple_structure(
                        new external_single_structure([
                            'name'    => new external_value(PARAM_TEXT, 'Quarter title'),
                            'status'  => new external_value(PARAM_ALPHAEXT, 'in_progress|planned'),
                            'courses' => new external_multiple_structure(
                                new external_single_structure([
                                    'code'    => new external_value(PARAM_TEXT, 'Course code, e.g. EMD-101'),
                                    'name'    => new external_value(PARAM_TEXT, 'Course name'),
                                    'credits' => new external_value(PARAM_INT, 'Credit count'),
                                    'status'  => new external_value(PARAM_ALPHAEXT, 'in_progress|planned'),
                                ])
                            ),
                        ])
                    ),
                ])
            ),
        ]);
    }
}
