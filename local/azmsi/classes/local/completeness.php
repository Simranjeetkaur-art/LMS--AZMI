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
 * Course-content completeness analysis (Phase 4).
 *
 * A week is "complete" only when it holds the full master-template activity set
 * (7 activities) AND every one of those activities actually has content (not just
 * the seeded placeholder), AND the course-level requirements are met (contact hours,
 * textbook, an approved course rating and an approved instructor rating).
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completeness {

    /** @var array Required activity composition per week: modname => minimum count. */
    const REQUIRED = ['page' => 3, 'forum' => 1, 'quiz' => 1, 'assign' => 2];

    /** @var string idnumber marker of the course-level Final Exam (excluded from weeks). */
    const ID_FINALEXAM = 'emd_finalexam';

    /**
     * The seven master-template activities per week, in creation order.
     *
     * @return array<int, array{0:string,1:string,2:string}> [modname, langkey, component]
     */
    protected static function week_activity_sequence(): array {
        return [
            ['page', 'act_video', 'format_emd'],
            ['page', 'act_readings', 'format_emd'],
            ['page', 'act_h5plab', 'format_emd'],
            ['forum', 'act_discussion', 'format_emd'],
            ['quiz', 'act_quiz', 'format_emd'],
            ['assign', 'act_assignment', 'format_emd'],
            ['assign', 'act_reflection', 'format_emd'],
        ];
    }

    /**
     * Analyse a course's content completeness.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function analyze(\stdClass $course): array {
        $courselevel = self::course_checks($course);
        $courseok = true;
        foreach ($courselevel as $c) {
            $courseok = $courseok && $c['ok'];
        }

        $weeks = self::week_checks($course, $courseok);
        $complete = 0;
        foreach ($weeks as $w) {
            if ($w['complete']) {
                $complete++;
            }
        }

        $totalweeks = count($weeks);

        return [
            'courseid'      => (int) $course->id,
            'code'          => (string) $course->idnumber,
            'name'          => format_string($course->fullname),
            'courselevel'   => $courselevel,
            'courseok'      => $courseok,
            'weeks'         => $weeks,
            'completeweeks' => $complete,
            'totalweeks'    => $totalweeks,
            'weekspct'      => $totalweeks ? (int) round(100 * $complete / $totalweeks) : 0,
            'iscomplete'    => $totalweeks > 0 && $complete === $totalweeks && $courseok,
        ];
    }

    /**
     * Compact completeness summary for the admin production-pipeline board.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function for_pipeline(\stdClass $course): array {
        $analysis = self::analyze($course);
        $url = (new \moodle_url('/local/azmsi/completeness.php', ['courseid' => $course->id]))->out(false);

        $weeks = array_map(static function (array $week): array {
            return [
                'num'          => $week['num'],
                'name'         => $week['name'],
                'shortlabel'   => 'W' . $week['num'],
                'complete'     => $week['complete'],
                'activitiesok' => $week['activitiesok'],
                'contentok'    => $week['contentok'],
                'blocked'      => !$week['complete'],
            ];
        }, $analysis['weeks']);

        return [
            'completeweeks' => $analysis['completeweeks'],
            'totalweeks'    => $analysis['totalweeks'],
            'weekspct'      => $analysis['weekspct'],
            'iscomplete'    => $analysis['iscomplete'],
            'courseok'      => $analysis['courseok'],
            'courselevel'   => array_map(static function (array $check): array {
                $short = match ($check['key']) {
                    'contacthours'     => get_string('reqshort_contacthours', 'local_azmsi'),
                    'textbook'           => get_string('reqshort_textbook', 'local_azmsi'),
                    'courserating'       => get_string('reqshort_courserating', 'local_azmsi'),
                    'instructorrating'   => get_string('reqshort_instructorrating', 'local_azmsi'),
                    default              => $check['label'],
                };
                $check['shortlabel'] = $short;
                return $check;
            }, $analysis['courselevel']),
            'weeks'         => $weeks,
            'url'           => $url,
        ];
    }

    /**
     * Course-level requirement checks.
     *
     * @param \stdClass $course
     * @return array list of ['key','label','ok']
     */
    protected static function course_checks(\stdClass $course): array {
        $fields = self::fields($course);
        $instructorid = self::instructor_id($course);

        $courserating = reviews::course_rating($course->id);
        $instrating = reviews::instructor_rating($instructorid);

        return [
            ['key' => 'contacthours', 'label' => get_string('chk_contacthours', 'local_azmsi'),
                'ok' => trim($fields['contact_hours'] ?? '') !== ''],
            ['key' => 'textbook', 'label' => get_string('chk_textbook', 'local_azmsi'),
                'ok' => trim($fields['textbook'] ?? '') !== ''],
            ['key' => 'courserating', 'label' => get_string('chk_courserating', 'local_azmsi'),
                'ok' => $courserating['has']],
            ['key' => 'instructorrating', 'label' => get_string('chk_instructorrating', 'local_azmsi'),
                'ok' => $instrating['has']],
        ];
    }

    /**
     * Per-week activity + content checks.
     *
     * @param \stdClass $course
     * @param bool $courseok whether the course-level checklist passed
     * @return array
     */
    protected static function week_checks(\stdClass $course, bool $courseok): array {
        $modinfo = get_fast_modinfo($course);

        $finalsection = null;
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->idnumber === self::ID_FINALEXAM) {
                $finalsection = (int) $cm->sectionnum;
            }
        }

        $weeks = [];
        $num = 0;
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section === 0 || $section->section === $finalsection) {
                continue;
            }
            $num++;

            $cms = [];
            foreach (($modinfo->sections[$section->section] ?? []) as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if ($cm->deletioninprogress || $cm->idnumber === self::ID_FINALEXAM) {
                    continue;
                }
                $cms[] = $cm;
            }

            $items = [];
            $activitiesok = true;
            $contentok = true;
            foreach (self::week_activity_sequence() as $i => [$modname, $labelkey, $component]) {
                $cm = $cms[$i] ?? null;
                $present = $cm && $cm->modname === $modname;
                $hascontent = $present && self::has_content($cm);
                $activitiesok = $activitiesok && $present;
                $contentok = $contentok && $hascontent;
                $items[] = [
                    'label'       => get_string($labelkey, $component),
                    'need'        => 1,
                    'count'       => $present ? 1 : 0,
                    'present'     => $present,
                    'withcontent' => $hascontent ? 1 : 0,
                    'hascontent'  => $hascontent,
                ];
            }

            $weeks[] = [
                'num'          => $num,
                'name'         => $section->name ?: get_string('weeklabel', 'local_azmsi', $num),
                'items'        => $items,
                'activitiesok' => $activitiesok,
                'contentok'    => $contentok,
                'complete'     => $activitiesok && $contentok && $courseok,
            ];
        }
        return $weeks;
    }

    /**
     * Whether a course module has real (author-supplied) content, not just the seeded
     * placeholder.
     *
     * @param \cm_info $cm
     * @return bool
     */
    protected static function has_content(\cm_info $cm): bool {
        global $DB;
        $instance = $DB->get_field('course_modules', 'instance', ['id' => $cm->id]);
        if (!$instance) {
            return false;
        }
        switch ($cm->modname) {
            case 'page':
                return self::is_real_content($DB->get_field('page', 'content', ['id' => $instance]));
            case 'assign':
                return self::is_real_content($DB->get_field('assign', 'intro', ['id' => $instance]));
            case 'quiz':
                return $DB->count_records('quiz_slots', ['quizid' => $instance]) > 0;
            case 'forum':
                return $DB->count_records('forum_discussions', ['forum' => $instance]) > 0;
        }
        return false;
    }

    /**
     * Treat the seeded master-template placeholder text as "no content".
     *
     * @param string|null $html
     * @return bool
     */
    protected static function is_real_content(?string $html): bool {
        $plain = trim(html_to_text((string) $html, 0, false));
        if ($plain === '') {
            return false;
        }
        foreach (['master template', 'eMD master template', 'Edit this activity to add your content',
                  'placeholder', 'Replace this'] as $needle) {
            if (stripos($plain, $needle) !== false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Course custom field values keyed by shortname.
     *
     * @param \stdClass $course
     * @return array
     */
    protected static function fields(\stdClass $course): array {
        $out = [];
        foreach (\core_course\customfield\course_handler::create()->get_instance_data($course->id, true) as $data) {
            $out[$data->get_field()->get('shortname')] = (string) $data->export_value();
        }
        return $out;
    }

    /**
     * The course's first editing teacher id (the rated instructor), or 0.
     *
     * @param \stdClass $course
     * @return int
     */
    protected static function instructor_id(\stdClass $course): int {
        $roles = array_keys(get_archetype_roles('editingteacher'));
        if (!$roles) {
            return 0;
        }
        $context = \context_course::instance($course->id);
        $teachers = get_role_users($roles, $context, false, 'u.id');
        return $teachers ? (int) reset($teachers)->id : 0;
    }
}
