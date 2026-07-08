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

namespace mod_flashdeck;

use mod_flashdeck\cardtype\manager;
use mod_flashdeck\local\seeder;

/**
 * Tests for the bundled sample deck and its loader.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_flashdeck\local\seeder
 */
final class seeder_test extends \advanced_testcase {

    /**
     * The sample deck loads, every card validates, and positions run 1..n.
     */
    public function test_seed(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $deck = $generator->create_module('flashdeck', ['course' => $course->id]);

        $count = seeder::seed($deck, $user->id);

        $cards = $DB->get_records('flashdeck_cards', ['deckid' => $deck->id], 'position ASC');
        $this->assertGreaterThanOrEqual(12, $count);
        $this->assertCount($count, $cards);

        $position = 0;
        $dissections = 0;
        foreach ($cards as $card) {
            $this->assertSame(++$position, (int) $card->position);
            $type = manager::get($card->cardtype);
            $content = json_decode($card->content, true);
            $this->assertIsArray($content);
            $this->assertSame([], $type->validate_content($content));
            $dissections += (int) ($card->cardtype === 'termdissection');
        }

        // The flagship interaction is demonstrated by the sample.
        $this->assertGreaterThanOrEqual(10, $dissections);

        // Seeding again appends after the existing cards, not over them.
        $secondcount = seeder::seed($deck, $user->id);
        $this->assertSame($count + $secondcount,
            $DB->count_records('flashdeck_cards', ['deckid' => $deck->id]));
        $max = (int) $DB->get_field_sql(
            'SELECT MAX(position) FROM {flashdeck_cards} WHERE deckid = ?', [$deck->id]);
        $this->assertSame($count + $secondcount, $max);
    }
}
