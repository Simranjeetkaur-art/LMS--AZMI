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
use local_contentchecker\local\audit;
use local_contentchecker\local\questions;

/**
 * Edits, approves, rejects or deletes a follow-up question.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_question extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionid' => new external_value(PARAM_INT, 'Question id'),
            'op' => new external_value(PARAM_ALPHA, 'save|approve|reject|delete'),
            'qtext' => new external_value(PARAM_TEXT, 'Question text', VALUE_DEFAULT, ''),
            'options' => new external_value(PARAM_RAW, 'JSON array of options',
                VALUE_DEFAULT, ''),
            'answer' => new external_value(PARAM_RAW, 'JSON array of correct indexes',
                VALUE_DEFAULT, ''),
            'explanation' => new external_value(PARAM_TEXT, 'Feedback shown to the learner',
                VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Apply the operation.
     *
     * @param int $questionid Question id.
     * @param string $op save|approve|reject|delete.
     * @param string $qtext Question text.
     * @param string $options JSON options array.
     * @param string $answer JSON answer index array.
     * @param string $explanation Learner feedback.
     * @return array The resulting status.
     */
    public static function execute(int $questionid, string $op, string $qtext = '',
            string $options = '', string $answer = '', string $explanation = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'questionid' => $questionid,
            'op' => $op,
            'qtext' => $qtext,
            'options' => $options,
            'answer' => $answer,
            'explanation' => $explanation,
        ]);

        $question = $DB->get_record('local_cchecker_questions',
            ['id' => $params['questionid']], '*', MUST_EXIST);

        $context = \context_course::instance((int) $question->courseid);
        self::validate_context($context);
        require_capability('local/contentchecker:manage', $context);

        switch ($params['op']) {
            case 'delete':
                audit::log('question', (int) $question->id, 'deleted', [
                    'courseid' => (int) $question->courseid,
                    'cmid' => (int) $question->cmid,
                    'before' => $question->qtext,
                ]);
                $DB->delete_records('local_cchecker_questions', ['id' => $question->id]);
                return ['status' => 'deleted'];

            case 'approve':
                questions::set_status((int) $question->id, 'approved');
                return ['status' => 'approved'];

            case 'reject':
                questions::set_status((int) $question->id, 'rejected');
                return ['status' => 'rejected'];

            case 'save':
                $before = $question->qtext;
                $decodedoptions = json_decode($params['options'], true);
                $decodedanswer = json_decode($params['answer'], true);

                if ($params['qtext'] !== '') {
                    $question->qtext = $params['qtext'];
                }
                if (is_array($decodedoptions)) {
                    $question->options = json_encode(array_values(
                        array_map('strval', $decodedoptions)));
                }
                if (is_array($decodedanswer)) {
                    // An answer index past the end of the options list would
                    // tell a learner a correct answer is wrong, so it is
                    // dropped rather than stored.
                    $count = count(json_decode($question->options, true) ?: []);
                    $question->answer = json_encode(array_values(array_filter(
                        array_map('intval', $decodedanswer),
                        fn($i) => $i >= 0 && $i < $count)));
                }
                $question->explanation = $params['explanation'];
                $question->usermodified = (int) $USER->id;
                $question->timemodified = time();
                $DB->update_record('local_cchecker_questions', $question);

                audit::log('question', (int) $question->id, 'updated', [
                    'courseid' => (int) $question->courseid,
                    'cmid' => (int) $question->cmid,
                    'before' => $before,
                    'after' => $question->qtext,
                ]);
                return ['status' => $question->status];

            default:
                throw new \moodle_exception('error:unknownop', 'local_contentchecker');
        }
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure The return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'The question status after the change'),
        ]);
    }
}
