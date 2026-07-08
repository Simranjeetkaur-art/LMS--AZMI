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

/**
 * Data generator for mod_flashdeck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates flashdeck instances and cards for testing.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_flashdeck_generator extends testing_module_generator {

    #[\Override]
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        if (!isset($record->scheduler)) {
            $record->scheduler = 'sm2';
        }
        if (!isset($record->newperday)) {
            $record->newperday = 20;
        }

        return parent::create_instance($record, $options);
    }

    /**
     * Create a card in a deck.
     *
     * Accepts either a ready 'content' payload (array or JSON string) or,
     * for basic cards, plain 'front'/'back' shortcuts (used from Behat).
     *
     * @param array|stdClass|null $record card fields; 'deckid' is required
     * @return stdClass the created flashdeck_cards record
     */
    public function create_card($record = null): stdClass {
        global $DB;

        $record = (array) $record;
        if (empty($record['deckid'])) {
            throw new coding_exception('create_card() requires a deckid');
        }

        $cardtype = $record['cardtype'] ?? 'basic';
        if (!\mod_flashdeck\cardtype\manager::exists($cardtype)) {
            throw new coding_exception("Unknown card type '{$cardtype}'");
        }

        $content = $record['content'] ?? null;
        if (is_string($content)) {
            $content = json_decode($content, true);
        }
        if ($content === null) {
            $content = [
                'front' => $record['front'] ?? '<p>Front of card</p>',
                'frontformat' => FORMAT_HTML,
                'back' => $record['back'] ?? '<p>Back of card</p>',
                'backformat' => FORMAT_HTML,
            ];
        }

        if ($problems = \mod_flashdeck\cardtype\manager::get($cardtype)->validate_content($content)) {
            throw new coding_exception('Invalid card content: ' . implode('; ', $problems));
        }

        $now = time();
        $card = (object) [
            'deckid' => (int) $record['deckid'],
            'cardtype' => $cardtype,
            'position' => $record['position'] ?? 1 + (int) $DB->get_field_sql(
                'SELECT COALESCE(MAX(position), 0) FROM {flashdeck_cards} WHERE deckid = ?', [$record['deckid']]),
            'tags' => $record['tags'] ?? null,
            'content' => json_encode($content),
            'usermodified' => $record['usermodified'] ?? 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $card->id = $DB->insert_record('flashdeck_cards', $card);

        return $card;
    }
}
