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

use mod_flashdeck\local\api;
use mod_flashdeck\scheduler\scheduler;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/flashdeck/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Backup/restore (via activity duplication) and course reset tests.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_flashdeck_activity_task
 * @covers     \restore_flashdeck_activity_task
 */
final class backup_restore_test extends \advanced_testcase {

    /**
     * Duplicating an activity (backup + restore) carries settings, cards
     * and card image files across, but not per-user data.
     */
    public function test_duplicate_carries_cards_and_files(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $deck = $generator->create_module('flashdeck', [
            'course' => $course->id, 'scheduler' => 'leitner', 'newperday' => 7, 'grade' => 55,
        ]);
        $student = $generator->create_and_enrol($course, 'student');

        /** @var \mod_flashdeck_generator $plugingenerator */
        $plugingenerator = $generator->get_plugin_generator('mod_flashdeck');
        $card1 = $plugingenerator->create_card(['deckid' => $deck->id, 'front' => 'Q1', 'back' => 'A1']);
        $imagecard = $plugingenerator->create_card([
            'deckid' => $deck->id,
            'cardtype' => 'imagelabel',
            'content' => [
                'variant' => 'identify', 'label' => 'Deltoid', 'alttext' => 'Shoulder muscles',
                'question' => '', 'description' => '',
                'region' => ['cx' => 42.5, 'cy' => 31.0, 'r' => 8.0],
            ],
        ]);

        // Attach an image to the imagelabel card.
        [, $cm] = get_course_and_cm_from_instance($deck, 'flashdeck');
        $context = \context_module::instance($cm->id);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_flashdeck', 'filearea' => 'cardimage',
            'itemid' => $imagecard->id, 'filepath' => '/', 'filename' => 'shoulder.png',
        ], 'fake-png-bytes');

        // Some per-user state, which must NOT be duplicated.
        api::grade_card($deck, $card1, $student->id, scheduler::GRADE_GOOD, $context);

        $newcm = duplicate_module($course, get_fast_modinfo($course)->get_cm($cm->id));

        $newdeck = $DB->get_record('flashdeck', ['id' => $newcm->instance], '*', MUST_EXIST);
        $this->assertSame('leitner', $newdeck->scheduler);
        $this->assertSame(7, (int) $newdeck->newperday);
        $this->assertSame(55, (int) $newdeck->grade);

        $newcards = array_values($DB->get_records('flashdeck_cards',
            ['deckid' => $newdeck->id], 'position ASC'));
        $this->assertCount(2, $newcards);
        $this->assertSame('basic', $newcards[0]->cardtype);
        $this->assertSame('imagelabel', $newcards[1]->cardtype);
        $this->assertEquals(json_decode($imagecard->content, true), json_decode($newcards[1]->content, true));

        // The card image followed the card, remapped to the new item id.
        $newcontext = \context_module::instance($newcm->id);
        $files = get_file_storage()->get_area_files($newcontext->id, 'mod_flashdeck', 'cardimage',
            $newcards[1]->id, 'itemid', false);
        $this->assertCount(1, $files);
        $this->assertSame('shoulder.png', reset($files)->get_filename());

        // Duplication is a fresh start: no review state came along.
        $this->assertSame(0, $DB->count_records('flashdeck_review', ['deckid' => $newdeck->id]));
    }

    /**
     * Course reset wipes per-user progress but keeps decks and cards.
     */
    public function test_course_reset(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $deck = $generator->create_module('flashdeck', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');

        /** @var \mod_flashdeck_generator $plugingenerator */
        $plugingenerator = $generator->get_plugin_generator('mod_flashdeck');
        $card = $plugingenerator->create_card(['deckid' => $deck->id]);

        [, $cm] = get_course_and_cm_from_instance($deck, 'flashdeck');
        $this->setUser($student);
        api::grade_card($deck, $card, $student->id, scheduler::GRADE_GOOD,
            \context_module::instance($cm->id));

        $this->assertSame(1, $DB->count_records('flashdeck_review', ['deckid' => $deck->id]));
        $this->assertSame(1, $DB->count_records('flashdeck_session', ['deckid' => $deck->id]));

        $this->setAdminUser();
        $status = flashdeck_reset_userdata((object) [
            'courseid' => $course->id,
            'reset_flashdeck_progress' => 1,
        ]);

        $this->assertFalse($status[0]['error']);
        $this->assertSame(0, $DB->count_records('flashdeck_review', ['deckid' => $deck->id]));
        $this->assertSame(0, $DB->count_records('flashdeck_session', ['deckid' => $deck->id]));
        // Content survives.
        $this->assertSame(1, $DB->count_records('flashdeck_cards', ['deckid' => $deck->id]));
        $this->assertTrue($DB->record_exists('flashdeck', ['id' => $deck->id]));
    }
}
