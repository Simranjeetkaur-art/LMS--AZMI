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
 * AJAX: per-user progress counts for a deck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_deck_progress extends external_api {

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
     * Count the user's card states in this deck.
     *
     * @param int $flashdeckid the flashdeck instance id
     * @return array progress counts
     */
    public static function execute(int $flashdeckid): array {
        global $DB, $USER;

        ['flashdeckid' => $flashdeckid] = self::validate_parameters(self::execute_parameters(),
            ['flashdeckid' => $flashdeckid]);

        $deck = $DB->get_record('flashdeck', ['id' => $flashdeckid], '*', MUST_EXIST);
        [, $cm] = get_course_and_cm_from_instance($deck, 'flashdeck');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/flashdeck:study', $context);

        return api::get_counts($deck, $USER->id);
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total cards in the deck'),
            'seen' => new external_value(PARAM_INT, 'Cards the user has studied at least once'),
            'new' => new external_value(PARAM_INT, 'Cards never studied'),
            'learning' => new external_value(PARAM_INT, 'Cards in learning or relearning'),
            'review' => new external_value(PARAM_INT, 'Cards graduated to review'),
            'duenow' => new external_value(PARAM_INT, 'Cards due right now'),
            'introducedtoday' => new external_value(PARAM_INT, 'New cards introduced today'),
            'newremaining' => new external_value(PARAM_INT, 'New cards still allowed today'),
        ]);
    }
}
