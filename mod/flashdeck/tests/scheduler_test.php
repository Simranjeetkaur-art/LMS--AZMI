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

use mod_flashdeck\scheduler\leitner;
use mod_flashdeck\scheduler\scheduler;
use mod_flashdeck\scheduler\sm2;

/**
 * Interval-maths tests for the SM-2 and Leitner schedulers.
 *
 * Both schedulers are deterministic and side-effect free, so every
 * expected interval below is an exact value.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_flashdeck\scheduler\scheduler
 * @covers     \mod_flashdeck\scheduler\sm2
 * @covers     \mod_flashdeck\scheduler\leitner
 */
final class scheduler_test extends \advanced_testcase {

    /** @var int an arbitrary fixed "now" for deterministic assertions */
    const NOW = 1750000000;

    /**
     * A review state with optional overrides.
     *
     * @param array $overrides field => value
     * @return \stdClass
     */
    protected function review(array $overrides = []): \stdClass {
        return (object) array_merge((array) scheduler::blank_review(1, 1, 1, self::NOW), $overrides);
    }

    /**
     * The factory resolves identifiers and falls back to SM-2.
     */
    public function test_factory(): void {
        $this->assertInstanceOf(sm2::class, scheduler::create('sm2'));
        $this->assertInstanceOf(leitner::class, scheduler::create('leitner'));
        $this->assertInstanceOf(sm2::class, scheduler::create('bogus'));
    }

    /**
     * Grades outside 0..3 are rejected.
     */
    public function test_invalid_grade_rejected(): void {
        $this->expectException(\coding_exception::class);
        (new sm2())->grade($this->review(), 4, self::NOW);
    }

    /**
     * New cards move through the 1 min / 10 min learning steps.
     */
    public function test_sm2_learning_steps(): void {
        $s = new sm2();

        // Good on a new card: advance to the 10-minute step.
        $r = $s->grade($this->review(), scheduler::GRADE_GOOD, self::NOW);
        $this->assertSame(scheduler::STATE_LEARNING, $r->state);
        $this->assertSame(1, (int) $r->repetitions);
        $this->assertSame(self::NOW + 600, (int) $r->duedate);
        $this->assertSame(scheduler::GRADE_GOOD, (int) $r->lastgrade);
        $this->assertSame(self::NOW, (int) $r->lastreviewed);

        // Good again: graduate to review at 1 day.
        $r = $s->grade($r, scheduler::GRADE_GOOD, self::NOW + 600);
        $this->assertSame(scheduler::STATE_REVIEW, $r->state);
        $this->assertSame(1, (int) $r->intervaldays);
        $this->assertSame(self::NOW + 600 + DAYSECS, (int) $r->duedate);

        // Again on a new card: back in 1 minute.
        $r = $s->grade($this->review(), scheduler::GRADE_AGAIN, self::NOW);
        $this->assertSame(scheduler::STATE_LEARNING, $r->state);
        $this->assertSame(0, (int) $r->repetitions);
        $this->assertSame(self::NOW + 60, (int) $r->duedate);

        // Hard on a new card: repeat the first step.
        $r = $s->grade($this->review(), scheduler::GRADE_HARD, self::NOW);
        $this->assertSame(self::NOW + 60, (int) $r->duedate);

        // Easy on a new card: skip the steps, graduate at 4 days.
        $r = $s->grade($this->review(), scheduler::GRADE_EASY, self::NOW);
        $this->assertSame(scheduler::STATE_REVIEW, $r->state);
        $this->assertSame(4, (int) $r->intervaldays);
        $this->assertSame(self::NOW + 4 * DAYSECS, (int) $r->duedate);
    }

    /**
     * Graduated intervals grow with the ease factor: 1 -> 3 -> 8 -> 20.
     */
    public function test_sm2_review_growth(): void {
        $s = new sm2();
        $r = $this->review(['state' => scheduler::STATE_REVIEW, 'intervaldays' => 1, 'repetitions' => 1]);

        $expected = [3, 8, 20];
        $now = self::NOW;
        foreach ($expected as $days) {
            $r = $s->grade($r, scheduler::GRADE_GOOD, $now);
            $this->assertSame($days, (int) $r->intervaldays);
            $this->assertSame($now + $days * DAYSECS, (int) $r->duedate);
            $this->assertEqualsWithDelta(2.5, (float) $r->easefactor, 0.001);
            $now = (int) $r->duedate;
        }
    }

