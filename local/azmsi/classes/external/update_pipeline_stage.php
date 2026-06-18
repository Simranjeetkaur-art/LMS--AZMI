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
use local_azmsi\local\pipeline;

/**
 * WS (write): advance a course's production-pipeline stage.
 *
 * Justification (AGENT_03 rule): a single audited write that both records the
 * stage in local_azmsi_pipeline AND, on launch, flips the course status custom
 * field and fires the website revalidation — no core call does this composed
 * side effect. Capability-checked; sesskey is enforced by the ajax/token
 * transport, and the in-LMS console POST path adds require_sesskey().
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_pipeline_stage extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'stage'    => new external_value(PARAM_ALPHAEXT, 'Pipeline stage column (one of the six stage_* columns)'),
            'value'    => new external_value(PARAM_ALPHA, 'Stage value: queued|active|done'),
        ]);
    }

    /**
     * Update the stage (capability-gated, audited).
     *
     * @param int $courseid
     * @param string $stage
     * @param string $value
     * @return array
     */
    public static function execute(int $courseid, string $stage, string $value): array {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid, 'stage' => $stage, 'value' => $value]
        );

        $context = \core\context\course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/azmsi:managepipeline', $context);

        if (!pipeline::is_stage($params['stage']) || !pipeline::is_value($params['value'])) {
            throw new \invalid_parameter_exception('Invalid pipeline stage or value.');
        }

        $row = pipeline::set_stage($params['courseid'], $params['stage'], $params['value'], (int) $USER->id);

        return [
            'courseid' => (int) $row->courseid,
            'stage'    => $params['stage'],
            'value'    => $params['value'],
            'success'  => true,
        ];
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'stage'    => new external_value(PARAM_ALPHAEXT, 'Stage column updated'),
            'value'    => new external_value(PARAM_ALPHA, 'New stage value'),
            'success'  => new external_value(PARAM_BOOL, 'Whether the write succeeded'),
        ]);
    }
}
