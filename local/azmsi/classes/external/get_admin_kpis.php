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
use core_external\external_value;

/**
 * WS: admin console KPIs.
 *
 * Justification: returns a pre-aggregated KPI rollup (active students,
 * applications, faculty, courses built X/48) computed by the scheduled
 * rollup_admin_kpis task and stored in cache_azmsi. No single core call returns
 * this composed figure set; reading the cache keeps the request O(1).
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_admin_kpis extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return the cached KPI rollup (zeros until the task has run once).
     *
     * @return array
     */
    public static function execute(): array {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/azmsi:ws_admin', $context);

        $cache = \cache::make('local_azmsi', 'rollups');
        $kpis = $cache->get('admin_kpis');

        return [
            'activestudents' => (int) ($kpis['activestudents'] ?? 0),
            'applications'   => (int) ($kpis['applications'] ?? 0),
            'faculty'        => (int) ($kpis['faculty'] ?? 0),
            'coursesbuilt'   => (int) ($kpis['coursesbuilt'] ?? 0),
            'coursestotal'   => (int) ($kpis['coursestotal'] ?? 48),
            'generatedon'    => (int) ($kpis['generatedon'] ?? 0),
        ];
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'activestudents' => new external_value(PARAM_INT, 'Active enrolled students'),
            'applications'   => new external_value(PARAM_INT, 'Open admissions applications'),
            'faculty'        => new external_value(PARAM_INT, 'Faculty members'),
            'coursesbuilt'   => new external_value(PARAM_INT, 'Courses built (live)'),
            'coursestotal'   => new external_value(PARAM_INT, 'Total planned courses'),
            'generatedon'    => new external_value(PARAM_INT, 'Unix time the rollup was generated (0 = never)'),
        ]);
    }
}
