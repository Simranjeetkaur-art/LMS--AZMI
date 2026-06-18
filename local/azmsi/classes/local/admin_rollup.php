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

/**
 * Heavy admin-console aggregation (S12), run by CRON (rollup_admin_kpis) and by
 * the event-driven adhoc refresh (refresh_admin_console) — never on page load.
 *
 * compute() walks every AZMSI course + role/enrolment/completion/application data
 * once and returns the whole dashboard dataset; the result is stored in
 * cache_azmsi (key 'admin_console') and read by the page via {@see admin}.
 *
 * Everything is a live query — nothing hardcoded. Sources unavailable on this
 * site (e.g. external uptime, BBB live sessions, a ticketing system) are simply
 * omitted rather than faked.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_rollup {
    /** @var string Cache key for the composed dataset. */
    public const KEY = 'admin_console';

    /** @var string Legacy KPI-only cache key (read by the get_admin_kpis WS). */
    public const KPI_KEY = 'admin_kpis';

    /**
     * Compute the full admin dataset and write it to cache_azmsi.
     *
     * Writes two keys: the full dashboard dataset (self::KEY) and the KPI subset
     * (self::KPI_KEY) consumed by the get_admin_kpis web service — so the WS keeps
     * reading the same key it always has.
     *
     * @return array the dataset (also cached)
     */
    public static function rebuild(): array {
        $data = self::compute();
        $cache = \cache::make('local_azmsi', 'rollups');
        $cache->set(self::KEY, $data);
        $cache->set(self::KPI_KEY, $data['kpis']);
        return $data;
    }

    /**
     * Compute the dataset (no caching side effect).
     *
     * @return array
     */
    public static function compute(): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $courses = self::azmsi_courses();
        $status = ['running' => 0, 'scheduled' => 0, 'draft' => 0, 'archived' => 0];
        $courseops = [];
        $facultyload = [];
        $studenttotal = 0;
        $now = time();

        foreach ($courses as $course) {
            $context = context_course::instance($course->id);
            $fields = self::course_fields($course->id);
            $bucket = self::status_bucket($course, $fields, $now);
            $status[$bucket]++;

            $students = (int) count_enrolled_users($context, 'mod/assign:submit', 0, true);
            $studenttotal += $students;
            $modinfo = get_fast_modinfo($course->id);
            $activities = count($modinfo->get_cms());
            $sections = $modinfo->get_section_info_all();
            $modules = max(0, count($sections) - 1); // Exclude section 0 (intro).
            $leaduser = self::lead_faculty_user($context);
            $leadname = $leaduser ? fullname($leaduser) : '';
            $avg = self::course_completion_avg($course->id, $context);

            $courseops[] = [
                'code'        => (string) $course->idnumber,
                'name'        => format_string($course->fullname, true, ['escape' => false]),
                'lead'        => $leadname ?: '—',
                'students'    => $students,
                'modules'     => $modules,
                'activities'  => $activities,
                'status'      => $bucket,
                'statuslabel' => get_string('status_' . $bucket, 'local_azmsi'),
                'avg'         => $avg,
            ];

            if ($leaduser) {
                $fid = (int) $leaduser->id;
                if (!isset($facultyload[$fid])) {
                    $facultyload[$fid] = [
                        'name'     => $leadname,
                        'dept'     => (string) ($leaduser->department ?? ''),
                        'initials' => self::initials($leaduser),
                        'courses'  => 0,
                        'students' => 0,
                    ];
                }
                $facultyload[$fid]['courses']++;
                $facultyload[$fid]['students'] += $students;
            }
        }

        // Course operations: running first, then by students desc (most relevant top).
        $order = ['running' => 0, 'scheduled' => 1, 'draft' => 2, 'archived' => 3];
        usort($courseops, static function ($a, $b) use ($order) {
            return [$order[$a['status']], -$a['students']] <=> [$order[$b['status']], -$b['students']];
        });

        // Faculty-load list (sorted by students desc).
        $faculty = array_values($facultyload);
        usort($faculty, static fn($a, $b) => $b['students'] <=> $a['students']);

        $health = self::system_health();

        return [
            'generatedon'     => $now,
            'kpis'            => self::kpis($status, $studenttotal),
            'coursesbystatus' => self::status_chart($status),
            'funnel'          => self::funnel(),
            'systemhealth'    => $health['rows'],
            'operational'     => $health['operational'],
            'courseops'       => $courseops,
            'courseopstotal'  => count($courseops),
            'facultyload'     => array_slice($faculty, 0, 8),
            'facultyactive'   => count($faculty),
            'usersbyrole'     => self::users_by_role(),
            'announcements'   => self::announcements(),
        ];
    }

    /**
     * Two-letter initials for an avatar chip.
     *
     * @param \stdClass $u
     * @return string
     */
    protected static function initials(\stdClass $u): string {
        $a = \core_text::strtoupper(\core_text::substr(trim((string) $u->firstname), 0, 1));
        $b = \core_text::strtoupper(\core_text::substr(trim((string) $u->lastname), 0, 1));
        return ($a . $b) !== '' ? $a . $b : '–';
    }

    /**
     * The AZMSI catalog courses (idnumber EMD-%).
     *
     * @return array
     */
    protected static function azmsi_courses(): array {
        global $DB;
        return $DB->get_records_select(
            'course',
            $DB->sql_like('idnumber', ':code'),
            ['code' => 'EMD-%'],
            'sortorder ASC'
        );
    }

    /**
     * Course custom fields keyed by shortname.
     *
     * @param int $courseid
     * @return array
     */
    protected static function course_fields(int $courseid): array {
        $fields = [];
        try {
            $handler = \core_course\customfield\course_handler::create();
            foreach ($handler->get_instance_data($courseid, true) as $d) {
                $fields[$d->get_field()->get('shortname')] = $d->export_value();
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $fields;
    }

    /**
     * Bucket a course into running|scheduled|draft|archived from live state.
     *
     * @param \stdClass $course
     * @param array $fields
     * @param int $now
     * @return string
     */
    protected static function status_bucket(\stdClass $course, array $fields, int $now): string {
        if ($course->enddate && $course->enddate < $now) {
            return 'archived';
        }
        if (($fields['status'] ?? '') === 'in_progress' && $course->visible) {
            return 'running';
        }
        if ($course->visible && $course->startdate > $now) {
            return 'scheduled';
        }
        return 'draft';
    }

    /**
     * The first editing teacher (user record) in a course, or null if unassigned.
     *
     * @param \context $context
     * @return \stdClass|null
     */
    protected static function lead_faculty_user(\context $context): ?\stdClass {
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
            return $t ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Average activity-completion percent across enrolled students in a course.
     *
     * @param int $courseid
     * @param \context $context
     * @return int
     */
    protected static function course_completion_avg(int $courseid, \context $context): int {
        global $DB;
        try {
            $modinfo = get_fast_modinfo($courseid);
            $trackable = 0;
            foreach ($modinfo->get_cms() as $cm) {
                if ($cm->completion != COMPLETION_TRACKING_NONE) {
                    $trackable++;
                }
            }
            $students = (int) count_enrolled_users($context, 'mod/assign:submit', 0, true);
            if (!$trackable || !$students) {
                return 0;
            }
            $sql = "SELECT COUNT(cmc.id)
                      FROM {course_modules_completion} cmc
                      JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                     WHERE cm.course = :courseid AND cmc.completionstate > 0";
            $done = (int) $DB->count_records_sql($sql, ['courseid' => $courseid]);
            return (int) round(min(100, $done / ($trackable * $students) * 100));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Top-line KPIs (also surfaced via get_admin_kpis).
     *
     * @param array $status status bucket counts
     * @param int $studenttotal
     * @return array
     */
    protected static function kpis(array $status, int $studenttotal): array {
        global $DB;
        $activestudents = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.status = 0 AND e.status = 0"
        );
        $faculty = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ra.userid)
               FROM {role_assignments} ra JOIN {role} r ON r.id = ra.roleid
              WHERE r.archetype IN ('editingteacher', 'teacher')"
        );
        $applications = (int) $DB->count_records('local_azmsi_application', ['status' => 'open']);
        $coursestotal = 0;
        foreach (program::catalog() as $info) {
            $coursestotal += count($info['courses']);
        }
        return [
            'activestudents' => $activestudents,
            'applications'   => $applications,
            'faculty'        => $faculty,
            'coursesbuilt'   => $status['running'],
            'coursestotal'   => $coursestotal,
            'generatedon'    => time(),
        ];
    }

    /**
     * Courses-by-status chart rows with percentages.
     *
     * @param array $status
     * @return array
     */
    protected static function status_chart(array $status): array {
        $total = max(1, array_sum($status));
        $rows = [];
        foreach (['running', 'scheduled', 'draft', 'archived'] as $key) {
            $rows[] = [
                'key'   => $key,
                'label' => get_string('status_' . $key, 'local_azmsi'),
                'count' => $status[$key],
                'pct'   => (int) round($status[$key] / $total * 100),
            ];
        }
        return [
            'rows'       => $rows,
            'running'    => $status['running'],
            'total'      => array_sum($status),
            'runningpct' => (int) round($status['running'] / $total * 100),
        ];
    }

    /**
     * Admissions funnel, live from local_azmsi_application + enrolment.
     *
     * @return array
     */
    protected static function funnel(): array {
        global $DB;
        try {
            $applications = (int) $DB->count_records('local_azmsi_application', []);
            $aqe = (int) $DB->count_records_select(
                'local_azmsi_application',
                'stage = :a OR stage = :b',
                ['a' => 'aqe_scheduled', 'b' => 'aqe_completed']
            );
            $admitted = (int) $DB->count_records('local_azmsi_application', ['stage' => 'decision']);
            $enrolled = (int) $DB->count_records('local_azmsi_application', ['status' => 'accepted']);
            $active = (int) $DB->count_records_sql(
                "SELECT COUNT(DISTINCT userid) FROM {user_lastaccess} WHERE timeaccess > :since",
                ['since' => time() - WEEKSECS]
            );
        } catch (\Throwable $e) {
            $applications = $aqe = $admitted = $enrolled = $active = 0;
        }
        $rows = [
            ['label' => get_string('funnelapplications', 'local_azmsi'), 'count' => $applications],
            ['label' => get_string('funnelaqe', 'local_azmsi'), 'count' => $aqe],
            ['label' => get_string('funneladmitted', 'local_azmsi'), 'count' => $admitted],
            ['label' => get_string('funnelenrolled', 'local_azmsi'), 'count' => $enrolled],
            ['label' => get_string('funnelactive', 'local_azmsi'), 'count' => $active],
        ];
        $max = max(1, ...array_column($rows, 'count'));
        foreach ($rows as &$r) {
            $r['pct'] = (int) round($r['count'] / $max * 100);
        }
        return $rows;
    }

    /**
     * System health from real sources: cron freshness, dataroot storage, and
     * users online now. Returns the rows plus an overall "operational" flag.
     * Sources with no data on this site (live conferencing, ticketing) are omitted.
     *
     * @return array{rows:array,operational:bool}
     */
    protected static function system_health(): array {
        global $DB, $CFG;
        $rows = [];
        $operational = true;

        $lastcron = (int) $DB->get_field_sql('SELECT MAX(lastruntime) FROM {task_scheduled}');
        $cronok = $lastcron && ($lastcron > time() - (2 * HOURSECS));
        $rows[] = [
            'label' => get_string('healthcron', 'local_azmsi'),
            'value' => $lastcron ? userdate($lastcron, get_string('strftimedatetimeshort', 'langconfig')) : '—',
            'ok'    => $cronok,
        ];
        $operational = $operational && $cronok;

        $total = @disk_total_space($CFG->dataroot);
        $free = @disk_free_space($CFG->dataroot);
        if ($total && $free !== false) {
            $usedpct = (int) round(($total - $free) / $total * 100);
            $storageok = $usedpct < 90;
            $rows[] = [
                'label' => get_string('healthstorage', 'local_azmsi'),
                'value' => display_size($total - $free) . ' / ' . display_size($total) . ' (' . $usedpct . '%)',
                'ok'    => $storageok,
            ];
            $operational = $operational && $storageok;
        }

        $online = (int) $DB->count_records_select(
            'user',
            'lastaccess > :since AND deleted = 0',
            ['since' => time() - (5 * MINSECS)]
        );
        $rows[] = [
            'label' => get_string('healthonline', 'local_azmsi'),
            'value' => (string) $online,
            'ok'    => true,
        ];

        return ['rows' => $rows, 'operational' => $operational];
    }

    /**
     * Latest site Announcements (the SITEID news forum) — live, never hardcoded.
     * Audience = the discussion's group name, or "All users" when ungrouped.
     *
     * @return array{items:array,forumid:int}
     */
    protected static function announcements(): array {
        global $DB;
        try {
            $forum = $DB->get_record('forum', ['course' => SITEID, 'type' => 'news'], 'id', IGNORE_MULTIPLE);
            if (!$forum) {
                return ['items' => [], 'forumid' => 0];
            }
            $discussions = $DB->get_records(
                'forum_discussions',
                ['forum' => $forum->id],
                'timemodified DESC',
                'id, name, timemodified, groupid',
                0,
                5
            );
            $items = [];
            foreach ($discussions as $d) {
                $audience = get_string('audienceall', 'local_azmsi');
                if ($d->groupid > 0 && ($g = $DB->get_record('groups', ['id' => $d->groupid], 'name'))) {
                    $audience = format_string($g->name);
                }
                $items[] = [
                    'audience' => $audience,
                    'time'     => userdate($d->timemodified, get_string('strftimerecent', 'langconfig')),
                    'subject'  => format_string($d->name),
                ];
            }
            return ['items' => $items, 'forumid' => (int) $forum->id];
        } catch (\Throwable $e) {
            return ['items' => [], 'forumid' => 0];
        }
    }

    /**
     * Users-by-role counts (bars scale to the largest).
     *
     * @return array
     */
    protected static function users_by_role(): array {
        global $DB;
        $defs = [
            'students'       => ['student'],
            'faculty'        => ['editingteacher', 'teacher'],
            'coursedesigners' => ['coursecreator'],
            'administrators' => ['manager'],
        ];
        $rows = [];
        foreach ($defs as $key => $archetypes) {
            [$insql, $params] = $DB->get_in_or_equal($archetypes, SQL_PARAMS_NAMED);
            $count = (int) $DB->count_records_sql(
                "SELECT COUNT(DISTINCT ra.userid)
                   FROM {role_assignments} ra JOIN {role} r ON r.id = ra.roleid
                  WHERE r.archetype $insql",
                $params
            );
            $rows[] = ['label' => get_string('role' . $key, 'local_azmsi'), 'count' => $count];
        }
        $max = max(1, ...array_column($rows, 'count'));
        foreach ($rows as &$r) {
            $r['pct'] = (int) round($r['count'] / $max * 100);
        }
        return $rows;
    }
}
