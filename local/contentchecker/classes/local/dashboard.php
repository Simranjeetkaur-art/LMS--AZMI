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

namespace local_contentchecker\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Assembles the per-week and site-wide status views.
 *
 * The four badge states come straight from the spec: Never Checked, Verified
 * OK, Needs Review and Check Failed. "Needs review" is driven by outstanding
 * suggestions rather than by the raw verdict count, so a week whose findings
 * have all been decided on goes back to green without needing a re-run.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard {

    /** @var string Never checked. */
    const NEVER = 'never';

    /** @var string Checked, nothing outstanding. */
    const OK = 'ok';

    /** @var string Checked, suggestions awaiting a decision. */
    const NEEDS_REVIEW = 'needsreview';

    /** @var string The check itself errored. */
    const FAILED = 'failed';

    /** @var string A check is queued or running right now. */
    const RUNNING = 'running';

    /**
     * Checked, but nothing was extracted from content that clearly has some.
     *
     * Distinct from OK on purpose: it is what a broken extraction prompt looks
     * like, and showing it as green would certify unread material.
     *
     * @var string
     */
    const EMPTY_RESULT = 'empty';

    /**
     * Per-section status for one course.
     *
     * @param int $courseid Course id.
     * @return array List of section status objects.
     */
    public static function for_course(int $courseid): array {
        global $DB;

        $modinfo = get_fast_modinfo($courseid);
        $format = course_get_format($courseid);
        $rows = [];

        foreach ($modinfo->get_section_info_all() as $section) {
            // Section 0 is the course header, not a teaching week.
            if ((int) $section->section === 0) {
                continue;
            }

            $items = content_source::for_section($courseid, (int) $section->section);
            $check = self::latest_check($courseid, (int) $section->section);

            $rows[] = (object) [
                'sectionnum' => (int) $section->section,
                'name' => $format->get_section_name($section),
                'numitems' => count($items),
                'status' => self::status_for($check, $courseid, (int) $section->section),
                'checkid' => $check ? (int) $check->id : 0,
                'lastchecked' => $check && $check->timefinished
                    ? userdate($check->timefinished) : null,
                'pending' => self::pending_count($courseid, (int) $section->section),
                'errormsg' => $check && $check->status === 'failed' ? $check->errormsg : null,
                'progress' => $check ? (int) $check->progress : 0,
            ];
        }

        return $rows;
    }

    /**
     * The most recent check covering a section.
     *
     * A whole-course run (sectionnum -1) counts as having covered every
     * section, so a week is not reported as never checked just because the
     * editor ran the course-level job rather than the per-week one.
     *
     * @param int $courseid Course id.
     * @param int $sectionnum Section number.
     * @return \stdClass|null The check row.
     */
    public static function latest_check(int $courseid, int $sectionnum): ?\stdClass {
        global $DB;

        // Only a check that covered the WHOLE week counts as having verified
        // it, which means cmid = 0. A single-activity run still surfaces its
        // findings through pending_count, but it cannot certify the twelve
        // other activities it never looked at.
        $records = $DB->get_records_select('local_cchecker_checks',
            'courseid = :courseid AND cmid = 0
                 AND (sectionnum = :sectionnum OR sectionnum = -1)',
            ['courseid' => $courseid, 'sectionnum' => $sectionnum],
            'timequeued DESC, id DESC', '*', 0, 1);

        return $records ? reset($records) : null;
    }

    /**
     * Suggestions in a section still awaiting a human decision.
     *
     * @param int $courseid Course id.
     * @param int $sectionnum Section number.
     * @return int Count of pending suggestions.
     */
    public static function pending_count(int $courseid, int $sectionnum): int {
        global $DB;

        $cmids = array_map(fn($item) => $item->cmid,
            content_source::for_section($courseid, $sectionnum));
        if (!$cmids) {
            return 0;
        }

        [$insql, $params] = $DB->get_in_or_equal(array_unique($cmids), SQL_PARAMS_NAMED, 'cm');
        [$vsql, $vparams] = $DB->get_in_or_equal(pipeline::FLAGGED, SQL_PARAMS_NAMED, 'v');

        return $DB->count_records_select('local_cchecker_suggestions',
            "cmid {$insql} AND verdict {$vsql} AND decision = :pending",
            $params + $vparams + ['pending' => 'pending']);
    }

    /**
     * Which badge a section should show.
     *
     * @param \stdClass|null $check The latest check, if any.
     * @param int $courseid Course id.
     * @param int $sectionnum Section number.
     * @return string One of the class constants.
     */
    protected static function status_for(?\stdClass $check, int $courseid,
            int $sectionnum): string {
        if ($check && in_array($check->status, ['queued', 'running'], true)) {
            return self::RUNNING;
        }

        // An outstanding finding outranks everything else, including the
        // absence of a week-level check: a single-activity run that flagged
        // something must not leave the week looking unexamined.
        if (self::pending_count($courseid, $sectionnum) > 0) {
            return self::NEEDS_REVIEW;
        }

        if (!$check) {
            return self::NEVER;
        }
        if ($check->status === 'failed') {
            return self::FAILED;
        }

        // Completed, nothing outstanding -- but if the run covered activities
        // and produced no claims whatsoever, it verified nothing and must not
        // read as green.
        global $DB;
        if ((int) $check->numitems > 0
                && !$DB->record_exists('local_cchecker_suggestions', ['checkid' => $check->id])) {
            return self::EMPTY_RESULT;
        }

        return self::OK;
    }

    /**
     * Site-wide oversight: one row per course that has ever been checked, plus
     * any course an admin can see that never has been.
     *
     * @param int $limit Maximum courses to report.
     * @return array List of course status objects.
     */
    public static function site_overview(int $limit = 200): array {
        global $DB;

        // Ordered in PHP rather than SQL: sorting "never checked" last needs
        // NULLS LAST, which MySQL does not support.
        $sql = "SELECT c.id, c.shortname, c.fullname,
                       MAX(ch.timefinished) AS lastchecked,
                       COUNT(DISTINCT ch.id) AS numchecks
                  FROM {course} c
             LEFT JOIN {local_cchecker_checks} ch ON ch.courseid = c.id
                 WHERE c.id <> :siteid
              GROUP BY c.id, c.shortname, c.fullname";

        $courses = $DB->get_records_sql($sql, ['siteid' => SITEID]);

        $rows = [];
        foreach ($courses as $course) {
            $pending = $DB->count_records_sql(
                "SELECT COUNT(s.id)
                   FROM {local_cchecker_suggestions} s
                   JOIN {local_cchecker_checks} ch ON ch.id = s.checkid
                  WHERE ch.courseid = :courseid AND s.decision = :pending",
                ['courseid' => $course->id, 'pending' => 'pending']);

            $rows[] = (object) [
                'courseid' => (int) $course->id,
                'shortname' => $course->shortname,
                'fullname' => $course->fullname,
                'numchecks' => (int) $course->numchecks,
                'pending' => $pending,
                'lastchecked' => $course->lastchecked ? userdate($course->lastchecked) : null,
                'lastcheckedraw' => (int) $course->lastchecked,
            ];
        }

        // Most recently checked first; never-checked courses fall to the end,
        // where they are then ordered by shortname.
        usort($rows, function($a, $b) {
            return ($b->lastcheckedraw <=> $a->lastcheckedraw)
                ?: strcasecmp($a->shortname, $b->shortname);
        });

        return array_slice($rows, 0, $limit);
    }
}
