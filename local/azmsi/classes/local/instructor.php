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
 * Composes the instructor-course view (S11) from LIVE data: the ungraded
 * submissions queue (deep-linked to the NATIVE grader), the at-risk list, the
 * roster, and the course's active advanced-grading (rubric) criteria.
 *
 * Grading is never rebuilt here — "Grade" deep-links to mod_assign's grader.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instructor {
    /**
     * Build the instructor view for a course.
     *
     * @param int $courseid
     * @return array
     */
    public static function for_course(int $courseid): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $course = get_course($courseid);
        $context = context_course::instance($courseid);

        return [
            'courseid'    => (int) $courseid,
            'coursename'  => format_string($course->fullname),
            'coursecode'  => (string) $course->idnumber,
            'submissions' => self::submissions($courseid),
            'atrisk'      => self::at_risk($course, $context),
            'roster'      => self::roster($context),
            'rubric'      => self::rubric($context),
        ];
    }

    /**
     * Ungraded, submitted assignment attempts → deep-link to the native grader.
     *
     * @param int $courseid
     * @return array
     */
    protected static function submissions(int $courseid): array {
        global $DB;
        $out = [];
        try {
            $sql = "SELECT s.id, s.userid, s.timemodified, a.name AS assignname, cm.id AS cmid,
                           u.firstname, u.lastname
                      FROM {assign_submission} s
                      JOIN {assign} a ON a.id = s.assignment
                      JOIN {course_modules} cm ON cm.instance = a.id
                      JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                      JOIN {user} u ON u.id = s.userid
                 LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid
                                            AND g.attemptnumber = s.attemptnumber
                     WHERE a.course = :courseid
                           AND s.latest = 1 AND s.status = :submitted
                           AND (g.grade IS NULL OR g.grade < 0 OR g.timemodified < s.timemodified)
                  ORDER BY s.timemodified ASC";
            $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'submitted' => 'submitted'], 0, 50);
            foreach ($rows as $r) {
                $graderurl = new moodle_url('/mod/assign/view.php', [
                    'id' => $r->cmid, 'action' => 'grader', 'userid' => $r->userid,
                ]);
                $out[] = [
                    'student'  => fullname($r),
                    'assign'   => format_string($r->assignname),
                    'time'     => userdate($r->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
                    'gradeurl' => $graderurl->out(false),
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    /**
     * At-risk students: low course grade OR no login in 14+ days.
     *
     * @param \stdClass $course
     * @param \context $context
     * @return array
     */
    protected static function at_risk(\stdClass $course, \context $context): array {
        $out = [];
        try {
            $item = \grade_item::fetch_course_item($course->id);
            $users = get_enrolled_users($context, 'mod/assign:submit', 0, 'u.*', null, 0, 0, true);
            $staletime = time() - (14 * DAYSECS);
            foreach ($users as $u) {
                $reasons = [];
                if ($item && $item->grademax > $item->grademin) {
                    $grade = new \grade_grade(['itemid' => $item->id, 'userid' => $u->id], false);
                    if ($grade && !is_null($grade->finalgrade)) {
                        $pct = ($grade->finalgrade - $item->grademin) / ($item->grademax - $item->grademin) * 100;
                        if ($pct < 60) {
                            $reasons[] = get_string('reasonlowgrade', 'local_azmsi', round($pct));
                        }
                    }
                }
                $last = (int) ($u->lastaccess ?? 0);
                if ($last > 0 && $last < $staletime) {
                    $reasons[] = get_string('reasoninactive', 'local_azmsi');
                }
                if ($reasons) {
                    $out[] = [
                        'student'     => fullname($u),
                        'reason'      => implode(' · ', $reasons),
                        'messageurl'  => (new moodle_url('/message/index.php', ['id' => $u->id]))->out(false),
                    ];
                }
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    /**
     * Roster from the enrolled-users API.
     *
     * @param \context $context
     * @return array
     */
    protected static function roster(\context $context): array {
        $out = [];
        try {
            $users = get_enrolled_users($context, 'mod/assign:submit', 0, 'u.*', 'u.lastname ASC', 0, 0, true);
            foreach ($users as $u) {
                $out[] = [
                    'name'       => fullname($u),
                    'lastaccess' => $u->lastaccess
                        ? userdate($u->lastaccess, get_string('strftimedateshort', 'langconfig'))
                        : get_string('never', 'local_azmsi'),
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    /**
     * Read the course's first assignment with an active rubric and return its
     * criteria (display only — grading itself uses the native grader).
     *
     * @param \context $coursecontext
     * @return array ['has' => bool, 'name' => string, 'criteria' => array, 'gradeurl' => string]
     */
    protected static function rubric(\context $coursecontext): array {
        global $DB, $CFG;
        $none = ['has' => false, 'name' => '', 'criteria' => [], 'gradeurl' => ''];
        try {
            require_once($CFG->dirroot . '/grade/grading/lib.php');
            $modinfo = get_fast_modinfo($coursecontext->instanceid);
            foreach ($modinfo->get_instances_of('assign') as $cm) {
                $modcontext = \core\context\module::instance($cm->id);
                $manager = get_grading_manager($modcontext, 'mod_assign', 'submissions');
                $method = $manager->get_active_method();
                if ($method !== 'rubric') {
                    continue;
                }
                $controller = $manager->get_controller($method);
                if (!$controller->is_form_defined()) {
                    continue;
                }
                $definition = $controller->get_definition();
                $criteria = [];
                if (!empty($definition->rubric_criteria)) {
                    foreach ($definition->rubric_criteria as $crit) {
                        $criteria[] = ['description' => format_string($crit['description'])];
                    }
                }
                return [
                    'has'      => !empty($criteria),
                    'name'     => format_string($cm->name),
                    'criteria' => $criteria,
                    'gradeurl' => (new moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'grading']))->out(false),
                ];
            }
        } catch (\Throwable $e) {
            return $none;
        }
        return $none;
    }
}
