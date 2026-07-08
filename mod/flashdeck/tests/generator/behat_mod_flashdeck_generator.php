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
 * Behat data generator for mod_flashdeck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_flashdeck_generator extends behat_generator_base {

    #[\Override]
    protected function get_creatable_entities(): array {
        return [
            'cards' => [
                'singular' => 'card',
                'datagenerator' => 'card',
                'required' => ['flashdeck', 'cardtype'],
                'switchids' => ['flashdeck' => 'deckid'],
            ],
        ];
    }

    /**
     * Resolve a flashdeck instance id from its name.
     *
     * @param string $name the deck (activity) name
     * @return int the flashdeck id
     */
    protected function get_flashdeck_id(string $name): int {
        global $DB;

        if (!$id = $DB->get_field('flashdeck', 'id', ['name' => $name])) {
            throw new Exception("Unknown flashdeck deck '{$name}'");
        }
        return (int) $id;
    }
}