    /**
     * Hard grows the interval slowly (x1.2, at least +1 day) and drops ease.
     */
    public function test_sm2_hard(): void {
        $s = new sm2();

        $r = $this->review(['state' => scheduler::STATE_REVIEW, 'intervaldays' => 1, 'repetitions' => 1]);
        $r = $s->grade($r, scheduler::GRADE_HARD, self::NOW);
        // Round(1.2) = 1 would stall; the +1 floor guarantees growth.
        $this->assertSame(2, (int) $r->intervaldays);
        $this->assertEqualsWithDelta(2.35, (float) $r->easefactor, 0.001);

        $r = $this->review(['state' => scheduler::STATE_REVIEW, 'intervaldays' => 10, 'repetitions' => 3]);
        $r = $s->grade($r, scheduler::GRADE_HARD, self::NOW);
        $this->assertSame(12, (int) $r->intervaldays);
    }

    /**
     * Easy applies the 1.3 bonus using the pre-increment ease, then raises ease.
     */
    public function test_sm2_easy(): void {
        $s = new sm2();
        $r = $this->review(['state' => scheduler::STATE_REVIEW, 'intervaldays' => 10, 'repetitions' => 3]);
        $r = $s->grade($r, scheduler::GRADE_EASY, self::NOW);
        // Round(10 x 2.5 x 1.3) = round(32.5) = 33.
        $this->assertSame(33, (int) $r->intervaldays);
        $this->assertEqualsWithDelta(2.65, (float) $r->easefactor, 0.001);
    }

    /**
     * Again on a graduated card lapses it: relearn, halve on graduation.
     */
    public function test_sm2_lapse_and_relearn(): void {
        $s = new sm2();
        $r = $this->review([
            'state' => scheduler::STATE_REVIEW, 'intervaldays' => 20, 'repetitions' => 5,
        ]);

        $r = $s->grade($r, scheduler::GRADE_AGAIN, self::NOW);
        $this->assertSame(scheduler::STATE_RELEARNING, $r->state);
        $this->assertSame(1, (int) $r->lapses);
        $this->assertEqualsWithDelta(2.3, (float) $r->easefactor, 0.001);
        $this->assertSame(self::NOW + 600, (int) $r->duedate);
        // The old interval is retained so graduation can halve it.
        $this->assertSame(20, (int) $r->intervaldays);

        $r = $s->grade($r, scheduler::GRADE_GOOD, self::NOW + 600);
        $this->assertSame(scheduler::STATE_REVIEW, $r->state);
        $this->assertSame(10, (int) $r->intervaldays);
        $this->assertSame(self::NOW + 600 + 10 * DAYSECS, (int) $r->duedate);
    }

    /**
     * Ease never drops below 1.30.
     */
    public function test_sm2_ease_floor(): void {
        $s = new sm2();
        $r = $this->review([
            'state' => scheduler::STATE_REVIEW, 'intervaldays' => 5, 'easefactor' => 1.35, 'repetitions' => 2,
        ]);
        $r = $s->grade($r, scheduler::GRADE_AGAIN, self::NOW);
        $this->assertEqualsWithDelta(1.3, (float) $r->easefactor, 0.001);

        // Grade the relearned card, lapse it again: still floored.
        $r = $s->grade($r, scheduler::GRADE_GOOD, self::NOW + 600);
        $r = $s->grade($r, scheduler::GRADE_AGAIN, self::NOW + DAYSECS);
        $this->assertEqualsWithDelta(1.3, (float) $r->easefactor, 0.001);
    }

    /**
     * Intervals never exceed the hard cap.
     */
    public function test_sm2_interval_cap(): void {
        $s = new sm2();
        $r = $this->review(['state' => scheduler::STATE_REVIEW, 'intervaldays' => 300, 'repetitions' => 9]);
        $r = $s->grade($r, scheduler::GRADE_GOOD, self::NOW);
        $this->assertSame(scheduler::MAX_INTERVAL_DAYS, (int) $r->intervaldays);
    }

