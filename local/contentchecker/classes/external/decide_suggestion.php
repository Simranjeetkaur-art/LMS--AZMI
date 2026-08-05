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
use local_contentchecker\local\decision;

/**
 * Records a human decision on an AI suggestion.
 *
 * This is the only route by which a model suggestion can change live course
 * content, and it cannot be reached without a named user holding
 * local/contentchecker:approve in the course the content lives in.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class decide_suggestion extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'suggestionid' => new external_value(PARAM_INT, 'Suggestion id'),
            'action' => new external_value(PARAM_ALPHA, 'approve|reject|edit'),
            'edited' => new external_value(PARAM_TEXT, 'Replacement text for edit',
                VALUE_DEFAULT, ''),
            'notes' => new external_value(PARAM_TEXT, 'Reviewer note', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Record the decision.
     *
     * @param int $suggestionid Suggestion id.
     * @param string $action approve|reject|edit.
     * @param string $edited Replacement text.
     * @param string $notes Reviewer note.
     * @return array The outcome.
     */
    public static function execute(int $suggestionid, string $action,
            string $edited = '', string $notes = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'suggestionid' => $suggestionid,
            'action' => $action,
            'edited' => $edited,
            'notes' => $notes,
        ]);

        // The context comes from the suggestion's own course, never from
        // anything the caller supplied alongside the id.
        global $DB;
        $courseid = $DB->get_field_sql(
            'SELECT ch.courseid
               FROM {local_cchecker_suggestions} s
               JOIN {local_cchecker_checks} ch ON ch.id = s.checkid
              WHERE s.id = :id',
            ['id' => $params['suggestionid']], MUST_EXIST);

        $context = \context_course::instance((int) $courseid);
        self::validate_context($context);

        // decision::record() enforces this too, and that is the check that
        // actually guards the write. Repeating it here is deliberate: the
        // requirement is that every endpoint gates itself, so a future
        // refactor of decision::record cannot silently un-gate this one.
        require_capability('local/contentchecker:approve', $context);

        $result = decision::record(
            $params['suggestionid'],
            $params['action'],
            $params['edited'] !== '' ? $params['edited'] : null,
            $params['notes'] !== '' ? $params['notes'] : null
        );

        return [
            'ok' => $result['ok'],
            'decision' => $result['decision'],
            'applied' => $result['applied'],
            'message' => $result['reason']
                ? get_string($result['reason'], 'local_contentchecker')
                : get_string('apply:done', 'local_contentchecker'),
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure The return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Was the decision recorded'),
            'decision' => new external_value(PARAM_ALPHA, 'pending|approved|rejected|edited'),
            'applied' => new external_value(PARAM_BOOL, 'Did live content change'),
            'message' => new external_value(PARAM_TEXT, 'Outcome for the editor'),
        ]);
    }
}
