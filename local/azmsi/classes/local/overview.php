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
        $modulescompleted = 0;
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
                'id'        => (int) $course->id,
                'name'      => format_string($course->fullname),
                'code'      => (string) ($fields['course_code'] ?? $course->idnumber),
                'credits'   => isset($fields['credits']) ? (int) $fields['credits'] : 0,
                'quarter'   => isset($fields['quarter']) ? (int) $fields['quarter'] : 0,
                'status'    => ($fields['status'] ?? '') === 'in_progress' ? 'in_progress' : 'planned',
                'progress'  => $progress,
                'url'       => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            ];
            if ($progress >= 100) {
                $modulescompleted++;
            }
        }

        return [
            'fullname'         => $user ? fullname($user) : '',
            'firstname'        => $user ? $user->firstname : '',
            'average'          => $graded ? round($sum / $graded, 1) : 0.0,
            'courses'          => $courseout,
            'continue'         => self::continue_card($userid, $courses),
            'dueweek'          => self::due_this_week($userid),
            'programmap'       => self::program_map($courseout),
            'coursecount'      => count($courseout),
            'modulescompleted' => $modulescompleted,
            'generatedon'      => time(),
        ];
    }

    /**
     * The continue card: the user's most-recently-viewed activity (logstore),
     * falling back to the most-recently-accessed course.
     *
     * @param int $userid
     * @param array $courses enrolled courses
     * @return array ['has' => bool, 'name' => string, 'coursename' => string, 'url' => string]
     */
    protected static function continue_card(int $userid, array $courses): array {
        global $DB;
        $none = ['has' => false, 'name' => '', 'coursename' => '', 'url' => ''];

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
                        return [
                            'has'        => true,
                            'name'       => format_string($cm->name),
                            'coursename' => format_string(get_course($r->course)->fullname),
                            'url'        => $cm->url ? $cm->url->out(false)
                                : (new moodle_url('/course/view.php', ['id' => $r->course]))->out(false),
                        ];
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
            return [
                'has'        => true,
                'name'       => format_string($best->fullname),
                'coursename' => format_string($best->fullname),
                'url'        => (new moodle_url('/course/view.php', ['id' => $best->id]))->out(false),
            ];
        }
        return $none;
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
        try {
            require_once($CFG->dirroot . '/calendar/lib.php');
            // The calendar action-events API reports for the current $USER only.
            if ($USER->id != $userid) {
                return $out;
            }
            $events = calendar_get_action_events_by_timesort(time(), time() + WEEKSECS, null, 6);
            foreach ($events as $event) {
                $url = $event->get_action() ? $event->get_action()->get_url()->out(false) : '';
                $duets = $event->get_times()->get_sort_time()->getTimestamp();
                $out[] = [
                    'name' => format_string($event->get_name()),
                    'url'  => $url,
                    'due'  => userdate($duets, get_string('strftimedate', 'langconfig')),
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
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
