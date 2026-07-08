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
use mod_flashdeck\scheduler\scheduler;

/**
 * AJAX: submit a four-button self-grade for a card and get the next one.
 *
 * The server computes the new interval; the client only ever sends the
 * grade. The response embeds the next due card so a review costs one
 * round trip.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_review extends external_api {

    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cardid' => new external_value(PARAM_INT, 'Card being graded'),
            'grade' => new external_value(PARAM_INT, 'Self-grade: 0 again, 1 hard, 2 good, 3 easy'),
        ]);
    }

    /**
     * Grade the card, persist the schedule, return the next card payload.
     *
     * @param int $cardid the card id
     * @param int $grade scheduler::GRADE_* value
     * @return array study payload for the next card
     */
    public static function execute(int $cardid, int $grade): array {
        global $DB, $USER, $OUTPUT;

        ['cardid' => $cardid, 'grade' => $grade] = self::validate_parameters(self::execute_parameters(),
            ['cardid' => $cardid, 'grade' => $grade]);

        $card = $DB->get_record('flashdeck_cards', ['id' => $cardid], '*', MUST_EXIST);
        $deck = $DB->get_record('flashdeck', ['id' => $card->deckid], '*', MUST_EXIST);
        [, $cm] = get_course_and_cm_from_instance($deck, 'flashdeck');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/flashdeck:study', $context);

        if ($grade < scheduler::GRADE_AGAIN || $grade > scheduler::GRADE_EASY) {
            throw new \invalid_parameter_exception('grade must be between 0 and 3');
        }

        api::grade_card($deck, $card, $USER->id, $grade, $context);

        return api::export_next_card($deck, $context, $USER->id, $OUTPUT);
    }

    /**
     * Same payload as get_next_due_card.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return get_next_due_card::execute_returns();
    }
}
