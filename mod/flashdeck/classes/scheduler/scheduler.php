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
 * Base class for spaced-repetition schedulers.
 *
 * A scheduler owns one thing: given a card's current per-user review
 * state and a four-button self-grade, produce the updated state (next
 * due date, interval, ease, learning state). It never touches the
 * database and is fully deterministic — both properties the interval
 * unit tests rely on. All scheduling runs server-side; grades are the
 * only thing the client ever sends.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class scheduler {

    /** @var int self-grade: forgot, show again soon */
    const GRADE_AGAIN = 0;

    /** @var int self-grade: recalled with difficulty */
    const GRADE_HARD = 1;

    /** @var int self-grade: recalled correctly */
    const GRADE_GOOD = 2;

    /** @var int self-grade: recalled effortlessly */
    const GRADE_EASY = 3;

    /** @var string[] review-state names, shared by all schedulers */
    const STATE_NEW = 'new';
    /** @var string card is inside the short learning steps */
    const STATE_LEARNING = 'learning';
    /** @var string card graduated to day-based review intervals */
    const STATE_REVIEW = 'review';
    /** @var string card lapsed and is being relearned */
    const STATE_RELEARNING = 'relearning';

    /** @var int hard ceiling on any interval, in days */
    const MAX_INTERVAL_DAYS = 365;

    /** @var string[] scheduler identifier => implementing class */
    const TYPES = [
        'sm2' => sm2::class,
        'leitner' => leitner::class,
    ];

    /**
     * Resolve a scheduler by identifier (the flashdeck.scheduler column).
     *
     * @param string $identifier 'sm2' or 'leitner'; unknown values fall back to sm2
     * @return scheduler
     */
    public static function create(string $identifier): scheduler {
        $classname = self::TYPES[$identifier] ?? self::TYPES['sm2'];
        return new $classname();
    }

    /**
     * The registry identifier of this scheduler.
     *
     * @return string
     */
    abstract public function get_identifier(): string;

    /**
     * Apply a self-grade to a review state.
     *
     * Mutates and returns a copy; the caller persists it. Fields the
     * scheduler owns: easefactor, intervaldays, duedate, repetitions
     * (step index while learning; box number for Leitner), lapses,
     * state, lastgrade, lastreviewed.
     *
     * @param \stdClass $review current flashdeck_review state
     * @param int $grade one of the GRADE_* constants
     * @param int $now the review timestamp
     * @return \stdClass the updated state (a clone; caller persists)
     */
    public function grade(\stdClass $review, int $grade, int $now): \stdClass {
        if ($grade < self::GRADE_AGAIN || $grade > self::GRADE_EASY) {
            throw new \coding_exception("Invalid grade '{$grade}'");
        }
        $next = clone $review;
        $this->apply($next, $grade, $now);
        $next->intervaldays = min((int) $next->intervaldays, self::MAX_INTERVAL_DAYS);
        $next->easefactor = round((float) $next->easefactor, 2);
        $next->lastgrade = $grade;
        $next->lastreviewed = $now;
        return $next;
    }

    /**
     * Scheduler-specific grading logic; mutates $review in place.
     *
     * @param \stdClass $review state to update
     * @param int $grade one of the GRADE_* constants
     * @param int $now the review timestamp
     */
    abstract protected function apply(\stdClass $review, int $grade, int $now): void;

    /**
     * Predict the delay (in seconds from now) each grade would produce.
     *
     * Powers the interval hints on the four grade buttons, making the
     * scheduling visible to the learner: hard cards come back sooner.
     *
     * @param \stdClass $review current review state
     * @param int $now the timestamp previews are relative to
     * @return int[] grade constant => delay in seconds
     */
    public function preview(\stdClass $review, int $now): array {
        $previews = [];
        foreach ([self::GRADE_AGAIN, self::GRADE_HARD, self::GRADE_GOOD, self::GRADE_EASY] as $grade) {
            $next = $this->grade($review, $grade, $now);
            $previews[$grade] = max(0, (int) $next->duedate - $now);
        }
        return $previews;
    }

    /**
     * A blank review state for a card the user has never studied.
     *
     * @param int $deckid the flashdeck id
     * @param int $cardid the card id
     * @param int $userid the learner
     * @param int $now creation timestamp
     * @return \stdClass unsaved flashdeck_review record
     */
    public static function blank_review(int $deckid, int $cardid, int $userid, int $now): \stdClass {
        return (object) [
            'deckid' => $deckid,
            'cardid' => $cardid,
            'userid' => $userid,
            'easefactor' => 2.5,
            'intervaldays' => 0,
            'duedate' => $now,
            'repetitions' => 0,
            'lapses' => 0,
            'state' => self::STATE_NEW,
            'lastgrade' => null,
            'lastreviewed' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
    }
}
