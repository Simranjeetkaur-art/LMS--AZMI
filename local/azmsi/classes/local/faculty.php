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

use core\context\course as context_course;
use moodle_url;

/**
 * Composes the faculty overview (S10) from LIVE Moodle data.
 *
 * Single source shared by the WS (local_azmsi_get_faculty_overview, website) and
 * the in-LMS faculty page (faculty.php) so figures are computed once and never
 * hardcoded. Every count is a query against core (enrolment, gradebook, mod_assign,
 * completion, calendar).
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class faculty {
    /**
     * Build the faculty overview for a teacher.
     *
     * @param int $userid teacher user id
     * @return array
     */
    public static function overview_for(int $userid): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $courses = enrol_get_all_users_courses($userid, true);
        $taught = [];
        $queuetotal = 0;
        $studenttotal = 0;
        $atrisktotal = 0;
        $ontracktotal = 0;

        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $context = context_course::instance($course->id);
            // Only courses this user can grade in (i.e. teaches).
            if (!has_capability('mod/assign:grade', $context, $userid)) {
                continue;
            }

            $students = self::student_count($context);
            $ungraded = self::ungraded_count($course, $context);
            [$avg, $ontrack, $atrisk] = self::class_health($course, $context);

            $studenttotal += $students;
            $queuetotal += $ungraded;
            $ontracktotal += $ontrack;
            $atrisktotal += $atrisk;

            $taught[] = [
                'id'       => (int) $course->id,
                'name'     => format_string($course->fullname),
                'code'     => (string) $course->idnumber,
                'students' => $students,
                'ungraded' => $ungraded,
                'classavg' => $avg,
                'hasavg'   => !is_null($avg),
                'manageurl' => (new moodle_url('/local/azmsi/instructor.php', ['courseid' => $course->id]))->out(false),
            ];
        }

        return [
            'coursecount'   => count($taught),
            'studenttotal'  => $studenttotal,
            'queuetotal'    => $queuetotal,
            'ontracktotal'  => $ontracktotal,
            'atrisktotal'   => $atrisktotal,
            'courses'       => $taught,
            'agenda'        => self::agenda($userid),
            'generatedon'   => time(),
        ];
    }

    /**
     * Count enrolled users who can submit assignments (≈ students) in a course.
     *
     * @param \context $context
     * @return int
     */
    protected static function student_count(\context $context): int {
        try {
            return count_enrolled_users($context, 'mod/assign:submit', 0, true);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Count ungraded, submitted assignment attempts across a course's assigns.
     *
     * @param \stdClass $course
     * @param \context $context
     * @return int
     */
    protected static function ungraded_count(\stdClass $course, \context $context): int {
        global $DB;
        try {
            $sql = "SELECT COUNT(s.id)
                      FROM {assign_submission} s
                      JOIN {assign} a ON a.id = s.assignment
                 LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid
                                            AND g.attemptnumber = s.attemptnumber
                     WHERE a.course = :courseid
                           AND s.latest = 1 AND s.status = :submitted
                           AND (g.grade IS NULL OR g.grade < 0 OR g.timemodified < s.timemodified)";
            return (int) $DB->count_records_sql($sql, ['courseid' => $course->id, 'submitted' => 'submitted']);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Class health: course grade average (percent) + on-track / at-risk counts.
     *
     * @param \stdClass $course
     * @param \context $context
     * @return array [?float $avgpercent, int $ontrack, int $atrisk]
     */
    protected static function class_health(\stdClass $course, \context $context): array {
        try {
            $item = \grade_item::fetch_course_item($course->id);
            if (!$item) {
                return [null, 0, 0];
            }
            $users = get_enrolled_users($context, 'mod/assign:submit', 0, 'u.id', null, 0, 0, true);
            $sum = 0;
            $graded = 0;
            $ontrack = 0;
            $atrisk = 0;
            foreach ($users as $u) {
                $grade = new \grade_grade(['itemid' => $item->id, 'userid' => $u->id], false);
                if ($grade && !is_null($grade->finalgrade) && $item->grademax > $item->grademin) {
                    $pct = ($grade->finalgrade - $item->grademin) / ($item->grademax - $item->grademin) * 100;
                    $sum += $pct;
                    $graded++;
                    if ($pct < 60) {
                        $atrisk++;
                    } else {
                        $ontrack++;
                    }
                }
            }
            return [$graded ? round($sum / $graded, 1) : null, $ontrack, $atrisk];
        } catch (\Throwable $e) {
            return [null, 0, 0];
        }
    }

    /**
     * Agenda: the teacher's upcoming calendar action events (next 2 weeks).
     *
     * @param int $userid
     * @return array list of ['name' => string, 'url' => string, 'due' => string]
     */
    protected static function agenda(int $userid): array {
        global $CFG, $USER;
        $out = [];
        try {
            require_once($CFG->dirroot . '/calendar/lib.php');
            if ($USER->id != $userid) {
                return $out;
            }
            $events = calendar_get_action_events_by_timesort(time(), time() + (2 * WEEKSECS), null, 8);
            foreach ($events as $event) {
                $action = $event->get_action();
                $duets = $event->get_times()->get_sort_time()->getTimestamp();
                $out[] = [
                    'name' => format_string($event->get_name()),
                    'url'  => $action ? $action->get_url()->out(false) : '',
                    'due'  => userdate($duets, get_string('strftimedatetimeshort', 'langconfig')),
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }
}
