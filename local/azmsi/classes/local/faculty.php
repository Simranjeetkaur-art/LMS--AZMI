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
use core_course\customfield\course_handler;
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
     * Courses this user is assigned to teach (course-context teacher roles only).
     *
     * @param int $userid
     * @return \stdClass[] course records keyed by id then reindexed
     */
    public static function taught_courses(int $userid): array {
        global $DB;

        $sql = "SELECT DISTINCT c.*
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :courselevel
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = :userid
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE r.archetype IN ('editingteacher', 'teacher')
                       AND c.id <> :siteid
              ORDER BY c.sortorder, c.fullname";

        return array_values($DB->get_records_sql($sql, [
            'courselevel' => CONTEXT_COURSE,
            'userid'      => $userid,
            'siteid'      => SITEID,
        ]));
    }

    /**
     * Whether the user is assigned to teach a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function teaches_course(int $userid, int $courseid): bool {
        foreach (self::taught_courses($userid) as $course) {
            if ((int) $course->id === $courseid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the user can add or edit activities in an assigned course.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function can_manage_course_content(int $userid, int $courseid): bool {
        if (!self::teaches_course($userid, $courseid)) {
            return false;
        }
        $context = context_course::instance($courseid);
        return has_capability('moodle/course:manageactivities', $context, $userid);
    }

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

        $user = \core_user::get_user($userid, 'id, firstname, lastname', IGNORE_MISSING);
        $handler = course_handler::create();
        $courses = self::taught_courses($userid);
        $taught = [];
        $queuetotal = 0;
        $studenttotal = 0;
        $atrisktotal = 0;
        $ontracktotal = 0;
        $quizsum = 0;
        $quizcount = 0;

        foreach ($courses as $course) {
            $context = context_course::instance($course->id);
            $fields = [];
            foreach ($handler->get_instance_data($course->id, true) as $d) {
                $fields[$d->get_field()->get('shortname')] = $d->export_value();
            }
            $week = self::course_week($course, $fields);
            $students = self::student_count($context);
            $ungraded = self::ungraded_count($course, $context);
            [$avg, $ontrack, $atrisk] = self::class_health($course, $context);
            $quizavg = self::quiz_average($course);

            $studenttotal += $students;
            $queuetotal += $ungraded;
            $ontracktotal += $ontrack;
            $atrisktotal += $atrisk;
            if (!is_null($quizavg)) {
                $quizsum += $quizavg;
                $quizcount++;
            }

            $statuskey = $ungraded > 0 ? 'coursestatuspending' : 'coursestatusdiscussions';
            $taught[] = [
                'id'         => (int) $course->id,
                'name'       => format_string($course->fullname, true, ['context' => $context]),
                'code'       => (string) ($fields['course_code'] ?? $course->idnumber),
                'students'   => $students,
                'ungraded'   => $ungraded,
                'classavg'   => $avg,
                'hasavg'     => !is_null($avg),
                'week'       => $week,
                'weeklabel'  => get_string('weekxofy', 'local_azmsi', (object) ['week' => $week, 'total' => 10]),
                'status'     => get_string($statuskey, 'local_azmsi'),
                'manageurl'  => (new moodle_url('/local/azmsi/instructor.php', ['courseid' => $course->id]))->out(false),
                'buildurl'   => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'canbuild'   => self::can_manage_course_content($userid, $course->id),
            ];
        }

        $queue = self::grading_queue($courses);
        $livecount = self::live_sessions_this_week($userid);
        $quizavgall = $quizcount ? round($quizsum / $quizcount, 1) : null;

        return [
            'firstname'     => $user ? $user->firstname : '',
            'greeting'      => self::greeting($user ? $user->firstname : ''),
            'summary'       => get_string('facultysummary', 'local_azmsi', (object) [
                'queue' => $queuetotal,
                'live'  => $livecount,
            ]),
            'coursecount'   => count($taught),
            'studenttotal'  => $studenttotal,
            'queuetotal'    => $queuetotal,
            'ontracktotal'  => $ontracktotal,
            'atrisktotal'   => $atrisktotal,
            'quizavg'       => $quizavgall,
            'hasquizavg'    => !is_null($quizavgall),
            'ontrackpct'    => $studenttotal ? (int) round($ontracktotal / $studenttotal * 100) : 0,
            'courses'       => $taught,
            'queue'         => $queue,
            'agenda'        => self::agenda($userid),
            'generatedon'   => time(),
        ];
    }

    /**
     * Time-of-day greeting for the faculty header.
     *
     * @param string $firstname
     * @return string
     */
    protected static function greeting(string $firstname): string {
        $hour = (int) userdate(time(), '%H');
        if ($hour < 12) {
            $key = 'greetingmorning';
        } else if ($hour < 17) {
            $key = 'greetingafternoon';
        } else {
            $key = 'greetingevening';
        }
        return get_string($key, 'local_azmsi', $firstname);
    }

    /**
     * Cross-course grading queue (ungraded assignment submissions).
     *
     * @param array $courses taught course records
     * @return array
     */
    protected static function grading_queue(array $courses): array {
        global $DB;
        if (empty($courses)) {
            return [];
        }

        $courseids = array_map(static fn($c) => (int) $c->id, $courses);
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $params['submitted'] = 'submitted';

        try {
            $sql = "SELECT s.id, s.userid, s.timemodified, a.name AS assignname, a.course AS courseid,
                           cm.id AS cmid, u.firstname, u.lastname, c.idnumber, c.shortname
                      FROM {assign_submission} s
                      JOIN {assign} a ON a.id = s.assignment
                      JOIN {course} c ON c.id = a.course
                      JOIN {course_modules} cm ON cm.instance = a.id
                      JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                      JOIN {user} u ON u.id = s.userid
                 LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid
                                            AND g.attemptnumber = s.attemptnumber
                     WHERE a.course $insql
                           AND s.latest = 1 AND s.status = :submitted
                           AND (g.grade IS NULL OR g.grade < 0 OR g.timemodified < s.timemodified)
                  ORDER BY s.timemodified ASC";

            $rows = $DB->get_records_sql($sql, $params, 0, 12);
            $out = [];
            foreach ($rows as $r) {
                $graderurl = new moodle_url('/mod/assign/view.php', [
                    'id'     => $r->cmid,
                    'action' => 'grader',
                    'userid' => $r->userid,
                ]);
                $out[] = [
                    'student'    => fullname($r),
                    'assign'     => format_string($r->assignname),
                    'coursecode' => (string) $r->idnumber,
                    'typelabel'  => get_string('assignment', 'local_azmsi'),
                    'typeclass'  => 'assignment',
                    'timeago'    => self::time_ago((int) $r->timemodified),
                    'gradeurl'   => $graderurl->out(false),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Human-readable relative time.
     *
     * @param int $timestamp
     * @return string
     */
    protected static function time_ago(int $timestamp): string {
        return format_time(time() - $timestamp);
    }

    /**
     * Count upcoming calendar events in the next 7 days (live sessions / deadlines).
     *
     * @param int $userid
     * @return int
     */
    protected static function live_sessions_this_week(int $userid): int {
        global $CFG, $USER;
        try {
            require_once($CFG->dirroot . '/calendar/lib.php');
            if ($USER->id != $userid) {
                return 0;
            }
            $events = calendar_get_action_events_by_timesort(time(), time() + WEEKSECS, null, 50);
            return count($events);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Average quiz score (percent) across a course.
     *
     * @param \stdClass $course
     * @return float|null
     */
    protected static function quiz_average(\stdClass $course): ?float {
        global $DB;
        try {
            $sql = "SELECT AVG((gg.finalgrade - gi.grademin) / NULLIF(gi.grademax - gi.grademin, 0) * 100)
                      FROM {grade_grades} gg
                      JOIN {grade_items} gi ON gi.id = gg.itemid
                     WHERE gi.courseid = :courseid
                           AND gi.itemmodule = 'quiz'
                           AND gg.finalgrade IS NOT NULL
                           AND gi.grademax > gi.grademin";
            $avg = $DB->get_field_sql($sql, ['courseid' => $course->id]);
            return is_null($avg) ? null : round((float) $avg, 1);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Current course week from custom fields or start date.
     *
     * @param \stdClass $course
     * @param array $fields
     * @return int
     */
    protected static function course_week(\stdClass $course, array $fields): int {
        if (!empty($fields['current_week'])) {
            return max(1, (int) $fields['current_week']);
        }
        if (!empty($course->startdate) && $course->startdate <= time()) {
            return max(1, min(12, (int) floor((time() - $course->startdate) / WEEKSECS) + 1));
        }
        return 1;
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
