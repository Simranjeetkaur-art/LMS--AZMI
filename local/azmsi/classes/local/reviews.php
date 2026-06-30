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

namespace local_azmsi\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Course + instructor ratings/reviews (Phase 3).
 *
 * One row per (course, learner) capturing a 1-5 star course rating and a 1-5 star
 * instructor rating, each with optional review text. Submissions start as
 * "pending"; only manager-approved rows count toward the displayed averages.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reviews {

    /** @var string Awaiting moderation. */
    const PENDING = 'pending';

    /** @var string Approved — counts toward public averages. */
    const APPROVED = 'approved';

    /** @var string Rejected by a moderator. */
    const REJECTED = 'rejected';

    /**
     * Create or update a learner's review for a course (resets to pending on edit).
     *
     * @param int $courseid
     * @param int $userid the learner
     * @param int $instructorid the instructor being rated (0 if none)
     * @param int $coursestars 0-5 (0 = not given)
     * @param string $coursereview
     * @param int $instructorstars 0-5
     * @param string $instructorreview
     * @return void
     */
    public static function save(int $courseid, int $userid, int $instructorid,
            int $coursestars, string $coursereview, int $instructorstars, string $instructorreview): void {
        global $DB;

        $now = time();
        $coursestars = self::clamp($coursestars);
        $instructorstars = self::clamp($instructorstars);

        $existing = $DB->get_record('local_azmsi_review', ['courseid' => $courseid, 'userid' => $userid]);
        $record = (object) [
            'courseid'         => $courseid,
            'userid'           => $userid,
            'instructorid'     => $instructorid,
            'coursestars'      => $coursestars,
            'coursereview'     => $coursereview,
            'instructorstars'  => $instructorstars,
            'instructorreview' => $instructorreview,
            'status'           => self::PENDING, // Any edit re-enters moderation.
            'approvedby'       => 0,
            'timeapproved'     => 0,
            'timemodified'     => $now,
        ];
        if ($existing) {
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $DB->update_record('local_azmsi_review', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_azmsi_review', $record);
        }
    }

    /**
     * Clamp a star value to 0-5.
     *
     * @param int $stars
     * @return int
     */
    protected static function clamp(int $stars): int {
        return max(0, min(5, $stars));
    }

    /**
     * A learner's own review row for a course, or null.
     *
     * @param int $courseid
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get_user_review(int $courseid, int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_azmsi_review', ['courseid' => $courseid, 'userid' => $userid]) ?: null;
    }

    /**
     * Approved course rating average + count.
     *
     * @param int $courseid
     * @return array ['avg' => float, 'count' => int, 'has' => bool]
     */
    public static function course_rating(int $courseid): array {
        global $DB;
        $sql = "SELECT AVG(coursestars) AS avg, COUNT(*) AS cnt
                  FROM {local_azmsi_review}
                 WHERE courseid = :cid AND status = :st AND coursestars > 0";
        $r = $DB->get_record_sql($sql, ['cid' => $courseid, 'st' => self::APPROVED]);
        $count = (int) ($r->cnt ?? 0);
        return ['avg' => $count ? round((float) $r->avg, 1) : 0.0, 'count' => $count, 'has' => $count > 0];
    }

    /**
     * Approved instructor rating average + count (across all the instructor's courses).
     *
     * @param int $instructorid
     * @return array ['avg' => float, 'count' => int, 'has' => bool]
     */
    public static function instructor_rating(int $instructorid): array {
        global $DB;
        if (!$instructorid) {
            return ['avg' => 0.0, 'count' => 0, 'has' => false];
        }
        $sql = "SELECT AVG(instructorstars) AS avg, COUNT(*) AS cnt
                  FROM {local_azmsi_review}
                 WHERE instructorid = :iid AND status = :st AND instructorstars > 0";
        $r = $DB->get_record_sql($sql, ['iid' => $instructorid, 'st' => self::APPROVED]);
        $count = (int) ($r->cnt ?? 0);
        return ['avg' => $count ? round((float) $r->avg, 1) : 0.0, 'count' => $count, 'has' => $count > 0];
    }

    /**
     * Reviews awaiting moderation, newest first, with rater/course names resolved.
     *
     * @param int $limit
     * @return array list of enriched review rows
     */
    public static function pending(int $limit = 100): array {
        return self::by_status(self::PENDING, $limit);
    }

    /**
     * Reviews with the given status, newest first.
     *
     * @param string $status
     * @param int $limit
     * @return array
     */
    public static function by_status(string $status, int $limit = 100): array {
        global $DB;
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $sql = "SELECT r.*, c.fullname AS coursename, $namefields
                  FROM {local_azmsi_review} r
                  JOIN {course} c ON c.id = r.courseid
                  JOIN {user} u ON u.id = r.userid
                 WHERE r.status = :st
              ORDER BY r.timemodified DESC";
        $rows = $DB->get_records_sql($sql, ['st' => $status], 0, $limit);
        foreach ($rows as $row) {
            $row->ratername = fullname($row);
        }
        return $rows;
    }

    /**
     * Count of reviews awaiting moderation.
     *
     * @return int
     */
    public static function count_pending(): int {
        global $DB;
        return $DB->count_records('local_azmsi_review', ['status' => self::PENDING]);
    }

    /**
     * Set a review's moderation status.
     *
     * @param int $id
     * @param string $status approved|rejected|pending
     * @param int $moderatorid
     * @return void
     */
    public static function set_status(int $id, string $status, int $moderatorid): void {
        global $DB;
        if (!in_array($status, [self::APPROVED, self::REJECTED, self::PENDING], true)) {
            throw new \coding_exception('Invalid review status: ' . $status);
        }
        $record = $DB->get_record('local_azmsi_review', ['id' => $id], '*', MUST_EXIST);
        $record->status = $status;
        $record->approvedby = ($status === self::APPROVED) ? $moderatorid : 0;
        $record->timeapproved = ($status === self::APPROVED) ? time() : 0;
        $record->timemodified = time();
        $DB->update_record('local_azmsi_review', $record);
    }
}
