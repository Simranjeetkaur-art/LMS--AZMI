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

namespace mod_flashdeck\scheduler;

/**
 * Modified SM-2 scheduler (Anki-style), the default.
 *
 * New and lapsed cards move through short intra-day learning steps;
 * graduated cards get day-based intervals that grow with the ease
 * factor. The four grades map onto ease adjustments exactly as in
 * Anki's variant of SM-2:
 *
 *  - learning/relearning: Again restarts the steps, Hard repeats the
 *    current step, Good advances, Easy graduates immediately.
 *  - review: Again lapses the card (ease -0.20, relearn, interval
 *    halves on graduation), Hard = interval x 1.2 and ease -0.15,
 *    Good = interval x ease, Easy = interval x ease x 1.3 and
 *    ease +0.15. Ease never drops below 1.30. Intervals always grow
 *    by at least one day on success and are capped at MAX_INTERVAL_DAYS.
 *
 * No random fuzz is applied: deterministic output keeps the interval
 * maths unit-testable and reviews reproducible.
 *
 * While learning, the repetitions column holds the current step index.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sm2 extends scheduler {

    /** @var int[] learning steps for new cards, in seconds (1 min, 10 min) */
    const LEARNING_STEPS = [60, 600];

    /** @var int[] relearning steps after a lapse, in seconds (10 min) */
    const RELEARNING_STEPS = [600];

    /** @var int interval in days granted on normal graduation */
    const GRADUATE_INTERVAL = 1;

    /** @var int interval in days granted when Easy skips the steps */
    const EASY_INTERVAL = 4;

    /** @var float ease penalty for a lapse */
    const LAPSE_EASE_PENALTY = 0.20;

    /** @var float fraction of the old interval kept after relearning a lapsed card */
    const LAPSE_INTERVAL_FACTOR = 0.5;

    /** @var float minimum ease factor */
    const MIN_EASE = 1.3;

    #[\Override]
    public function get_identifier(): string {
        return 'sm2';
    }

    #[\Override]
    protected function apply(\stdClass $review, int $grade, int $now): void {
        switch ($review->state) {
            case self::STATE_NEW:
                // First contact enters the learning steps.
                $review->state = self::STATE_LEARNING;
                $review->repetitions = 0;
                $this->apply_steps($review, $grade, $now, self::LEARNING_STEPS);
                break;

            case self::STATE_LEARNING:
                $this->apply_steps($review, $grade, $now, self::LEARNING_STEPS);
                break;

            case self::STATE_RELEARNING:
                $this->apply_steps($review, $grade, $now, self::RELEARNING_STEPS);
                break;

            case self::STATE_REVIEW:
            default:
                $this->apply_review($review, $grade, $now);
                break;
        }
    }

    /**
     * Grade a card that is inside learning or relearning steps.
     *
     * @param \stdClass $review state to update; repetitions = current step index
     * @param int $grade one of the GRADE_* constants
     * @param int $now the review timestamp
     * @param int[] $steps the step delays for this phase, in seconds
     */
    protected function apply_steps(\stdClass $review, int $grade, int $now, array $steps): void {
        $step = (int) $review->repetitions;
        $step = max(0, min($step, count($steps) - 1));

        switch ($grade) {
            case self::GRADE_AGAIN:
                $review->repetitions = 0;
                $review->duedate = $now + $steps[0];
                break;

            case self::GRADE_HARD:
                // Repeat the current step.
                $review->repetitions = $step;
                $review->duedate = $now + $steps[$step];
                break;

            case self::GRADE_GOOD:
                if ($step + 1 < count($steps)) {
                    $review->repetitions = $step + 1;
                    $review->duedate = $now + $steps[$step + 1];
                } else {
                    $this->graduate($review, $now, false);
                }
                break;

            case self::GRADE_EASY:
                $this->graduate($review, $now, true);
                break;
        }
    }

    /**
     * Promote a card out of the (re)learning steps into review.
     *
     * @param \stdClass $review state to update
     * @param int $now the review timestamp
     * @param bool $easy whether the Easy button triggered the graduation
     */
    protected function graduate(\stdClass $review, int $now, bool $easy): void {
        if ($review->state === self::STATE_RELEARNING) {
            // A relearned card keeps a fraction of its old interval.
            $interval = max(1, (int) round($review->intervaldays * self::LAPSE_INTERVAL_FACTOR));
            if ($easy) {
                $interval = max($interval + 1, self::EASY_INTERVAL);
            }
        } else {
            $interval = $easy ? self::EASY_INTERVAL : self::GRADUATE_INTERVAL;
        }
        $review->state = self::STATE_REVIEW;
        $review->repetitions = 1;
        $review->intervaldays = $interval;
        $review->duedate = $now + $interval * DAYSECS;
    }

    /**
     * Grade a graduated card on day-based intervals.
     *
     * @param \stdClass $review state to update
     * @param int $grade one of the GRADE_* constants
     * @param int $now the review timestamp
     */
    protected function apply_review(\stdClass $review, int $grade, int $now): void {
        $interval = max(1, (int) $review->intervaldays);
        $ease = (float) $review->easefactor;

        switch ($grade) {
            case self::GRADE_AGAIN:
                $review->lapses = (int) $review->lapses + 1;
                $review->easefactor = max(self::MIN_EASE, $ease - self::LAPSE_EASE_PENALTY);
                $review->state = self::STATE_RELEARNING;
                $review->repetitions = 0;
                // Old interval is kept on the record so graduation can halve it.
                $review->duedate = $now + self::RELEARNING_STEPS[0];
                break;

            case self::GRADE_HARD:
                $review->easefactor = max(self::MIN_EASE, $ease - 0.15);
                $review->intervaldays = max($interval + 1, (int) round($interval * 1.2));
                $review->repetitions = (int) $review->repetitions + 1;
                $review->duedate = $now + $review->intervaldays * DAYSECS;
                break;

            case self::GRADE_GOOD:
                $review->intervaldays = max($interval + 1, (int) round($interval * $ease));
                $review->repetitions = (int) $review->repetitions + 1;
                $review->duedate = $now + $review->intervaldays * DAYSECS;
                break;

            case self::GRADE_EASY:
                $review->easefactor = $ease + 0.15;
                $review->intervaldays = max($interval + 1, (int) round($interval * $ease * 1.3));
                $review->repetitions = (int) $review->repetitions + 1;
                $review->duedate = $now + $review->intervaldays * DAYSECS;
                break;
        }
    }
}
