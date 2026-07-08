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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/flashdeck/lib.php');

/**
 * Tests for the mod_flashdeck library callbacks.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_flashdeck
 */
final class lib_test extends \advanced_testcase {

    /**
     * Adding an instance stores the deck with its study settings.
     */
    public function test_add_instance(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $deck = $this->getDataGenerator()->create_module('flashdeck', [
            'course' => $course->id,
            'name' => 'Week 1 terminology',
            'scheduler' => 'leitner',
            'newperday' => 15,
        ]);

        $record = $DB->get_record('flashdeck', ['id' => $deck->id], '*', MUST_EXIST);
        $this->assertSame('Week 1 terminology', $record->name);
        $this->assertSame('leitner', $record->scheduler);
        $this->assertEquals(15, $record->newperday);
        $this->assertGreaterThan(0, $record->timecreated);
    }

    /**
     * Updating an instance persists changed settings.
     */
    public function test_update_instance(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $deck = $this->getDataGenerator()->create_module('flashdeck', ['course' => $course->id]);

        $data = (object) [
            'instance' => $deck->id,
            'course' => $course->id,
            'name' => 'Renamed deck',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'scheduler' => 'sm2',
            'newperday' => 30,
        ];
        $this->assertTrue(flashdeck_update_instance($data));

        $record = $DB->get_record('flashdeck', ['id' => $deck->id], '*', MUST_EXIST);
        $this->assertSame('Renamed deck', $record->name);
        $this->assertEquals(30, $record->newperday);
    }

    /**
     * Deleting an instance removes its cards and review state too.
     */
    public function test_delete_instance_removes_dependents(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $deck = $generator->create_module('flashdeck', ['course' => $course->id]);

        /** @var \mod_flashdeck_generator $plugingenerator */
        $plugingenerator = $generator->get_plugin_generator('mod_flashdeck');
        $card = $plugingenerator->create_card(['deckid' => $deck->id]);

        // Simulate Phase 2 review state so the cleanup path is exercised now.
        $DB->insert_record('flashdeck_review', (object) [
            'deckid' => $deck->id,
            'cardid' => $card->id,
            'userid' => $user->id,
            'duedate' => time(),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->assertTrue(flashdeck_delete_instance($deck->id));

        $this->assertFalse($DB->record_exists('flashdeck', ['id' => $deck->id]));
        $this->assertFalse($DB->record_exists('flashdeck_cards', ['deckid' => $deck->id]));
        $this->assertFalse($DB->record_exists('flashdeck_review', ['deckid' => $deck->id]));
    }

    /**
     * Deleting an unknown instance reports failure.
     */
    public function test_delete_missing_instance(): void {
        $this->resetAfterTest();
        $this->assertFalse(flashdeck_delete_instance(987654));
    }

    /**
     * The declared features match the Phase 1 surface.
     */
    public function test_supports(): void {
        $this->assertTrue(flashdeck_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(flashdeck_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertFalse(flashdeck_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertNull(flashdeck_supports(FEATURE_BACKUP_MOODLE2));
    }
}
