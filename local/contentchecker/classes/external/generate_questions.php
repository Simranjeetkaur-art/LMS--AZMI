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

namespace local_contentchecker\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_contentchecker\local\queue;
use local_contentchecker\local\questions;

/**
 * Generates draft follow-up questions for one activity.
 *
 * Everything produced here lands as a draft. Nothing reaches a learner until
 * an editor approves it.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_questions extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'replace' => new external_value(PARAM_BOOL, 'Discard existing drafts first',
                VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Generate the questions.
     *
     * @param int $cmid Course module id.
     * @param bool $replace Whether to discard existing drafts.
     * @return array How many were created.
     */
    public static function execute(int $cmid, bool $replace = false): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'replace' => $replace,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid']);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/contentchecker:manage',
            \context_course::instance($course->id));

        if (!get_config('local_contentchecker', 'questions_enabled')) {
            throw new \moodle_exception('error:questionsdisabled', 'local_contentchecker');
        }

        $created = queue::with_slot(function() use ($params) {
            \core_php_time_limit::raise(600);
            return (new questions())->generate_for_cm($params['cmid'], $params['replace']);
        });

        return ['created' => $created];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure The return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'created' => new external_value(PARAM_INT, 'Draft questions created'),
        ]);
    }
}
