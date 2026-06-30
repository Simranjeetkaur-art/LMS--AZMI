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

use core_course\customfield\course_handler;
use moodle_url;

/**
 * Composes the student dashboard overview (S4) from LIVE Moodle data.
 *
 * Single source of truth shared by the WS function (local_azmsi_get_student_overview,
 * for the website) and the dashboard block (block_azmsi_dashboard, in-LMS) so the
 * numbers are computed once and never recomputed/hardcoded. Read from cache_azmsi
 * when warm; observers/tasks invalidate it (01_ARCHITECTURE §4/§5).
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview {
    /**
     * Build (or fetch cached) the overview for a user.
     *
     * @param int $userid
     * @param bool $usecache read/write the rollups cache
     * @return array
     */
    public static function for_user(int $userid, bool $usecache = true): array {
        $cache = \cache::make('local_azmsi', 'rollups');
        $key = 'overview_' . $userid;
        if ($usecache && ($cached = $cache->get($key)) !== false) {
            return $cached;
        }

        $data = self::compose($userid);

        if ($usecache) {
            $cache->set($key, $data);
        }
        return $data;
    }

    /**
     * Compose the overview from core APIs (completion, gradebook, calendar,
     * customfields). Every read is guarded so a sparse account never errors.
     *
     * @param int $userid
     * @return array
     */
    protected static function compose(int $userid): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $user = \core_user::get_user($userid, '*', IGNORE_MISSING);
        $courses = enrol_get_all_users_courses($userid, true);
        $handler = course_handler::create();

        $courseout = [];
        $sum = 0;
        $graded = 0;
        $continuecourseid = 0;
        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $fields = [];
            foreach ($handler->get_instance_data($course->id, true) as $d) {
                $fields[$d->get_field()->get('shortname')] = $d->export_value();
            }
            $pct = \core_completion\progress::get_course_progress_percentage($course, $userid);
            $progress = is_null($pct) ? 0 : (int) round($pct);
            $context = \core\context\course::instance($course->id);
            $instructor = self::course_instructor($context);

            // Per-course grade (percent) for the average.
            $gradepct = null;
            if ($item = \grade_item::fetch_course_item($course->id)) {
                $grade = new \grade_grade(['itemid' => $item->id, 'userid' => $userid], false);
                if ($grade && !is_null($grade->finalgrade) && $item->grademax > $item->grademin) {
                    $gradepct = ($grade->finalgrade - $item->grademin) / ($item->grademax - $item->grademin) * 100;
                    $sum += $gradepct;
                    $graded++;
                }
            }

            $courseout[] = [
                'id'         => (int) $course->id,
                'name'       => format_string($course->fullname),
                'code'       => (string) ($fields['course_code'] ?? $course->idnumber),
                'credits'    => isset($fields['credits']) ? (int) $fields['credits'] : 0,
                'quarter'    => isset($fields['quarter']) ? (int) $fields['quarter'] : 0,
                'status'     => ($fields['status'] ?? '') === 'in_progress' ? 'in_progress' : 'planned',
                'progress'   => $progress,
                'url'        => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'instructor' => $instructor,
                'week'       => self::course_week($course, $fields),
            ];
        }

        $programmap = self::program_map($courseout);
        $currentquarter = self::current_quarter($programmap);
        $continue = self::continue_card($userid, $courses, $courseout);
        if (!empty($continue['courseid'])) {
            $continuecourseid = (int) $continue['courseid'];
        }
        foreach ($courseout as &$c) {
            $c['isprimary'] = $continuecourseid && (int) $c['id'] === $continuecourseid;
        }
        unset($c);

        $inquarter = array_values(array_filter($courseout, static function ($c) use ($currentquarter) {
            return $currentquarter && (int) $c['quarter'] === $currentquarter;
        }));
        if (!$inquarter) {
            $inquarter = $courseout;
        }

        $journey = self::journey($userid, $programmap, $courseout, $currentquarter);

        return [
            'fullname'          => $user ? fullname($user) : '',
            'firstname'         => $user ? $user->firstname : '',
            'average'           => $graded ? round($sum / $graded, 1) : 0.0,
            'courses'           => $inquarter,
            'allcourses'        => $courseout,
            'continue'          => $continue,
            'dueweek'           => self::due_this_week($userid),
            'programmap'        => $programmap,
            'journey'           => $journey,
            'currentquarter'    => $currentquarter,
            'programsubtitle'   => self::program_subtitle($currentquarter, $continue),
            'inprogressmeta'    => self::inprogress_meta($inquarter, $currentquarter),
            'coursecount'       => count($inquarter),
            'modulescompleted'  => self::modules_completed($userid),
            'generatedon'       => time(),
        ];
    }

    /**
     * Lead instructor for a course (first editing teacher), or empty string.
     *
     * @param \context $context
     * @return string
     */
    protected static function course_instructor(\context $context): string {
        try {
            $teachers = get_enrolled_users(
                $context,
                'moodle/course:manageactivities',
                0,
                'u.*',
                'u.lastname ASC',
                0,
                1,
                true
            );
            $t = reset($teachers);
            return $t ? fullname($t) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Current instructional week for a course (custom field or derived from start date).
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
     * Count of completed activity modules across all enrolled courses.
     *
     * @param int $userid
     * @return int
     */
    protected static function modules_completed(int $userid): int {
        global $DB;
        try {
            return (int) $DB->count_records_sql(
                "SELECT COUNT(cmc.id)
                   FROM {course_modules_completion} cmc
                   JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                  WHERE cmc.userid = :uid AND cmc.completionstate > 0",
                ['uid' => $userid]
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Current quarter number from the program map (first in-progress quarter).
     *
     * @param array $programmap
     * @return int
     */
    protected static function current_quarter(array $programmap): int {
        foreach ($programmap as $q) {
            if (!empty($q['current'])) {
                return (int) $q['quarter'];
            }
        }
        return 0;
    }

    /**
     * Subtitle under the greeting (program + quarter + week).
     *
     * @param int $currentquarter
     * @param array $continue
     * @return string
     */
    protected static function program_subtitle(int $currentquarter, array $continue): string {
        $week = (int) ($continue['week'] ?? 0);
        if ($currentquarter && $week) {
            return get_string('programsubtitle', 'block_azmsi_dashboard', (object) [
                'quarter' => $currentquarter,
                'week'    => $week,
            ]);
        }
        if ($currentquarter) {
            return get_string('programsubtitlequarter', 'block_azmsi_dashboard', $currentquarter);
        }
        return get_string('programsubtitledefault', 'block_azmsi_dashboard');
    }

    /**
     * Meta line for the in-progress section header.
     *
     * @param array $courses
     * @param int $currentquarter
     * @return string
     */
    protected static function inprogress_meta(array $courses, int $currentquarter): string {
        $count = count($courses);
        $credits = 0;
        foreach ($courses as $c) {
            $credits = max($credits, (int) $c['credits']);
        }
        if ($currentquarter && $count) {
            return get_string('inprogressmeta', 'block_azmsi_dashboard', (object) [
                'quarter'  => $currentquarter,
                'count'    => $count,
                'credits'  => $credits ?: 4,
            ]);
        }
        return '';
    }

    /**
     * eMD Journey widget: labelled quarter chips, program progress, next live class.
     *
     * @param array $programmap
     * @param array $courses
     * @param int $currentquarter
     * @return array
     */
    protected static function journey(int $userid, array $programmap, array $courses, int $currentquarter): array {
        $doneq = 0;
        $quarters = [];
        foreach ($programmap as $q) {
            $label = 'Q' . $q['quarter'];
            $highlight = false;
            if ($q['status'] === 'done') {
                $doneq++;
            }
            if (!empty($q['current']) && $q['status'] === 'in_progress') {
                $label = get_string('quarterinprogress', 'block_azmsi_dashboard', $q['quarter']);
                $highlight = true;
            }
            $quarters[] = [
                'quarter'   => $q['quarter'],
                'label'     => $label,
                'status'    => $q['status'],
                'highlight' => $highlight,
            ];
        }

        $currentcourses = array_filter($courses, static fn($c) => $currentquarter && (int) $c['quarter'] === $currentquarter);
        $avg = 0;
        if ($currentcourses) {
            $avg = array_sum(array_column($currentcourses, 'progress')) / count($currentcourses);
        }
        $pct = (int) round(min(100, ($doneq * 100 + $avg) / 12));

        $live = self::next_live_class($userid);
        return [
            'quarters'            => $quarters,
            'programprogress'     => $pct,
            'programprogresslabel' => $currentquarter
                ? get_string('programprogresslabel', 'block_azmsi_dashboard', (object) [
                    'quarter' => $currentquarter,
                    'pct'     => $pct,
                ]) : get_string('programprogressnone', 'block_azmsi_dashboard'),
            'nextliveclass'       => $live,
            'hasnextliveclass'    => !empty($live['has']),
        ];
    }

    /**
     * The continue card: the user's most-recently-viewed activity (logstore),
     * falling back to the most-recently-accessed course.
     *
     * @param int $userid
     * @param array $courses enrolled courses
     * @param array $courseout composed course rows (for codes/weeks)
     * @return array
     */
    protected static function continue_card(int $userid, array $courses, array $courseout = []): array {
        global $DB;
        $none = [
            'has' => false, 'name' => '', 'coursename' => '', 'coursecode' => '',
            'url' => '', 'courseid' => 0, 'week' => 0, 'progresstext' => '',
            'resumelabel' => '',
        ];
        $codes = [];
        foreach ($courseout as $c) {
            $codes[(int) $c['id']] = $c;
        }

        $build = static function (int $courseid, string $name, string $url, ?int $sectionnum = null) use ($codes, $userid): array {
            $meta = $codes[$courseid] ?? [];
            $course = get_course($courseid);
            $week = (int) ($meta['week'] ?? self::course_week($course, []));
            if ($sectionnum) {
                $week = max($week, $sectionnum);
            }
            $code = (string) ($meta['code'] ?? $course->idnumber);
            $secprog = $sectionnum ? self::section_progress($courseid, $userid, $sectionnum) : ['done' => 0, 'total' => 0];
            $progresstext = $secprog['total']
                ? get_string('continueprogress', 'block_azmsi_dashboard', (object) $secprog) : '';
            $title = $sectionnum
                ? get_string('continuetitleweek', 'block_azmsi_dashboard', (object) [
                    'week' => $week,
                    'name' => $name,
                ]) : $name;
            return [
                'has'          => true,
                'name'         => $title,
                'coursename'   => format_string($course->fullname),
                'coursecode'   => $code,
                'url'          => $url,
                'courseid'     => $courseid,
                'week'         => $week,
                'progresstext' => $progresstext,
                'resumelabel'  => get_string('resumeweek', 'block_azmsi_dashboard', $week),
            ];
        };

        // Last viewed course module from the standard log (guarded; log may be off).
        try {
            $sql = "SELECT cm.id AS cmid, cm.course, l.timecreated
                      FROM {logstore_standard_log} l
                      JOIN {course_modules} cm ON cm.id = l.contextinstanceid
                     WHERE l.userid = :uid AND l.contextlevel = :ctx AND l.crud = 'r'
                  ORDER BY l.timecreated DESC";
            $rows = $DB->get_records_sql($sql, ['uid' => $userid, 'ctx' => CONTEXT_MODULE], 0, 1);
            if ($rows) {
                $r = reset($rows);
                $modinfo = get_fast_modinfo($r->course, $userid);
                if (!empty($modinfo->cms[$r->cmid])) {
                    $cm = $modinfo->cms[$r->cmid];
                    if ($cm->uservisible) {
                        $url = $cm->url ? $cm->url->out(false)
                            : (new moodle_url('/course/view.php', ['id' => $r->course]))->out(false);
                        return $build($r->course, format_string($cm->name), $url, (int) $cm->sectionnum);
                    }
                }
            }
        } catch (\Throwable $e) {
            debugging('local_azmsi continue_card log lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Fallback: most-recently-accessed enrolled course.
        $best = null;
        $besttime = 0;
        foreach ($courses as $c) {
            if ($c->id == SITEID) {
                continue;
            }
            $params = ['userid' => $userid, 'courseid' => $c->id];
            $t = (int) $DB->get_field('user_lastaccess', 'timeaccess', $params);
            if ($t >= $besttime) {
                $besttime = $t;
                $best = $c;
            }
        }
        if ($best) {
            return $build(
                (int) $best->id,
                format_string($best->fullname),
                (new moodle_url('/course/view.php', ['id' => $best->id]))->out(false)
            );
        }
        return $none;
    }

    /**
     * Completion counts for trackable activities in one course section.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $sectionnum
     * @return array{done:int,total:int}
     */
    protected static function section_progress(int $courseid, int $userid, int $sectionnum): array {
        try {
            $modinfo = get_fast_modinfo($courseid, $userid);
            $total = 0;
            $done = 0;
            foreach ($modinfo->cms as $cm) {
                if ((int) $cm->sectionnum !== $sectionnum || !$cm->uservisible) {
                    continue;
                }
                if ($cm->completion == COMPLETION_TRACKING_NONE) {
                    continue;
                }
                $total++;
                if (!empty($cm->completiondata->completionstate) && $cm->completiondata->completionstate > 0) {
                    $done++;
                }
            }
            return ['done' => $done, 'total' => $total];
        } catch (\Throwable $e) {
            return ['done' => 0, 'total' => 0];
        }
    }

    /**
     * Due This Week: calendar action events in the next 7 days.
     *
     * @param int $userid
     * @return array list of ['name' => string, 'url' => string, 'due' => string]
     */
    protected static function due_this_week(int $userid): array {
        global $CFG, $USER;
        $out = [];
        $start = time();
        try {
            require_once($CFG->dirroot . '/calendar/lib.php');
            if ($USER->id != $userid) {
                return ['items' => [], 'range' => self::week_range_label($start), 'hasitems' => false];
            }
            $end = $start + WEEKSECS;
            $events = calendar_get_action_events_by_timesort($start, $end, null, 8);
            foreach ($events as $event) {
                $url = $event->get_action() ? $event->get_action()->get_url()->out(false) : '';
                $duets = $event->get_times()->get_sort_time()->getTimestamp();
                $typeinfo = self::activity_type_from_url($url);
                $courseid = (int) $event->get_course()->get('id');
                $coursecode = '';
                if ($courseid && ($c = get_course($courseid, IGNORE_MISSING))) {
                    $coursecode = (string) $c->idnumber;
                }
                $out[] = [
                    'name'       => format_string($event->get_name()),
                    'url'        => $url,
                    'due'        => userdate($duets, get_string('strftimedate', 'langconfig')),
                    'dueshort'   => strtoupper(userdate($duets, '%a %b %d')),
                    'typelabel'  => $typeinfo['label'],
                    'typeclass'  => $typeinfo['class'],
                    'coursecode' => $coursecode,
                ];
            }
        } catch (\Throwable $e) {
            return ['items' => [], 'range' => self::week_range_label($start), 'hasitems' => false];
        }
        return [
            'items'    => $out,
            'range'    => self::week_range_label($start),
            'hasitems' => !empty($out),
        ];
    }

    /**
     * Map a module URL to a due-list type pill (quiz / h5p / forum / …).
     *
     * @param string $url
     * @return array{label:string,class:string}
     */
    protected static function activity_type_from_url(string $url): array {
        if (strpos($url, '/mod/quiz/') !== false) {
            return ['label' => get_string('typequiz', 'block_azmsi_dashboard'), 'class' => 'quiz'];
        }
        if (strpos($url, '/mod/h5pactivity/') !== false || strpos($url, '/mod/hvp/') !== false) {
            return ['label' => get_string('typeh5p', 'block_azmsi_dashboard'), 'class' => 'h5p'];
        }
        if (strpos($url, '/mod/forum/') !== false) {
            return ['label' => get_string('typeforum', 'block_azmsi_dashboard'), 'class' => 'forum'];
        }
        if (strpos($url, '/mod/assign/') !== false) {
            return ['label' => get_string('typeassign', 'block_azmsi_dashboard'), 'class' => 'assign'];
        }
        return ['label' => get_string('typetask', 'block_azmsi_dashboard'), 'class' => 'task'];
    }

    /**
     * Human-readable Mon–Sun range for the current week.
     *
     * @param int $now
     * @return string
     */
    protected static function week_range_label(int $now): string {
        $start = usergetmidnight($now);
        $dow = (int) date('w', $start);
        $start -= ($dow === 0 ? 6 : $dow - 1) * DAYSECS;
        $end = $start + (6 * DAYSECS);
        return strtoupper(userdate($start, '%b %d') . '–' . userdate($end, '%d'));
    }

    /**
     * Next upcoming live class (BigBlueButton or live-titled calendar event).
     *
     * @param int $userid
     * @return array{has:bool,text:string,url:string}
     */
    protected static function next_live_class(int $userid): array {
        global $CFG, $USER;
        $none = ['has' => false, 'text' => '', 'url' => ''];
        try {
            require_once($CFG->dirroot . '/calendar/lib.php');
            if ($USER->id != $userid) {
                return $none;
            }
            $events = calendar_get_action_events_by_timesort(time(), time() + (14 * DAYSECS), null, 30);
            foreach ($events as $event) {
                $url = $event->get_action() ? $event->get_action()->get_url()->out(false) : '';
                $name = format_string($event->get_name());
                $islive = strpos($url, 'bigbluebuttonbn') !== false
                    || stripos($name, 'live') !== false
                    || stripos($name, 'seminar') !== false;
                if (!$islive) {
                    continue;
                }
                $when = userdate(
                    $event->get_times()->get_sort_time()->getTimestamp(),
                    get_string('strftimedaydatetime', 'langconfig')
                );
                $suffix = strpos($url, 'bigbluebuttonbn') !== false ? ' · BigBlueButton' : '';
                return [
                    'has'  => true,
                    'text' => $name . ' — ' . $when . $suffix,
                    'url'  => $url,
                ];
            }
        } catch (\Throwable $e) {
            return $none;
        }
        return $none;
    }

    /**
     * Program map Q1–Q12 derived from the enrolled courses' quarter custom field.
     *
     * @param array $courseout composed course list
     * @return array 12 entries ['quarter' => int, 'status' => string, 'current' => bool]
     */
    protected static function program_map(array $courseout): array {
        // Aggregate enrolment + completion by quarter.
        $byquarter = [];
        foreach ($courseout as $c) {
            $q = (int) $c['quarter'];
            if ($q < 1 || $q > 12) {
                continue;
            }
            $byquarter[$q]['enrolled'] = true;
            $byquarter[$q]['complete'] = ($byquarter[$q]['complete'] ?? true) && $c['progress'] >= 100;
        }

        $current = 0;
        foreach (range(1, 12) as $q) {
            if (!empty($byquarter[$q]['enrolled']) && empty($byquarter[$q]['complete'])) {
                $current = $q;
                break;
            }
        }

        $map = [];
        foreach (range(1, 12) as $q) {
            $status = 'planned';
            if (!empty($byquarter[$q]['enrolled'])) {
                $status = !empty($byquarter[$q]['complete']) ? 'done' : 'in_progress';
            }
            $map[] = [
                'quarter' => $q,
                'status'  => $status,
                'current' => $q === $current,
            ];
        }
        return $map;
    }
}
