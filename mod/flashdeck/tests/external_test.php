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

use mod_flashdeck\external\get_deck_progress;
use mod_flashdeck\external\get_next_due_card;
use mod_flashdeck\external\submit_review;
use mod_flashdeck\scheduler\scheduler;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the AJAX study loop external functions.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_flashdeck\external\get_next_due_card
 * @covers     \mod_flashdeck\external\submit_review
 * @covers     \mod_flashdeck\external\get_deck_progress
 * @covers     \mod_flashdeck\local\api
 */
final class external_test extends \externallib_advanced_testcase {

    /**
     * Create a course, deck, enrolled student and two basic cards.
     *
     * @param array $deckoptions extra flashdeck settings
     * @return array [deck, student, card1, card2]
     */
    protected function setup_deck(array $deckoptions = []): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $deck = $generator->create_module('flashdeck', $deckoptions + ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');

        /** @var \mod_flashdeck_generator $plugingenerator */
        $plugingenerator = $generator->get_plugin_generator('mod_flashdeck');
        $card1 = $plugingenerator->create_card(['deckid' => $deck->id, 'front' => 'Q1', 'back' => 'A1']);
        $card2 = $plugingenerator->create_card(['deckid' => $deck->id, 'front' => 'Q2', 'back' => 'A2']);

        return [$deck, $student, $card1, $card2];
    }

    /**
     * New cards are served in deck position order with previews and counts.
     */
    public function test_get_next_due_card_serves_new_in_order(): void {
        $this->resetAfterTest();
        [$deck, $student, $card1] = $this->setup_deck();
        $this->setUser($student);

        $result = get_next_due_card::execute($deck->id);
        $result = \core_external\external_api::clean_returnvalue(get_next_due_card::execute_returns(), $result);

        $this->assertFalse($result['done']);
        $this->assertSame((int) $card1->id, $result['cardid']);
        $this->assertSame('new', $result['state']);
        $this->assertStringContainsString('Q1', $result['cardhtml']);
        $this->assertSame(2, $result['counts']['total']);
        $this->assertSame(2, $result['counts']['newremaining']);
        $this->assertSame(0, $result['counts']['duenow']);
        // SM-2 previews for a new card.
        $this->assertSame('1 min', $result['previews']['again']);
        $this->assertSame('10 min', $result['previews']['good']);
        $this->assertSame('4 days', $result['previews']['easy']);
    }

