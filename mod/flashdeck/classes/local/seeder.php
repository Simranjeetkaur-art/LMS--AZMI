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

namespace mod_flashdeck\local;

/**
 * Loads the bundled sample deck into a flashdeck instance.
 *
 * The sample (EMD-101 Week 1 medical terminology) exists so a fresh
 * install is testable out of the box and demonstrates the flagship
 * term-dissection interaction. It appends; existing cards are kept.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class seeder {

    /** @var string plugin-relative path of the bundled sample deck */
    const SAMPLEFILE = '/mod/flashdeck/sample/emd101-week1.json';

    /**
     * Append the bundled sample cards to a deck.
     *
     * @param \stdClass $deck the flashdeck record
     * @param int $userid user recorded as the cards' author
     * @return int number of cards added
     * @throws \moodle_exception if the sample file is missing or invalid
     */
    public static function seed(\stdClass $deck, int $userid): int {
        global $CFG;

        $filepath = $CFG->dirroot . self::SAMPLEFILE;
        if (!is_readable($filepath)) {
            throw new \moodle_exception('errsampledeck', 'mod_flashdeck', '', 'file not readable');
        }

        // Porter validates every card before anything is written.
        try {
            return porter::import_json($deck, file_get_contents($filepath), $userid);
        } catch (\moodle_exception $e) {
            throw new \moodle_exception('errsampledeck', 'mod_flashdeck', '', $e->getMessage());
        }
    }
}
