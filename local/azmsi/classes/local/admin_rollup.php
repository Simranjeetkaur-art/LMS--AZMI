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
            $lead = self::lead_faculty($context);
            $avg = self::course_completion_avg($course->id, $context);

            $courseops[] = [
                'code'        => (string) $course->idnumber,
                'name'        => format_string($course->fullname, true, ['escape' => false]),
                'lead'        => $lead ?: '—',
                'students'    => $students,
                'modules'     => $modules,
                'activities'  => $activities,
                'status'      => $bucket,
                'statuslabel' => get_string('status_' . $bucket, 'local_azmsi'),
                'avg'         => $avg,
            ];

            if ($lead) {
                $facultyload[$lead]['courses'] = ($facultyload[$lead]['courses'] ?? 0) + 1;
                $facultyload[$lead]['students'] = ($facultyload[$lead]['students'] ?? 0) + $students;
            }
        }

        // Faculty-load list (sorted by students desc).
        $faculty = [];
        foreach ($facultyload as $name => $l) {
            $faculty[] = ['name' => $name, 'courses' => $l['courses'], 'students' => $l['students']];
        }
        usort($faculty, static fn($a, $b) => $b['students'] <=> $a['students']);

        return [
            'generatedon'    => $now,
            'kpis'           => self::kpis($status, $studenttotal),
            'coursesbystatus' => self::status_chart($status),
            'funnel'         => self::funnel(),
            'systemhealth'   => self::system_health(),
            'courseops'      => $courseops,
            'courseopstotal' => count($courseops),
            'facultyload'    => array_slice($faculty, 0, 8),
            'usersbyrole'    => self::users_by_role(),
        ];
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
     * The first editing teacher's name in a course, or '' if unassigned.
     *
     * @param \context $context
     * @return string
     */
    protected static function lead_faculty(\context $context): string {
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
     * System health from real sources: cron freshness + dataroot storage.
     *
     * @return array
     */
    protected static function system_health(): array {
        global $DB, $CFG;
        $out = [];

        $lastcron = (int) $DB->get_field_sql('SELECT MAX(lastruntime) FROM {task_scheduled}');
        $out[] = [
            'label' => get_string('healthcron', 'local_azmsi'),
            'value' => $lastcron ? userdate($lastcron, get_string('strftimedatetimeshort', 'langconfig')) : '—',
            'ok'    => $lastcron && ($lastcron > time() - (2 * HOURSECS)),
        ];

        $total = @disk_total_space($CFG->dataroot);
        $free = @disk_free_space($CFG->dataroot);
        if ($total && $free !== false) {
            $usedpct = (int) round(($total - $free) / $total * 100);
            $out[] = [
                'label' => get_string('healthstorage', 'local_azmsi'),
                'value' => $usedpct . '%',
                'ok'    => $usedpct < 90,
            ];
        }
        return $out;
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
