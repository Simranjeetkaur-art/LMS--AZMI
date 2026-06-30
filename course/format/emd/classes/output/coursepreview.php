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

namespace format_emd\output;

use core\output\named_templatable;
use renderable;
use renderer_base;
use core_course\customfield\course_handler;
use format_emd\local\master_template;

/**
 * Learner course-preview page (03_SCREEN_SPECS S5 "AZMSI LMS Course").
 *
 * Everything is read from live Moodle data — course fields, summary, sections and
 * activities, quiz question counts, gradebook category weights, the enrolled
 * teacher and the catalog sequence. Nothing is hardcoded; sections render only
 * when they have data.
 *
 * @package    format_emd
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursepreview implements named_templatable, renderable {

    /** @var \stdClass the course record. */
    protected $course;

    /** @var array cached custom field values keyed by shortname. */
    protected $fields = [];

    /**
     * Constructor.
     *
     * @param \stdClass $course
     */
    public function __construct(\stdClass $course) {
        $this->course = $course;
        foreach (course_handler::create()->get_instance_data($course->id, true) as $data) {
            $this->fields[$data->get_field()->get('shortname')] = (string) $data->export_value();
        }
    }

    /**
     * Template name.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_emd/coursepreview';
    }

    /**
     * Convenience accessor for a custom field value.
     *
     * @param string $name
     * @return string
     */
    protected function field(string $name): string {
        return trim($this->fields[$name] ?? '');
    }

    /**
     * Assemble the full preview context from live data.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;
        $context = \context_course::instance($this->course->id);

        $weeks = master_template::count_weeks($this->course);
        $credits = $this->field('credits');
        $contacthours = $this->field('contact_hours');
        $delivery = $this->field('delivery_mode') ?: '100% online';
        $quarter = $this->field('quarter');

        // KPI strip — only the metrics that have a value.
        $kpis = [];
        if ($weeks) {
            $kpis[] = ['num' => $weeks, 'label' => get_string('weeks', 'format_emd')];
        }
        if ($contacthours !== '') {
            $kpis[] = ['num' => $contacthours, 'label' => get_string('contacthours', 'format_emd')];
        }
        if ($credits !== '') {
            $kpis[] = ['num' => $credits, 'label' => get_string('credithours', 'format_emd')];
        }
        if ($delivery !== '') {
            $kpis[] = ['num' => $delivery, 'label' => get_string('delivery', 'format_emd')];
        }

        $pathway = $this->pathway();

        return [
            'courseid'   => $this->course->id,
            'code'       => $this->field('course_code') ?: $this->course->idnumber,
            'title'      => format_string($this->course->fullname, true, ['escape' => false]),
            'quarter'    => $quarter,
            'hasquarter' => $quarter !== '',
            'isgateway'  => $quarter === '1',
            'summary'    => $this->summary(),
            'meta'       => $this->meta($weeks, $contacthours, $credits, $delivery, $pathway),
            'kpis'       => $kpis,
            'welcome'    => $this->welcome_video(),
            'resources'  => $this->resources(),
            'objectives' => $this->objectives(),
            'weektypes'  => $this->week_types(),
            'weeks'      => $this->weeks(),
            'assessment' => $this->assessment(),
            'instructor' => $this->instructor($context),
            'pathway'    => $pathway,
            'enrolled'   => is_enrolled($context, $USER, '', true),
            'features'   => $this->features(),
            'courserating' => $this->course_rating(),
            'modulesanchor' => '#az-weekly-modules',
        ] + $this->rating_form($output);
    }

    /**
     * Course overview / summary text.
     *
     * @return string
     */
    protected function summary(): string {
        if (empty($this->course->summary)) {
            return '';
        }
        return format_text(
            $this->course->summary,
            $this->course->summaryformat ?? FORMAT_HTML,
            ['context' => \context_course::instance($this->course->id)]
        );
    }

    /**
     * The header meta chips (weeks · contact hours · credits · delivery · prereq).
     *
     * @param int $weeks
     * @param string $contacthours
     * @param string $credits
     * @param string $delivery
     * @param array $pathway
     * @return array list of ['text' => string]
     */
    protected function meta(int $weeks, string $contacthours, string $credits, string $delivery, array $pathway): array {
        $bits = [];
        if ($weeks) {
            $bits[] = get_string('nweeks', 'format_emd', $weeks);
        }
        if ($contacthours !== '') {
            $bits[] = get_string('ncontacthours', 'format_emd', $contacthours);
        }
        if ($credits !== '') {
            $bits[] = get_string('ncredithours', 'format_emd', $credits);
        }
        if ($delivery !== '') {
            $bits[] = $delivery;
        }
        $bits[] = get_string('prereqmeta', 'format_emd', $pathway['prereq']);
        return array_map(fn($t) => ['text' => $t], $bits);
    }

    /**
     * Welcome video — the welcome_video_url field, else the Welcome Video activity.
     *
     * @return array ['has' => bool, 'url' => string, 'label' => string]
     */
    protected function welcome_video(): array {
        $url = $this->field('welcome_video_url');
        $label = $this->field('faculty_name') !== ''
            ? get_string('welcomefrom', 'format_emd', $this->field('faculty_name'))
            : get_string('act_welcomevideo', 'format_emd');

        if ($url === '') {
            // Fall back to the Welcome Video activity, opened in the modal.
            foreach (get_fast_modinfo($this->course)->get_cms() as $cm) {
                if ($cm->idnumber === master_template::ID_WELCOME && $cm->uservisible) {
                    $url = $cm->url ? $cm->url->out(false) : '';
                    break;
                }
            }
        }
        return ['has' => $url !== '', 'url' => $url, 'label' => $label];
    }

    /**
     * Resource cards: syllabus activity, textbook, OER list (each only if present).
     *
     * @return array
     */
    protected function resources(): array {
        $out = [];

        // Syllabus — the Syllabus page activity.
        foreach (get_fast_modinfo($this->course)->get_cms() as $cm) {
            if ($cm->idnumber === master_template::ID_SYLLABUS && $cm->uservisible && $cm->url) {
                $out[] = [
                    'tag'    => get_string('restag_pdf', 'format_emd'),
                    'title'  => format_string($cm->name),
                    'detail' => get_string('syllabusdetail', 'format_emd'),
                    'url'    => $cm->url->out(false),
                ];
                break;
            }
        }

        // Textbook.
        $textbook = $this->field('textbook');
        if ($textbook !== '') {
            $isbn = $this->field('textbook_isbn');
            $out[] = [
                'tag'    => get_string('restag_book', 'format_emd'),
                'title'  => $textbook,
                'detail' => $isbn !== '' ? get_string('isbn', 'format_emd', $isbn) : '',
                'url'    => '',
            ];
        }

        // Open educational resources.
        $oer = $this->field('oer_resources');
        if ($oer !== '') {
            $out[] = [
                'tag'    => get_string('restag_oer', 'format_emd'),
                'title'  => get_string('openresources', 'format_emd'),
                'detail' => format_string(strip_tags($oer)),
                'url'    => '',
            ];
        }
        return $out;
    }

    /**
     * "What you'll learn" — the objectives field, one item per line.
     *
     * @return array list of ['text' => string]
     */
    protected function objectives(): array {
        $raw = trim(strip_tags($this->field('objectives')));
        if ($raw === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = ['text' => $line];
            }
        }
        return $out;
    }

    /**
     * "How every week is built" — the distinct activity types of the first real
     * weekly module (so the chips reflect this course's actual structure). Falls
     * back to the canonical master-template sequence only when no week is built yet.
     *
     * @return array list of ['label' => string]
     */
    protected function week_types(): array {
        $modinfo = get_fast_modinfo($this->course);
        $finalsection = null;
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->idnumber === master_template::ID_FINALEXAM) {
                $finalsection = (int) $cm->sectionnum;
            }
        }

        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section === 0 || $section->section === $finalsection) {
                continue;
            }
            $seen = [];
            $out = [];
            foreach (($modinfo->sections[$section->section] ?? []) as $cmid) {
                $cm = $modinfo->cms[$cmid];
                // Structural overview — key off the activity's own visibility, not the
                // viewer's per-user access (so locked-but-shown activities still appear).
                if (!$cm->visible || $cm->deletioninprogress) {
                    continue;
                }
                $tag = $this->activity_tag($cm);
                if (!isset($seen[$tag])) {
                    $seen[$tag] = true;
                    $out[] = ['label' => $tag];
                }
            }
            if ($out) {
                return $out;
            }
        }

        // No week built yet — show the canonical master-template sequence.
        $labels = ['act_overview', 'act_video', 'act_readings', 'act_h5plab',
            'act_discussion', 'act_assignment', 'act_reflection', 'act_quiz'];
        return array_map(fn($k) => ['label' => get_string($k, 'format_emd')], $labels);
    }

    /**
     * Weekly modules — real sections with their activities (first week expanded).
     *
     * @return array
     */
    protected function weeks(): array {
        global $DB;
        $modinfo = get_fast_modinfo($this->course);
        $finalsection = null;
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->idnumber === master_template::ID_FINALEXAM) {
                $finalsection = (int) $cm->sectionnum;
            }
        }

        $out = [];
        $weeknum = 0;
        $first = true;
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section === 0) {
                continue;
            }
            $isfinal = ($section->section === $finalsection);
            if (!$isfinal) {
                $weeknum++;
            }

            $acts = [];
            $questiontotal = 0;
            foreach (($modinfo->sections[$section->section] ?? []) as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if (!$cm->uservisible || $cm->deletioninprogress) {
                    continue;
                }
                $detail = '';
                if ($cm->modname === 'quiz') {
                    $instance = $DB->get_field('course_modules', 'instance', ['id' => $cm->id]);
                    $qcount = (int) $DB->count_records('quiz_slots', ['quizid' => $instance]);
                    $questiontotal += $qcount;
                    $detail = $qcount ? get_string('nquestions', 'format_emd', $qcount) : '';
                }
                $acts[] = [
                    'tag'   => $this->activity_tag($cm),
                    'name'  => format_string($cm->name),
                    'detail' => $detail,
                    'url'   => $cm->url ? $cm->url->out(false) : '',
                    'hasurl' => (bool) $cm->url,
                ];
            }

            $out[] = [
                'num'       => $isfinal ? '★' : $weeknum,
                'name'      => $section->name ?: get_string('weekn', 'format_emd', $weeknum),
                'isfinal'   => $isfinal,
                'itemcount' => count($acts),
                'summary'   => $isfinal ? get_string('finallabel', 'format_emd')
                                        : ($questiontotal ? get_string('nq', 'format_emd', $questiontotal) : ''),
                'expanded'  => $first && !$isfinal,
                'activities' => $acts,
            ];
            if (!$isfinal) {
                $first = false;
            }
        }
        return $out;
    }

    /**
     * Map a course module to a short badge label for the preview.
     *
     * @param \cm_info $cm
     * @return string
     */
    protected function activity_tag(\cm_info $cm): string {
        $name = \core_text::strtolower($cm->name);
        $map = [
            'quiz'   => 'tag_quiz',
            'forum'  => 'tag_discussion',
            'assign' => (strpos($name, 'reflection') !== false) ? 'tag_reflection' : 'tag_assignment',
            'url'    => 'tag_video',
        ];
        if (isset($map[$cm->modname])) {
            return get_string($map[$cm->modname], 'format_emd');
        }
        if ($cm->modname === 'page') {
            foreach (['video' => 'tag_video', 'reading' => 'tag_reading', 'overview' => 'tag_overview',
                      'h5p' => 'tag_h5p', 'syllabus' => 'tag_syllabus'] as $needle => $key) {
                if (strpos($name, $needle) !== false) {
                    return get_string($key, 'format_emd');
                }
            }
            return get_string('tag_page', 'format_emd');
        }
        return \core_text::strtoupper($cm->modname);
    }

    /**
     * Assessment breakdown — gradebook top-level category weights (live).
     *
     * @return array list of ['name' => string, 'weight' => int, 'haswidth' => bool]
     */
    protected function assessment(): array {
        $out = [];
        try {
            $cats = \grade_category::fetch_all(['courseid' => $this->course->id]) ?: [];
            foreach ($cats as $cat) {
                if ($cat->is_course_category()) {
                    continue;
                }
                $coef = (float) ($cat->load_grade_item()->aggregationcoef2 ?? 0);
                if ($coef <= 0) {
                    continue;
                }
                $pct = (int) round($coef * 100);
                $out[] = ['name' => format_string($cat->get_name()), 'weight' => $pct, 'haswidth' => true];
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    /**
     * The course instructor (enrolled editing teacher) + their reach counts.
     *
     * @param \context_course $context
     * @return array
     */
    protected function instructor(\context_course $context): array {
        global $DB;
        $none = ['has' => false];

        $teacherroles = array_keys(get_archetype_roles('editingteacher'));
        $teacher = null;
        if ($teacherroles) {
            $users = get_role_users($teacherroles, $context, false, 'u.id, u.firstname, u.lastname, u.description, u.descriptionformat');
            $teacher = $users ? reset($users) : null;
        }

        $name = $teacher ? fullname($teacher) : $this->field('faculty_name');
        if ($name === '') {
            return $none;
        }

        $data = [
            'has'      => true,
            'name'     => $name,
            'initials' => $this->initials($name),
            'bio'      => '',
            'courses'  => null,
            'students' => null,
            'hasrating' => false,
            'rating'    => 0,
        ];

        if ($teacher) {
            if (!empty($teacher->description)) {
                $data['bio'] = format_text($teacher->description, $teacher->descriptionformat ?? FORMAT_HTML,
                    ['context' => $context]);
            }
            [$courses, $students] = $this->instructor_reach((int) $teacher->id, $teacherroles);
            $data['courses'] = $courses;
            $data['students'] = $students;
            // Approved instructor rating (Phase 3), guarded for pre-upgrade safety.
            try {
                $rating = \local_azmsi\local\reviews::instructor_rating((int) $teacher->id);
                $data['hasrating'] = $rating['has'];
                $data['rating'] = $rating['avg'];
            } catch (\Throwable $e) {
                $data['hasrating'] = false;
            }
        }
        return $data;
    }

    /**
     * Count distinct courses the teacher teaches and distinct students across them.
     *
     * @param int $userid
     * @param array $teacherroles
     * @return array [courses, students]
     */
    protected function instructor_reach(int $userid, array $teacherroles): array {
        global $DB;
        if (!$teacherroles) {
            return [null, null];
        }
        [$tin, $tparams] = $DB->get_in_or_equal($teacherroles, SQL_PARAMS_NAMED, 'tr');
        $studentroles = array_keys(get_archetype_roles('student'));
        $coursectx = CONTEXT_COURSE;

        $courses = (int) $DB->get_field_sql(
            "SELECT COUNT(DISTINCT ctx.instanceid)
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :cl
              WHERE ra.userid = :uid AND ra.roleid $tin",
            ['uid' => $userid, 'cl' => $coursectx] + $tparams);

        $students = null;
        if ($studentroles) {
            [$sin, $sparams] = $DB->get_in_or_equal($studentroles, SQL_PARAMS_NAMED, 'sr');
            $students = (int) $DB->get_field_sql(
                "SELECT COUNT(DISTINCT ra2.userid)
                   FROM {role_assignments} ra
                   JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :cl
                   JOIN {context} ctx2 ON ctx2.contextlevel = :cl2 AND ctx2.instanceid = ctx.instanceid
                   JOIN {role_assignments} ra2 ON ra2.contextid = ctx2.id AND ra2.roleid $sin
                  WHERE ra.userid = :uid AND ra.roleid $tin",
                ['uid' => $userid, 'cl' => $coursectx, 'cl2' => $coursectx] + $tparams + $sparams);
        }
        return [$courses, $students];
    }

    /**
     * Rating block form for enrolled learners when the block is on the course.
     *
     * @param renderer_base $output
     * @return array
     */
    protected function rating_form(renderer_base $output): array {
        global $DB, $USER;

        $context = \context_course::instance($this->course->id);
        if (!is_enrolled($context, $USER, '', true)
                || !has_capability('local/azmsi:submitreview', $context)) {
            return ['hasratingform' => false];
        }
        if (!$DB->record_exists('block_instances', [
            'parentcontextid' => $context->id,
            'blockname'       => 'azmsi_rating',
        ])) {
            return ['hasratingform' => false];
        }
        if (!class_exists('\local_azmsi\output\rating_form')) {
            return ['hasratingform' => false];
        }

        return [
            'hasratingform'  => true,
            'ratingformhtml' => $output->render(new \local_azmsi\output\rating_form($this->course->id)),
        ];
    }

    /**
     * Approved course rating (avg + count), guarded for pre-upgrade safety.
     *
     * @return array ['has' => bool, 'avg' => float, 'count' => int]
     */
    protected function course_rating(): array {
        try {
            return \local_azmsi\local\reviews::course_rating($this->course->id);
        } catch (\Throwable $e) {
            return ['has' => false, 'avg' => 0.0, 'count' => 0];
        }
    }

    /**
     * Two-letter initials from a name.
     *
     * @param string $name
     * @return string
     */
    protected function initials(string $name): string {
        $parts = preg_split('/\s+/', trim($name));
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? end($parts) : '';
        return \core_text::strtoupper(\core_text::substr($first, 0, 1) . \core_text::substr($last, 0, 1));
    }

    /**
     * Prerequisite + next course, derived from the catalog sequence.
     *
     * @return array ['prereq' => string, 'next' => string, 'hasnext' => bool]
     */
    protected function pathway(): array {
        $code = $this->field('course_code') ?: $this->course->idnumber;
        $prereqnone = get_string('prereqnone', 'format_emd');

        if (!class_exists('\local_azmsi\local\program')) {
            return ['prereq' => $prereqnone, 'next' => '', 'hasnext' => false];
        }

        // Flatten the catalog into an ordered [code => name] list.
        $ordered = [];
        foreach (\local_azmsi\local\program::catalog() as $quarter) {
            foreach ($quarter['courses'] as [$ccode, $cname]) {
                $ordered[] = ['code' => $ccode, 'name' => $cname];
            }
        }
        $idx = null;
        foreach ($ordered as $i => $c) {
            if ($c['code'] === $code) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            return ['prereq' => $prereqnone, 'next' => '', 'hasnext' => false];
        }
        $prev = $idx > 0 ? $ordered[$idx - 1] : null;
        $next = isset($ordered[$idx + 1]) ? $ordered[$idx + 1] : null;
        return [
            'prereq'  => $prev ? "{$prev['code']} · {$prev['name']}" : $prereqnone,
            'next'    => $next ? "{$next['code']} · {$next['name']}" : '',
            'hasnext' => (bool) $next,
        ];
    }

    /**
     * Enrolment-card feature checklist — shown per the activity types the course has.
     *
     * @return array list of ['label' => string]
     */
    protected function features(): array {
        $types = [];
        foreach (get_fast_modinfo($this->course)->get_cms() as $cm) {
            $types[$cm->modname] = true;
        }
        $out = [];
        if (isset($types['page'])) {
            $out[] = ['label' => get_string('feat_videos', 'format_emd')];
        }
        if (isset($types['forum'])) {
            $out[] = ['label' => get_string('feat_forums', 'format_emd')];
        }
        if (isset($types['assign'])) {
            $out[] = ['label' => get_string('feat_assignments', 'format_emd')];
        }
        if (isset($types['quiz'])) {
            $out[] = ['label' => get_string('feat_quizzes', 'format_emd')];
        }
        return $out;
    }
}