    /**
     * Previews expose the exact delay each grade would schedule.
     */
    public function test_sm2_preview(): void {
        $s = new sm2();

        $previews = $s->preview($this->review(), self::NOW);
        $this->assertSame(60, $previews[scheduler::GRADE_AGAIN]);
        $this->assertSame(60, $previews[scheduler::GRADE_HARD]);
        $this->assertSame(600, $previews[scheduler::GRADE_GOOD]);
        $this->assertSame(4 * DAYSECS, $previews[scheduler::GRADE_EASY]);

        $r = $this->review(['state' => scheduler::STATE_REVIEW, 'intervaldays' => 10, 'repetitions' => 3]);
        $previews = $s->preview($r, self::NOW);
        $this->assertSame(600, $previews[scheduler::GRADE_AGAIN]);
        $this->assertSame(12 * DAYSECS, $previews[scheduler::GRADE_HARD]);
        $this->assertSame(25 * DAYSECS, $previews[scheduler::GRADE_GOOD]);
        $this->assertSame(33 * DAYSECS, $previews[scheduler::GRADE_EASY]);
    }

    /**
     * Preview must not mutate the state it previews.
     */
    public function test_preview_is_pure(): void {
        $r = $this->review();
        $before = clone $r;
        (new sm2())->preview($r, self::NOW);
        $this->assertEquals($before, $r);
    }

    /**
     * Leitner: Good/Easy climb the boxes onto fixed intervals.
     */
    public function test_leitner_promotion(): void {
        $s = new leitner();

        // New card, Good: box 1, 1 day, still 'learning'.
        $r = $s->grade($this->review(), scheduler::GRADE_GOOD, self::NOW);
        $this->assertSame(1, (int) $r->repetitions);
        $this->assertSame(scheduler::STATE_LEARNING, $r->state);
        $this->assertSame(1, (int) $r->intervaldays);
        $this->assertSame(self::NOW + DAYSECS, (int) $r->duedate);

        // Climb: box 2 (2d, learning), box 3 (4d, review), 4 (8d), 5 (16d), 5 (16d).
        $expected = [[2, 2, 'learning'], [3, 4, 'review'], [4, 8, 'review'], [5, 16, 'review'], [5, 16, 'review']];
        $now = self::NOW;
        foreach ($expected as [$box, $days, $state]) {
            $now = (int) $r->duedate;
            $r = $s->grade($r, scheduler::GRADE_GOOD, $now);
            $this->assertSame($box, (int) $r->repetitions);
            $this->assertSame($days, (int) $r->intervaldays);
            $this->assertSame($state, $r->state);
            $this->assertSame($now + $days * DAYSECS, (int) $r->duedate);
        }

        // Easy jumps two boxes.
        $r = $s->grade($this->review(), scheduler::GRADE_EASY, self::NOW);
        $this->assertSame(2, (int) $r->repetitions);
        $this->assertSame(2, (int) $r->intervaldays);
    }

    /**
     * Leitner: Hard repeats the box; Again returns to box 1 in 10 minutes.
     */
    public function test_leitner_hard_and_again(): void {
        $s = new leitner();
        $box3 = $this->review([
            'state' => scheduler::STATE_REVIEW, 'repetitions' => 3, 'intervaldays' => 4,
        ]);

        $r = $s->grade($box3, scheduler::GRADE_HARD, self::NOW);
        $this->assertSame(3, (int) $r->repetitions);
        $this->assertSame(4, (int) $r->intervaldays);
        $this->assertSame(self::NOW + 4 * DAYSECS, (int) $r->duedate);

        // Again from a review box counts a lapse and restarts at box 1.
        $r = $s->grade($box3, scheduler::GRADE_AGAIN, self::NOW);
        $this->assertSame(1, (int) $r->repetitions);
        $this->assertSame(1, (int) $r->lapses);
        $this->assertSame(scheduler::STATE_LEARNING, $r->state);
        $this->assertSame(self::NOW + 600, (int) $r->duedate);

        // Again from an early box does not count a lapse.
        $r = $s->grade($this->review(['repetitions' => 1, 'state' => scheduler::STATE_LEARNING]),
            scheduler::GRADE_AGAIN, self::NOW);
        $this->assertSame(0, (int) $r->lapses);
    }
}
