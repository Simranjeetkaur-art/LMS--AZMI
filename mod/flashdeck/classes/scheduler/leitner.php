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
 * Leitner five-box scheduler: the lower-cognitive-load per-deck option.
 *
 * Cards live in boxes 1-5 with fixed intervals of 1, 2, 4, 8 and 16
 * days. Again sends the card back to box 1 (and, when it had left the
 * early boxes, counts a lapse and shows it again in 10 minutes); Hard
 * repeats the current box; Good moves up one box; Easy moves up two.
 * The ease factor is unused. The repetitions column holds the box
 * number (0 = never studied).
 *
 * States map from boxes for progress display: boxes 1-2 are
 * 'learning', boxes 3-5 are 'review'.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class leitner extends scheduler {

    /** @var int[] box number => interval in days */
    const BOX_INTERVALS = [1 => 1, 2 => 2, 3 => 4, 4 => 8, 5 => 16];

    /** @var int highest box */
    const MAX_BOX = 5;

    /** @var int first box considered 'review' rather than 'learning' */
    const REVIEW_BOX = 3;

    /** @var int delay before a forgotten card returns, in seconds (10 min) */
    const AGAIN_DELAY = 600;

    #[\Override]
    public function get_identifier(): string {
        return 'leitner';
    }

    #[\Override]
    protected function apply(\stdClass $review, int $grade, int $now): void {
        $box = (int) $review->repetitions;

        switch ($grade) {
            case self::GRADE_AGAIN:
                if ($box >= self::REVIEW_BOX) {
                    $review->lapses = (int) $review->lapses + 1;
                }
                $box = 1;
                // Forgotten cards return within the session, then daily.
                $review->repetitions = $box;
                $review->state = self::STATE_LEARNING;
                $review->intervaldays = 0;
                $review->duedate = $now + self::AGAIN_DELAY;
                return;

            case self::GRADE_HARD:
                $box = max(1, $box);
                break;

            case self::GRADE_GOOD:
                $box = min(self::MAX_BOX, $box + 1);
                break;

            case self::GRADE_EASY:
                $box = min(self::MAX_BOX, $box + 2);
                break;
        }

        $review->repetitions = $box;
        $review->state = ($box >= self::REVIEW_BOX) ? self::STATE_REVIEW : self::STATE_LEARNING;
        $review->intervaldays = self::BOX_INTERVALS[$box];
        $review->duedate = $now + $review->intervaldays * DAYSECS;
    }
}
