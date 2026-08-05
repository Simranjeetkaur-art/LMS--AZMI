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
use local_emdverify\local\ledger;

/**
 * Record a reviewer's decision on a claim.
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_review extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters Parameter definition.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'claimid' => new external_value(PARAM_INT, 'Claim id'),
            'decision' => new external_value(PARAM_ALPHA, 'agree, disagree or unsure'),
            'notes' => new external_value(PARAM_TEXT, 'Optional notes', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Save the decision.
     *
     * @param int $claimid Claim id.
     * @param string $decision agree|disagree|unsure
     * @param string $notes Optional notes.
     * @return array Result.
     */
    public static function execute(int $claimid, string $decision, string $notes = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(),
            ['claimid' => $claimid, 'decision' => $decision, 'notes' => $notes]);

        if (!in_array($params['decision'], ['agree', 'disagree', 'unsure'], true)) {
            throw new \invalid_parameter_exception('Unknown decision');
        }

        $sql = "SELECT r.courseid
                  FROM {local_emdverify_claim} c
                  JOIN {local_emdverify_run} r ON r.id = c.runid
                 WHERE c.id = :claimid";
        $courseid = $DB->get_field_sql($sql, ['claimid' => $params['claimid']]);
        if (!$courseid) {
            throw new \moodle_exception('invalidrecord', 'error');
        }

        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/emdverify:adjudicate', $context);

        ledger::save_review($params['claimid'], $USER->id,
            $params['decision'], $params['notes']);

        return ['status' => true, 'message' => get_string('reviewsaved', 'local_emdverify')];
    }

    /**
     * Return description.
     *
     * @return external_single_structure Return definition.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether the decision was saved'),
            'message' => new external_value(PARAM_TEXT, 'Confirmation message'),
        ]);
    }
}