    /**
     * Grading persists server-computed state, fires the event, returns the next card.
     */
    public function test_submit_review_persists_and_returns_next(): void {
        global $DB;
        $this->resetAfterTest();
        [$deck, $student, $card1, $card2] = $this->setup_deck();
        $this->setUser($student);

        $sink = $this->redirectEvents();
        $before = time();
        $result = submit_review::execute($card1->id, scheduler::GRADE_GOOD);
        $result = \core_external\external_api::clean_returnvalue(submit_review::execute_returns(), $result);

        // The schedule was computed and stored server-side.
        $review = $DB->get_record('flashdeck_review',
            ['cardid' => $card1->id, 'userid' => $student->id], '*', MUST_EXIST);
        $this->assertSame('learning', $review->state);
        $this->assertSame(scheduler::GRADE_GOOD, (int) $review->lastgrade);
        $this->assertGreaterThanOrEqual($before + 600, (int) $review->duedate);

        // The event fired for the report/analytics pipeline.
        $events = array_filter($sink->get_events(), function ($event) {
            return $event instanceof \mod_flashdeck\event\card_reviewed;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertSame((int) $card1->id, $event->other['cardid']);

        // The response carries the next card: card2 is the only new one left.
        $this->assertFalse($result['done']);
        $this->assertSame((int) $card2->id, $result['cardid']);
        $this->assertStringContainsString('Q2', $result['cardhtml']);
        $this->assertSame(1, $result['counts']['learning']);
    }

    /**
     * A full session drains the queue and lands on the done panel; the
     * schedule survives "logout" because everything lives server-side.
     */
    public function test_full_session_reaches_done_and_resumes(): void {
        global $DB;
        $this->resetAfterTest();
        [$deck, $student, $card1, $card2] = $this->setup_deck();
        $this->setUser($student);

        submit_review::execute($card1->id, scheduler::GRADE_EASY);
        $result = submit_review::execute($card2->id, scheduler::GRADE_EASY);
        $result = \core_external\external_api::clean_returnvalue(submit_review::execute_returns(), $result);

        $this->assertTrue($result['done']);
        $this->assertGreaterThan(time(), $result['nextdue']);
        $this->assertNotSame('', $result['nextduelabel']);
        $this->assertSame(0, $result['counts']['duenow']);
        $this->assertSame(0, $result['counts']['newremaining']);

        // Resume in a "new session": the same done state is served.
        $this->setUser($student);
        $again = get_next_due_card::execute($deck->id);
        $again = \core_external\external_api::clean_returnvalue(get_next_due_card::execute_returns(), $again);
        $this->assertTrue($again['done']);
        $this->assertSame(2, $DB->count_records('flashdeck_review', ['userid' => $student->id]));
    }

    /**
     * Learning cards due shortly are served ahead once the queue is idle.
     */
    public function test_learn_ahead_serves_pending_learning_cards(): void {
        $this->resetAfterTest();
        [$deck, $student, $card1, $card2] = $this->setup_deck();
        $this->setUser($student);

        // Card1 into learning (+10 min), card2 graduated away (+4 days).
        submit_review::execute($card1->id, scheduler::GRADE_GOOD);
        $result = submit_review::execute($card2->id, scheduler::GRADE_EASY);
        $result = \core_external\external_api::clean_returnvalue(submit_review::execute_returns(), $result);

        // Nothing strictly due, but the learning step is inside the
        // learn-ahead window, so the session can finish it.
        $this->assertFalse($result['done']);
        $this->assertSame((int) $card1->id, $result['cardid']);
        $this->assertSame('learning', $result['state']);
    }

    /**
     * The per-day new-card budget is enforced server-side.
     */
    public function test_newperday_limit(): void {
        $this->resetAfterTest();
        [$deck, $student, $card1] = $this->setup_deck(['newperday' => 1]);
        $this->setUser($student);

        $first = get_next_due_card::execute($deck->id);
        $first = \core_external\external_api::clean_returnvalue(get_next_due_card::execute_returns(), $first);
        $this->assertSame((int) $card1->id, $first['cardid']);
        $this->assertSame(1, $first['counts']['newremaining']);

        // Graduate card1 away: the second new card must NOT be introduced today.
        $result = submit_review::execute($card1->id, scheduler::GRADE_EASY);
        $result = \core_external\external_api::clean_returnvalue(submit_review::execute_returns(), $result);
        $this->assertTrue($result['done']);
        $this->assertSame(0, $result['counts']['newremaining']);
    }

    /**
     * The Leitner deck setting drives the scheduling of graded cards.
     */
    public function test_leitner_deck_setting_is_honoured(): void {
        global $DB;
        $this->resetAfterTest();
        [, $student, $card1] = $this->setup_deck(['scheduler' => 'leitner']);
        $this->setUser($student);

        submit_review::execute($card1->id, scheduler::GRADE_GOOD);
        $review = $DB->get_record('flashdeck_review',
            ['cardid' => $card1->id, 'userid' => $student->id], '*', MUST_EXIST);
        // Leitner box 1 = 1 day; SM-2 would be a 10-minute learning step.
        $this->assertSame(1, (int) $review->repetitions);
        $this->assertSame(1, (int) $review->intervaldays);
    }

    /**
     * Users without the study capability are rejected.
     */
    public function test_requires_study_capability(): void {
        $this->resetAfterTest();
        [$deck] = $this->setup_deck();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\moodle_exception::class);
        get_next_due_card::execute($deck->id);
    }

    /**
     * Out-of-range grades are rejected before touching the scheduler.
     */
    public function test_invalid_grade_rejected(): void {
        $this->resetAfterTest();
        [, $student, $card1] = $this->setup_deck();
        $this->setUser($student);

        $this->expectException(\invalid_parameter_exception::class);
        submit_review::execute($card1->id, 9);
    }

    /**
     * Progress counts reflect the review states.
     */
    public function test_get_deck_progress(): void {
        $this->resetAfterTest();
        [$deck, $student, $card1] = $this->setup_deck();
        $this->setUser($student);

        submit_review::execute($card1->id, scheduler::GRADE_EASY);

        $progress = get_deck_progress::execute($deck->id);
        $progress = \core_external\external_api::clean_returnvalue(get_deck_progress::execute_returns(), $progress);

        $this->assertSame(2, $progress['total']);
        $this->assertSame(1, $progress['seen']);
        $this->assertSame(1, $progress['new']);
        $this->assertSame(0, $progress['learning']);
        $this->assertSame(1, $progress['review']);
        $this->assertSame(1, $progress['introducedtoday']);
    }
}
