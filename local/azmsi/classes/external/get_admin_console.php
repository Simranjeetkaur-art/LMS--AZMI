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
use local_azmsi\local\admin;
use local_azmsi\output\admin_console;

/**
 * WS: live admin-console data region.
 *
 * Justification: the admin console page is served from the cron/event-driven
 * rollup in cache_azmsi. This function lets the open page poll for the freshest
 * rollup and swap the data widgets in place without a full reload. It is an O(1)
 * cache read rendered through the shared local_azmsi/admin_console_live template,
 * so the AJAX response is byte-for-byte the same markup the page rendered on load.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_admin_console extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Render the live data region from the cached rollup.
     *
     * @return array{html:string,generatedon:int}
     */
    public static function execute(): array {
        global $OUTPUT;

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/azmsi:viewadminconsole', $context);

        $data = admin::console();
        $live = admin_console::live_from($data);
        $html = $OUTPUT->render_from_template('local_azmsi/admin_console_live', $live);

        return [
            'html'        => $html,
            'generatedon' => (int) ($data['generatedon'] ?? 0),
        ];
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html'        => new external_value(PARAM_RAW, 'Rendered live data-widget region HTML'),
            'generatedon' => new external_value(PARAM_INT, 'Unix time the rollup was generated (0 = never)'),
        ]);
    }
}
