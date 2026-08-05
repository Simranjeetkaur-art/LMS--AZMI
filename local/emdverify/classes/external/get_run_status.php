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

namespace local_emdverify\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Poll a run's progress so the ledger page can update itself.
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_run_status extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters Parameter definition.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'runid' => new external_value(PARAM_INT, 'Run id'),
        ]);
    }

    /**
     * Report status.
     *
     * @param int $runid Run id.
     * @return array Status payload.
     */
    public static function execute(int $runid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['runid' => $runid]);
        $run = $DB->get_record('local_emdverify_run', ['id' => $params['runid']],
            '*', MUST_EXIST);

        $context = \context_course::instance($run->courseid);
        self::validate_context($context);
        require_capability('local/emdverify:view', $context);

        return [
            'status' => $run->status,
            'progress' => (int) $run->progress,
            'numclaims' => (int) $run->numclaims,
            'numflagged' => (int) $run->numflagged,
            'errormsg' => (string) ($run->errormsg ?? ''),
        ];
    }

    /**
     * Return description.
     *
     * @return external_single_structure Return definition.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'queued, running, complete or failed'),
            'progress' => new external_value(PARAM_INT, 'Percent complete'),
            'numclaims' => new external_value(PARAM_INT, 'Claims extracted'),
            'numflagged' => new external_value(PARAM_INT, 'Claims needing attention'),
            'errormsg' => new external_value(PARAM_TEXT, 'Failure message, if any'),
        ]);
    }
}
