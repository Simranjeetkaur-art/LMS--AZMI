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
use mod_flashdeck\output\match_page;
use mod_flashdeck\scheduler\scheduler;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/flashdeck/lib.php');

/**
 * Tests for sessions, streaks, points, mastery, grades and completion.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_flashdeck\local\api
 * @covers     \mod_flashdeck\completion\custom_completion
 * @covers     \mod_flashdeck\output\match_page
 */
final class gamification_test extends \advanced_testcase {

    /**
     * Create a course, deck, enrolled student and two basic cards.
     *
     * @param array $deckoptions extra flashdeck settings
     * @return array [course, deck, cm_info, context, student, card1, card2]
     */
    protected function setup_deck(array $deckoptions = []): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $deck = $generator->create_module('flashdeck', $deckoptions + ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');

        /** @var \mod_flashdeck_generator $plugingenerator */
        $plugingenerator = $generator->get_plugin_generator('mod_flashdeck');
        $card1 = $plugingenerator->create_card(['deckid' => $deck->id, 'front' => 'Q1', 'back' => 'A1']);
        $card2 = $plugingenerator->create_card(['deckid' => $deck->id, 'front' => 'Q2', 'back' => 'A2']);

        [, $cm] = get_course_and_cm_from_instance($deck, 'flashdeck');
        return [$course, $deck, $cm, \context_module::instance($cm->id), $student, $card1, $card2];
    }

    /**
     * Sessions aggregate per day; points reward effort plus success.
     */
    public function test_sessions_points_and_correct(): void {
        global $DB;
        $this->resetAfterTest();
        [, $deck, , $context, $student, $card1, $card2] = $this->setup_deck();
        $this->setUser($student);
        $now = time();

        api::grade_card($deck, $card1, $student->id, scheduler::GRADE_GOOD, $context, $now);
        api::grade_card($deck, $card2, $student->id, scheduler::GRADE_AGAIN, $context, $now + 60);

        $session = $DB->get_record('flashdeck_session',
            ['deckid' => $deck->id, 'userid' => $student->id], '*', MUST_EXIST);
        $this->assertSame(2, (int) $session->reviews);
        $this->assertSame(1, (int) $session->correct);
        // Good = 5 + 5 bonus, Again = 5.
        $this->assertSame(15, (int) $session->points);
        $this->assertSame(15, api::get_points($deck, $student->id));
        $this->assertSame($now, (int) $session->firstreview);
        $this->assertSame($now + 60, (int) $session->lastreview);
    }

    /**
     * A streak counts consecutive days back from today and breaks on a gap.
     */
    public function test_streak(): void {
        $this->resetAfterTest();
        [, $deck, , $context, $student, $card1] = $this->setup_deck();
        $this->setUser($student);
        $now = time();

        $this->assertSame(0, api::get_streak($deck, $student->id, $now));

        // Three consecutive days ending today.
        foreach ([2, 1, 0] as $daysago) {
            api::grade_card($deck, $card1, $student->id, scheduler::GRADE_GOOD, $context,
                $now - $daysago * DAYSECS);
        }
        $this->assertSame(3, api::get_streak($deck, $student->id, $now));

        // Still alive tomorrow morning (studied "yesterday"), dead a day later.
        $this->assertSame(3, api::get_streak($deck, $student->id, $now + DAYSECS));
        $this->assertSame(0, api::get_streak($deck, $student->id, $now + 3 * DAYSECS));
    }

    /**
     * A gap in the past limits the streak to the recent run.
     */
    public function test_streak_gap(): void {
        $this->resetAfterTest();
        [, $deck, , $context, $student, $card1] = $this->setup_deck();
        $this->setUser($student);
        $now = time();

        foreach ([5, 4, 1, 0] as $daysago) {
            api::grade_card($deck, $card1, $student->id, scheduler::GRADE_GOOD, $context,
                $now - $daysago * DAYSECS);
        }
        $this->assertSame(2, api::get_streak($deck, $student->id, $now));
    }

    /**
     * Mastery is graduated cards over the whole deck.
     */
    public function test_mastery(): void {
        $this->resetAfterTest();
        [, $deck, , $context, $student, $card1] = $this->setup_deck();
        $this->setUser($student);

        $this->assertSame([0, 2, 0], api::get_mastery($deck, $student->id));

        // Easy graduates immediately: 1 of 2 cards = 50%.
        api::grade_card($deck, $card1, $student->id, scheduler::GRADE_EASY, $context);
        $this->assertSame([1, 2, 50], api::get_mastery($deck, $student->id));
    }

