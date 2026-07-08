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

namespace mod_flashdeck\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_flashdeck\local\api;

/**
 * AJAX: fetch the next due card (server-scheduled) for the current user.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_next_due_card extends external_api {

    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'flashdeckid' => new external_value(PARAM_INT, 'Flashdeck instance id'),
        ]);
    }

    /**
     * Fetch the next due card and queue counts.
     *
     * @param int $flashdeckid the flashdeck instance id
     * @return array study payload
     */
    public static function execute(int $flashdeckid): array {
        global $DB, $USER, $OUTPUT;

        ['flashdeckid' => $flashdeckid] = self::validate_parameters(self::execute_parameters(),
            ['flashdeckid' => $flashdeckid]);

        $deck = $DB->get_record('flashdeck', ['id' => $flashdeckid], '*', MUST_EXIST);
        [, $cm] = get_course_and_cm_from_instance($deck, 'flashdeck');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/flashdeck:study', $context);

        return api::export_next_card($deck, $context, $USER->id, $OUTPUT);
    }

    /**
     * Return description, shared with submit_review.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'done' => new external_value(PARAM_BOOL, 'True when nothing is due right now'),
            'cardid' => new external_value(PARAM_INT, 'Card id', VALUE_OPTIONAL),
            'cardtype' => new external_value(PARAM_ALPHANUMEXT, 'Card type identifier', VALUE_OPTIONAL),
            'state' => new external_value(PARAM_ALPHA, 'Review state of the served card', VALUE_OPTIONAL),
            'cardhtml' => new external_value(PARAM_RAW, 'Server-rendered card faces', VALUE_OPTIONAL),
            'previews' => new external_single_structure([
                'again' => new external_value(PARAM_TEXT, 'Interval preview for Again'),
                'hard' => new external_value(PARAM_TEXT, 'Interval preview for Hard'),
                'good' => new external_value(PARAM_TEXT, 'Interval preview for Good'),
                'easy' => new external_value(PARAM_TEXT, 'Interval preview for Easy'),
            ], 'Interval each grade would schedule', VALUE_OPTIONAL),
            'counts' => new external_single_structure([
                'duenow' => new external_value(PARAM_INT, 'Cards due now'),
                'learning' => new external_value(PARAM_INT, 'Cards in learning/relearning'),
                'newremaining' => new external_value(PARAM_INT, 'New cards still allowed today'),
                'total' => new external_value(PARAM_INT, 'Total cards in the deck'),
            ], 'Queue counts'),
            'mastery' => new external_value(PARAM_INT, 'Mastery percentage (graduated / total)'),
            'graduated' => new external_value(PARAM_INT, 'Cards graduated to review'),
            'streak' => new external_value(PARAM_INT, 'Consecutive study days'),
            'points' => new external_value(PARAM_INT, 'Total points earned in this deck'),
            'nextdue' => new external_value(PARAM_INT, 'Timestamp of the next scheduled review, 0 if none'),
            'nextduelabel' => new external_value(PARAM_TEXT, 'Formatted next review time, empty if none'),
        ]);
    }
}
