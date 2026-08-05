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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_contentchecker\local\pipeline;

/**
 * Polls a check for progress, returning the structured result once it is done.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_check_status extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'checkid' => new external_value(PARAM_INT, 'Check id'),
        ]);
    }

    /**
     * Return the check's current state.
     *
     * @param int $checkid Check id.
     * @return array Status, progress and the structured result.
     */
    public static function execute(int $checkid): array {
        global $DB;

        ['checkid' => $checkid] = self::validate_parameters(self::execute_parameters(),
            ['checkid' => $checkid]);

        $check = $DB->get_record('local_cchecker_checks', ['id' => $checkid], '*', MUST_EXIST);

        // Gated on the course the check belongs to, not on anything the caller
        // supplied alongside the id.
        $context = \context_course::instance((int) $check->courseid);
        self::validate_context($context);
        require_capability('local/contentchecker:view', $context);

        $result = pipeline::result_for($checkid);

        return [
            'checkid' => $checkid,
            'status' => $check->status,
            'progress' => (int) $check->progress,
            'numflagged' => (int) $check->numflagged,
            'errormsg' => $check->errormsg,
            'result' => [
                'status' => $result['status'],
                'issues' => array_map(fn($issue) => [
                    'id' => $issue['id'],
                    'claim' => $issue['claim'],
                    'current_text' => (string) $issue['current_text'],
                    'suggested_text' => (string) $issue['suggested_text'],
                    'source_reference' => (string) $issue['source_reference'],
                    'confidence' => $issue['confidence'],
                    'verdict' => $issue['verdict'],
                    'decision' => $issue['decision'],
                ], $result['issues']),
                'sources_checked' => array_map(fn($source) => [
                    'title' => (string) $source['title'],
                    'url' => (string) $source['url'],
                    'tier' => $source['tier'],
                ], $result['sources_checked']),
            ],
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure The return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'checkid' => new external_value(PARAM_INT, 'Check id'),
            'status' => new external_value(PARAM_ALPHA, 'queued|running|complete|failed'),
            'progress' => new external_value(PARAM_INT, 'Percent complete'),
            'numflagged' => new external_value(PARAM_INT, 'Items flagged for review'),
            'errormsg' => new external_value(PARAM_RAW, 'Failure detail', VALUE_OPTIONAL),
            'result' => new external_single_structure([
                'status' => new external_value(PARAM_ALPHANUMEXT, 'ok|needs_review|error|queued|running'),
                'issues' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Suggestion id'),
                    'claim' => new external_value(PARAM_RAW, 'The claim judged'),
                    'current_text' => new external_value(PARAM_RAW, 'Live text, left of the diff'),
                    'suggested_text' => new external_value(PARAM_RAW, 'Proposed text, right of the diff'),
                    'source_reference' => new external_value(PARAM_RAW, 'Citation for the suggestion'),
                    'confidence' => new external_value(PARAM_FLOAT, 'Model confidence, for inspection only'),
                    'verdict' => new external_value(PARAM_ALPHAEXT, 'The machine verdict'),
                    'decision' => new external_value(PARAM_ALPHA, 'pending|approved|rejected|edited'),
                ])),
                'sources_checked' => new external_multiple_structure(new external_single_structure([
                    'title' => new external_value(PARAM_RAW, 'Source title'),
                    'url' => new external_value(PARAM_RAW, 'Source URL'),
                    'tier' => new external_value(PARAM_INT, '1 textbook, 2 guideline, 3 open'),
                ])),
            ]),
        ]);
    }
}