    /**
     * Mastery grades reach the gradebook and track further progress.
     */
    public function test_gradebook_push(): void {
        $this->resetAfterTest();
        [$course, $deck, , $context, $student, $card1, $card2] = $this->setup_deck(['grade' => 80]);
        $this->setUser($student);

        api::grade_card($deck, $card1, $student->id, scheduler::GRADE_EASY, $context);
        $grades = grade_get_grades($course->id, 'mod', 'flashdeck', $deck->id, $student->id);
        $this->assertEquals(40.0, (float) $grades->items[0]->grades[$student->id]->grade);

        api::grade_card($deck, $card2, $student->id, scheduler::GRADE_EASY, $context);
        $grades = grade_get_grades($course->id, 'mod', 'flashdeck', $deck->id, $student->id);
        $this->assertEquals(80.0, (float) $grades->items[0]->grades[$student->id]->grade);
    }

    /**
     * A deck without a grade setting creates no gradable item values.
     */
    public function test_no_grade_when_disabled(): void {
        $this->resetAfterTest();
        [, $deck, , $context, $student, $card1] = $this->setup_deck(['grade' => 0]);
        $this->setUser($student);

        api::grade_card($deck, $card1, $student->id, scheduler::GRADE_EASY, $context);
        $this->assertSame([], flashdeck_get_user_grades($deck, $student->id));
    }

    /**
     * Both custom completion rules flip when their thresholds are met.
     */
    public function test_custom_completion(): void {
        $this->resetAfterTest();
        [$course, $deck, , $context, $student, $card1, $card2] = $this->setup_deck([
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionstudied' => 2,
            'completionmastery' => 100,
        ]);
        $this->setUser($student);

        $cm = get_fast_modinfo($course, $student->id)->get_cm($deck->cmid);
        $custom = new \mod_flashdeck\completion\custom_completion($cm, $student->id);

        $this->assertSame(COMPLETION_INCOMPLETE, $custom->get_state('completionstudied'));
        $this->assertSame(COMPLETION_INCOMPLETE, $custom->get_state('completionmastery'));

        // One card studied (Good: still learning) - not enough for either rule.
        api::grade_card($deck, $card1, $student->id, scheduler::GRADE_GOOD, $context);
        $this->assertSame(COMPLETION_INCOMPLETE, $custom->get_state('completionstudied'));

        // Second card studied: the studied rule passes; mastery does not.
        api::grade_card($deck, $card2, $student->id, scheduler::GRADE_EASY, $context);
        $this->assertSame(COMPLETION_COMPLETE, $custom->get_state('completionstudied'));
        $this->assertSame(COMPLETION_INCOMPLETE, $custom->get_state('completionmastery'));

        // Graduate the first card too: 100% mastery.
        api::grade_card($deck, $card1, $student->id, scheduler::GRADE_EASY, $context);
        $this->assertSame(COMPLETION_COMPLETE, $custom->get_state('completionmastery'));
    }

    /**
     * The match game extracts only short, complete pairs.
     */
    public function test_match_pair_extraction(): void {
        $this->resetAfterTest();

        $cards = [
            (object) ['id' => 1, 'cardtype' => 'matching', 'content' => json_encode([
                'prompt' => 'x',
                'pairs' => [['left' => 'brady-', 'right' => 'slow'], ['left' => 'tachy-', 'right' => 'fast']],
            ])],
            (object) ['id' => 2, 'cardtype' => 'termdissection', 'content' => json_encode([
                'term' => 'hepatitis', 'definition' => 'Inflammation of the liver.',
                'parts' => [['text' => 'hepat', 'role' => 'root', 'meaning' => 'liver']],
            ])],
            (object) ['id' => 3, 'cardtype' => 'basic', 'content' => json_encode([
                'front' => '<p>-itis?</p>', 'frontformat' => FORMAT_HTML,
                'back' => '<p>Inflammation</p>', 'backformat' => FORMAT_HTML,
            ])],
            // Too long for a tile: excluded.
            (object) ['id' => 4, 'cardtype' => 'basic', 'content' => json_encode([
                'front' => '<p>' . str_repeat('long ', 20) . '</p>', 'frontformat' => FORMAT_HTML,
                'back' => '<p>short</p>', 'backformat' => FORMAT_HTML,
            ])],
            // No pairable content: excluded.
            (object) ['id' => 5, 'cardtype' => 'cloze', 'content' => json_encode([
                'text' => 'A [[blank]].', 'casesensitive' => false,
            ])],
        ];

        $pairs = match_page::collect_pairs($cards);
        $this->assertCount(4, $pairs);
        $this->assertContains(['brady-', 'slow'], $pairs);
        $this->assertContains(['hepatitis', 'Inflammation of the liver.'], $pairs);
        $this->assertContains(['-itis?', 'Inflammation'], $pairs);
    }
}
