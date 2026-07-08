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

namespace mod_flashdeck\completion;

use core_completion\activity_custom_completion;

/**
 * Custom completion rules: cards studied and mastery reached.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    #[\Override]
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);
        $deckid = $this->cm->instance;
        $required = (int) $this->cm->customdata['customcompletionrules'][$rule];

        if ($rule === 'completionstudied') {
            $studied = $DB->count_records('flashdeck_review',
                ['deckid' => $deckid, 'userid' => $this->userid]);
            return ($studied >= $required) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        // Mastery: graduated cards as a percentage of the whole deck.
        $total = $DB->count_records('flashdeck_cards', ['deckid' => $deckid]);
        if (!$total) {
            return COMPLETION_INCOMPLETE;
        }
        $graduated = $DB->count_records('flashdeck_review',
            ['deckid' => $deckid, 'userid' => $this->userid, 'state' => 'review']);

        return (100 * $graduated / $total >= $required) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    #[\Override]
    public static function get_defined_custom_rules(): array {
        return ['completionstudied', 'completionmastery'];
    }

    #[\Override]
    public function get_custom_rule_descriptions(): array {
        $studied = (int) ($this->cm->customdata['customcompletionrules']['completionstudied'] ?? 0);
        $mastery = (int) ($this->cm->customdata['customcompletionrules']['completionmastery'] ?? 0);

        return [
            'completionstudied' => get_string('completiondetail:studied', 'mod_flashdeck', $studied),
            'completionmastery' => get_string('completiondetail:mastery', 'mod_flashdeck', $mastery),
        ];
    }

    #[\Override]
    public function get_sort_order(): array {
        return ['completionview', 'completionstudied', 'completionmastery'];
    }
}
