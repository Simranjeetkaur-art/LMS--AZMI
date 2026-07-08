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

use mod_flashdeck\cardtype\manager as cardtype_manager;
use mod_flashdeck\scheduler\scheduler;

/**
 * The study loop: queue building, grading and progress.
 *
 * Single source of truth shared by the AJAX external functions and the
 * no-JavaScript form fallback in view.php, so scheduling has exactly
 * one server-side code path. The client never computes an interval.
 *
 * Queue priority per user: (1) learning/relearning cards that are due,
 * (2) review cards that are due, (3) new cards up to the deck's
 * new-per-day limit, (4) learning cards due within the learn-ahead
 * window so a session can finish its short steps.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /** @var int serve learning cards early by up to this many seconds when idle */
    const LEARN_AHEAD_SECS = 1200;

    /**
     * Apply a self-grade to a card for a user and persist the new state.
     *
     * @param \stdClass $deck the flashdeck record
     * @param \stdClass $card the flashdeck_cards record (must belong to $deck)
     * @param int $userid the learner
     * @param int $grade scheduler::GRADE_* value
     * @param \context_module $context for the event
     * @param int|null $now review timestamp, defaults to now
     * @return \stdClass the persisted flashdeck_review record
     */
    public static function grade_card(\stdClass $deck, \stdClass $card, int $userid, int $grade,
            \context_module $context, ?int $now = null): \stdClass {
        global $DB;

        if ((int) $card->deckid !== (int) $deck->id) {
            throw new \coding_exception('Card does not belong to this deck');
        }
        if ($grade < scheduler::GRADE_AGAIN || $grade > scheduler::GRADE_EASY) {
            throw new \moodle_exception('errinvalidgrade', 'mod_flashdeck');
        }
        $now = $now ?? time();

        $review = $DB->get_record('flashdeck_review', ['cardid' => $card->id, 'userid' => $userid]);
        if (!$review) {
            $review = scheduler::blank_review($deck->id, $card->id, $userid, $now);
        }

        $next = scheduler::create($deck->scheduler)->grade($review, $grade, $now);
        $next->timemodified = $now;

        if (empty($next->id)) {
            try {
                $next->id = $DB->insert_record('flashdeck_review', $next);
            } catch (\dml_exception $e) {
                // Lost a create race (unique cardid-userid index); merge onto the winner.
                $existing = $DB->get_record('flashdeck_review',
                    ['cardid' => $card->id, 'userid' => $userid], '*', MUST_EXIST);
                $next->id = $existing->id;
                $next->timecreated = $existing->timecreated;
                $DB->update_record('flashdeck_review', $next);
            }
        } else {
            $DB->update_record('flashdeck_review', $next);
        }

        $event = \mod_flashdeck\event\card_reviewed::create([
            'objectid' => $next->id,
            'context' => $context,
            'relateduserid' => $userid,
            'other' => [
                'cardid' => (int) $card->id,
                'grade' => $grade,
                'state' => $next->state,
                'intervaldays' => (int) $next->intervaldays,
            ],
        ]);
        $event->trigger();

        return $next;
    }

    /**
     * Pick the next card the user should study, with its review state.
     *
     * @param \stdClass $deck the flashdeck record
     * @param int $userid the learner
     * @param int|null $now timestamp, defaults to now
     * @return array [card record|null, review record|null]
     */
    public static function get_next_due(\stdClass $deck, int $userid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $params = ['deckid' => $deck->id, 'userid' => $userid];

        // 1. Learning steps that have come due (most urgent: short-term memory).
        $review = $DB->get_record_sql(
            "SELECT r.* FROM {flashdeck_review} r
              WHERE r.deckid = :deckid AND r.userid = :userid
                    AND r.state IN ('learning', 'relearning') AND r.duedate <= :now
           ORDER BY r.duedate ASC, r.id ASC",
            $params + ['now' => $now], IGNORE_MULTIPLE);

        // 2. Graduated reviews that are due, most overdue first.
        if (!$review) {
            $review = $DB->get_record_sql(
                "SELECT r.* FROM {flashdeck_review} r
                  WHERE r.deckid = :deckid AND r.userid = :userid
                        AND r.state = 'review' AND r.duedate <= :now
               ORDER BY r.duedate ASC, r.id ASC",
                $params + ['now' => $now], IGNORE_MULTIPLE);
        }

        // 3. A new card, if today's introduction budget allows.
        if (!$review && self::count_introduced_today($deck, $userid, $now) < (int) $deck->newperday) {
            $card = $DB->get_record_sql(
                "SELECT c.* FROM {flashdeck_cards} c
             LEFT JOIN {flashdeck_review} r ON r.cardid = c.id AND r.userid = :userid
                 WHERE c.deckid = :deckid AND r.id IS NULL
              ORDER BY c.position ASC, c.id ASC",
                $params, IGNORE_MULTIPLE);
            if ($card) {
                return [$card, null];
            }
        }

        // 4. Nothing strictly due: pull learning steps due shortly so the
        // session can finish what it started (Anki-style learn-ahead).
        if (!$review) {
            $review = $DB->get_record_sql(
                "SELECT r.* FROM {flashdeck_review} r
                  WHERE r.deckid = :deckid AND r.userid = :userid
                        AND r.state IN ('learning', 'relearning') AND r.duedate <= :horizon
               ORDER BY r.duedate ASC, r.id ASC",
                $params + ['horizon' => $now + self::LEARN_AHEAD_SECS], IGNORE_MULTIPLE);
        }

        if (!$review) {
            return [null, null];
        }

        $card = $DB->get_record('flashdeck_cards', ['id' => $review->cardid], '*', MUST_EXIST);
        return [$card, $review];
    }

    /**
     * How many new cards were introduced to this user today (their timezone).
     *
     * @param \stdClass $deck the flashdeck record
     * @param int $userid the learner
     * @param int $now timestamp
     * @return int
     */
    public static function count_introduced_today(\stdClass $deck, int $userid, int $now): int {
        global $DB;
        return $DB->count_records_select('flashdeck_review',
            'deckid = :deckid AND userid = :userid AND timecreated >= :daystart',
            ['deckid' => $deck->id, 'userid' => $userid, 'daystart' => usergetmidnight($now)]);
    }

    /**
     * Queue and progress counts for a user in a deck.
     *
     * @param \stdClass $deck the flashdeck record
     * @param int $userid the learner
     * @param int|null $now timestamp, defaults to now
     * @return array total, seen, new, learning, review, duenow, introducedtoday, newremaining
     */
    public static function get_counts(\stdClass $deck, int $userid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $params = ['deckid' => $deck->id, 'userid' => $userid];

        $total = $DB->count_records('flashdeck_cards', ['deckid' => $deck->id]);
        $seen = $DB->count_records('flashdeck_review', $params);
        $learning = $DB->count_records_select('flashdeck_review',
            "deckid = :deckid AND userid = :userid AND state IN ('learning', 'relearning')", $params);
        $review = $DB->count_records_select('flashdeck_review',
            "deckid = :deckid AND userid = :userid AND state = 'review'", $params);
        $duenow = $DB->count_records_select('flashdeck_review',
            'deckid = :deckid AND userid = :userid AND duedate <= :now', $params + ['now' => $now]);
        $introducedtoday = self::count_introduced_today($deck, $userid, $now);
        $newremaining = max(0, min((int) $deck->newperday - $introducedtoday, $total - $seen));

        return [
            'total' => $total,
            'seen' => $seen,
            'new' => $total - $seen,
            'learning' => $learning,
            'review' => $review,
            'duenow' => $duenow,
            'introducedtoday' => $introducedtoday,
            'newremaining' => $newremaining,
        ];
    }

    /**
     * When the user's next review becomes due, if anything is scheduled.
     *
     * @param \stdClass $deck the flashdeck record
     * @param int $userid the learner
     * @param int $now timestamp
     * @return int timestamp, or 0 when nothing is scheduled
     */
    public static function next_due_after(\stdClass $deck, int $userid, int $now): int {
        global $DB;
        return (int) $DB->get_field_sql(
            "SELECT MIN(duedate) FROM {flashdeck_review}
              WHERE deckid = :deckid AND userid = :userid AND duedate > :now",
            ['deckid' => $deck->id, 'userid' => $userid, 'now' => $now]);
    }

    /**
     * Build the study payload: the next card (rendered), grade previews
     * and counts. Shared by the external functions and the learn page.
     *
     * @param \stdClass $deck the flashdeck record
     * @param \context_module $context module context
     * @param int $userid the learner
     * @param \renderer_base $output for template rendering
     * @param int|null $now timestamp, defaults to now
     * @return array payload matching get_next_due_card::execute_returns()
     */
    public static function export_next_card(\stdClass $deck, \context_module $context, int $userid,
            \renderer_base $output, ?int $now = null): array {
        $now = $now ?? time();
        [$card, $review] = self::get_next_due($deck, $userid, $now);
        $counts = self::get_counts($deck, $userid, $now);

        $payload = [
            'done' => $card === null,
            'counts' => [
                'duenow' => $counts['duenow'],
                'learning' => $counts['learning'],
                'newremaining' => $counts['newremaining'],
                'total' => $counts['total'],
            ],
            'nextdue' => 0,
            'nextduelabel' => '',
        ];

        if ($card === null) {
            if ($nextdue = self::next_due_after($deck, $userid, $now)) {
                $payload['nextdue'] = $nextdue;
                $payload['nextduelabel'] = userdate($nextdue, get_string('strftimedatetimeshort', 'langconfig'));
            }
            return $payload;
        }

        $review = $review ?? scheduler::blank_review($deck->id, $card->id, $userid, $now);
        $previews = scheduler::create($deck->scheduler)->preview($review, $now);
        $type = cardtype_manager::get($card->cardtype);

        $payload['cardid'] = (int) $card->id;
        $payload['cardtype'] = $card->cardtype;
        $payload['state'] = $review->state;
        $payload['cardhtml'] = $output->render_from_template($type->get_template(),
            $type->export_for_template($card, $context));
        $payload['previews'] = [
            'again' => self::format_delay($previews[scheduler::GRADE_AGAIN]),
            'hard' => self::format_delay($previews[scheduler::GRADE_HARD]),
            'good' => self::format_delay($previews[scheduler::GRADE_GOOD]),
            'easy' => self::format_delay($previews[scheduler::GRADE_EASY]),
        ];
        return $payload;
    }

    /**
     * Human-compact delay label for grade-button previews.
     *
     * @param int $seconds delay from now
     * @return string e.g. "1 min", "10 min", "1 day", "3 days"
     */
    public static function format_delay(int $seconds): string {
        if ($seconds < DAYSECS) {
            return get_string('previewminutes', 'mod_flashdeck', max(1, (int) round($seconds / MINSECS)));
        }
        $days = max(1, (int) round($seconds / DAYSECS));
        return $days === 1
            ? get_string('previewday', 'mod_flashdeck')
            : get_string('previewdays', 'mod_flashdeck', $days);
    }
}
