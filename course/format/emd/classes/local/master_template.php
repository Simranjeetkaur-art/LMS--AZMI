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

namespace format_emd\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the eMD master course template.
 *
 * A course gets a fixed structure: a "Course Introduction" section (Overview,
 * Welcome Video, Syllabus), one or more weekly-module sections (each with the
 * standard activity sequence: Video, Readings, H5P Lab, Discussion, Quiz,
 * Assignment, Reflection) and a course-level Final Exam in the last section.
 *
 * Every item is a real Moodle activity created through the same core path the
 * "Add an activity" form uses ({@see add_moduleinfo()}), so completion, grading,
 * the gradebook and events all stay native. The template stores no content of
 * its own — authors fill each placeholder in.
 *
 * @package    format_emd
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class master_template {

    /** @var string idnumber marker for the singleton Course Overview page. */
    const ID_OVERVIEW = 'emd_overview';

    /** @var string idnumber marker for the singleton Welcome Video page. */
    const ID_WELCOME = 'emd_welcomevideo';

    /** @var string idnumber marker for the singleton Syllabus page. */
    const ID_SYLLABUS = 'emd_syllabus';

    /** @var string idnumber marker for the singleton course-level Final Exam quiz. */
    const ID_FINALEXAM = 'emd_finalexam';

    /** @var int Fallback number of weeks when the course has no max_weeks value. */
    const MAX_WEEKS_DEFAULT = 10;

    /**
     * The standard weekly activity sequence (in display order).
     *
     * Each entry: [module name, lang key for the activity name].
     * "H5P Lab" is created as a Page placeholder (an h5pactivity cannot be created
     * empty — it requires a content package), which the author replaces with a real
     * H5P activity or H5P content embedded via the editor.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected static function week_sequence(): array {
        return [
            ['page',   'act_video'],
            ['page',   'act_readings'],
            ['page',   'act_h5plab'],
            ['forum',  'act_discussion'],
            ['quiz',   'act_quiz'],
            ['assign', 'act_assignment'],
            ['assign', 'act_reflection'],
        ];
    }

    /**
     * Idempotently apply the full master template to a (normally new, empty) course.
     *
     * Safe to call more than once: the singleton items (intro pages, final exam) are
     * guarded by an idnumber marker, and Week 1 is only created when the course has
     * no populated week yet.
     *
     * @param \stdClass $course the course record (format must be emd; not re-checked here)
     * @return void
     */
    public static function apply_to_course(\stdClass $course): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        // 1. Course Introduction (section 0): Overview, Welcome Video, Syllabus.
        self::ensure_intro($course);

        // 2. Week 1. New courses ship with default empty sections; use section 1,
        //    populate it, then trim the other (still-empty) auto-created sections so
        //    the course is exactly Intro -> Week 1 -> Final Exam.
        if (!self::course_has_week($course)) {
            course_create_sections_if_missing($course, [0, 1]);
            self::create_week_activities($course, 1);
            self::trim_empty_sections($course, 1);
        }

        // 3. Final Exam — a new section at the end.
        self::ensure_final_exam($course);

        rebuild_course_cache($course->id, true);
    }

    /**
     * Create a brand-new weekly-module section, populated with the standard activity
     * sequence, positioned just before the Final Exam (so the exam stays last).
     *
     * This backs the "Add a week" course action.
     *
     * @param \stdClass $course the course record
     * @return \section_info the new section
     */
    public static function add_week(\stdClass $course): \section_info {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        // Enforce the per-course week cap (e.g. a 10-week course cannot get Week 11).
        $max = self::get_max_weeks($course);
        if (self::count_weeks($course) >= $max) {
            throw new \moodle_exception('weekcapreached', 'format_emd', '', $max);
        }

        // Insert before the Final Exam section if one exists, else append at the end.
        $finalsection = self::find_section_number($course->id, self::ID_FINALEXAM);
        $position = is_null($finalsection) ? 0 : $finalsection;
        $section = course_create_section($course->id, $position);

        self::create_week_activities($course, $section->section);
        rebuild_course_cache($course->id, true);

        return get_fast_modinfo($course)->get_section_info($section->section);
    }

    /**
     * The course's configured number of weeks (the max_weeks custom field), or the
     * default when unset/invalid. This is the cap enforced by add_week().
     *
     * @param \stdClass $course
     * @return int
     */
    public static function get_max_weeks(\stdClass $course): int {
        $max = 0;
        foreach (\core_course\customfield\course_handler::create()->get_instance_data($course->id, true) as $data) {
            if ($data->get_field()->get('shortname') === 'max_weeks') {
                $max = (int) $data->export_value();
                break;
            }
        }
        return $max > 0 ? $max : self::MAX_WEEKS_DEFAULT;
    }

    /**
     * Number of week sections in the course (every section except section 0 and the
     * Final Exam section — counts empty weeks too, so they count toward the cap).
     *
     * @param \stdClass $course
     * @return int
     */
    public static function count_weeks(\stdClass $course): int {
        $finalsection = self::find_section_number($course->id, self::ID_FINALEXAM);
        $count = 0;
        foreach (get_fast_modinfo($course)->get_section_info_all() as $section) {
            if ($section->section === 0 || $section->section === $finalsection) {
                continue;
            }
            $count++;
        }
        return $count;
    }

    /**
     * Whether another week may be added (below the course's week cap).
     *
     * @param \stdClass $course
     * @return bool
     */
    public static function can_add_week(\stdClass $course): bool {
        return self::count_weeks($course) < self::get_max_weeks($course);
    }

    /**
     * Whether the course already has at least one populated week section (a section
     * other than section 0 and the Final Exam section that holds activities).
     *
     * @param \stdClass $course
     * @return bool
     */
    protected static function course_has_week(\stdClass $course): bool {
        $finalsection = self::find_section_number($course->id, self::ID_FINALEXAM);
        $modinfo = get_fast_modinfo($course);
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section === 0 || $section->section === $finalsection) {
                continue;
            }
            if (!empty($modinfo->sections[$section->section])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ensure the three Course Introduction pages exist in section 0.
     *
     * @param \stdClass $course
     * @return void
     */
    protected static function ensure_intro(\stdClass $course): void {
        $intro = [
            [self::ID_OVERVIEW, 'act_overview'],
            [self::ID_WELCOME,  'act_welcomevideo'],
            [self::ID_SYLLABUS, 'act_syllabus'],
        ];
        foreach ($intro as [$idnumber, $namekey]) {
            if (self::id_exists($course->id, $idnumber)) {
                continue;
            }
            $name = get_string($namekey, 'format_emd');
            self::try_create_activity($course, 0, 'page', $name, self::page_options($name), $idnumber);
        }
    }

    /**
     * Ensure the course-level Final Exam (a quiz in its own last section) exists.
     *
     * @param \stdClass $course
     * @return void
     */
    protected static function ensure_final_exam(\stdClass $course): void {
        global $CFG;
        if (self::id_exists($course->id, self::ID_FINALEXAM)) {
            return;
        }
        require_once($CFG->dirroot . '/course/lib.php');

        $section = course_create_section($course->id, 0); // Append at the end.
        course_update_section($course, $section, ['name' => get_string('finalexamname', 'format_emd')]);

        $name = get_string('act_finalexam', 'format_emd');
        self::try_create_activity($course, $section->section, 'quiz', $name, self::quiz_options(), self::ID_FINALEXAM);
    }

    /**
     * Create the standard weekly activity sequence in the given section number.
     *
     * @param \stdClass $course
     * @param int $sectionnum
     * @return void
     */
    protected static function create_week_activities(\stdClass $course, int $sectionnum): void {
        foreach (self::week_sequence() as [$modname, $namekey]) {
            $name = get_string($namekey, 'format_emd');
            $options = match ($modname) {
                'page'  => self::page_options($name),
                'forum' => self::forum_options(),
                'quiz'  => self::quiz_options(),
                'assign' => self::assign_options(),
                default => [],
            };
            self::try_create_activity($course, $sectionnum, $modname, $name, $options);
        }
    }

    /**
     * Create one activity, swallowing (and logging) any failure so a single bad
     * module never aborts the rest of the template.
     *
     * @param \stdClass $course
     * @param int $sectionnum
     * @param string $modname
     * @param string $name
     * @param array $options module-specific fields merged over the common defaults
     * @param string $idnumber optional course-module idnumber marker
     * @return void
     */
    protected static function try_create_activity(\stdClass $course, int $sectionnum, string $modname,
            string $name, array $options, string $idnumber = ''): void {
        global $DB;
        try {
            self::create_activity($course, $sectionnum, $modname, $name, $options, $idnumber);
        } catch (\Throwable $e) {
            // add_moduleinfo() opens a delegated transaction and throws without
            // rolling it back. Neither the course_created observer nor the add-week
            // endpoint runs inside an outer transaction, so it is safe to clear any
            // stranded transaction here and carry on with the remaining activities.
            if ($DB->is_transaction_started()) {
                $DB->force_transaction_rollback();
            }
            debugging("format_emd: could not create {$modname} \"{$name}\": " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Create one activity via the core add-module path.
     *
     * @param \stdClass $course
     * @param int $sectionnum
     * @param string $modname
     * @param string $name
     * @param array $options module-specific fields merged over the common defaults
     * @param string $idnumber optional course-module idnumber marker
     * @return void
     */
    protected static function create_activity(\stdClass $course, int $sectionnum, string $modname,
            string $name, array $options, string $idnumber = ''): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');

        $defaults = [
            'course'                    => $course->id,
            'modulename'                => $modname,
            'module'                    => $DB->get_field('modules', 'id', ['name' => $modname], MUST_EXIST),
            'section'                   => $sectionnum,
            'visible'                   => 1,
            'visibleoncoursepage'       => 1,
            'cmidnumber'                => $idnumber,
            'groupmode'                 => 0,
            'groupingid'                => 0,
            'availability'              => null,
            'completion'                => 0,
            'completionview'            => 0,
            'completionexpected'        => 0,
            'completionpassgrade'       => 0,
            'conditiongradegroup'       => [],
            'conditionfieldgroup'       => [],
            'conditioncompletiongroup'  => [],
            'name'                      => $name,
            'intro'                     => get_string('templateintro', 'format_emd'),
            'introformat'               => FORMAT_HTML,
        ];

        $moduleinfo = (object) array_merge($defaults, $options);
        add_moduleinfo($moduleinfo, $course);
    }

    /**
     * Common fields for a Page activity used as an editable content placeholder.
     *
     * @param string $name the activity name (interpolated into the placeholder body)
     * @return array
     */
    protected static function page_options(string $name): array {
        $content = get_string('templatecontent', 'format_emd', $name);
        if ($name === get_string('act_h5plab', 'format_emd')) {
            $content .= get_string('h5plabnote', 'format_emd');
        }
        return [
            'content'           => $content,
            'contentformat'     => FORMAT_HTML,
            'display'           => 0, // RESOURCELIB_DISPLAY_AUTO.
            'printintro'        => 0,
            'printheading'      => 1,
            'printlastmodified' => 1,
        ];
    }

    /**
     * Common fields for a general discussion Forum.
     *
     * @return array
     */
    protected static function forum_options(): array {
        return [
            'type'         => 'general',
            'forcesubscribe' => 0, // FORUM_CHOOSESUBSCRIBE.
            'assessed'     => 0,
            'scale'        => 0,
            'grade_forum'  => 0,
        ];
    }

    /**
     * Default Quiz settings (mirrors core's quiz generator defaults so add_moduleinfo
     * produces a valid, empty quiz ready for the author to add questions).
     *
     * @return array
     */
    protected static function quiz_options(): array {
        return [
            'timeopen'               => 0,
            'timeclose'              => 0,
            'preferredbehaviour'     => 'deferredfeedback',
            'attempts'               => 0,
            'attemptonlast'          => 0,
            'grademethod'            => 1, // QUIZ_GRADEHIGHEST.
            'decimalpoints'          => 2,
            'questiondecimalpoints'  => -1,
            'attemptduring'          => 1,
            'correctnessduring'      => 1,
            'maxmarksduring'         => 1,
            'marksduring'            => 1,
            'specificfeedbackduring' => 1,
            'generalfeedbackduring'  => 1,
            'rightanswerduring'      => 1,
            'overallfeedbackduring'  => 0,
            'attemptimmediately'          => 1,
            'correctnessimmediately'      => 1,
            'maxmarksimmediately'         => 1,
            'marksimmediately'            => 1,
            'specificfeedbackimmediately' => 1,
            'generalfeedbackimmediately'  => 1,
            'rightanswerimmediately'      => 1,
            'overallfeedbackimmediately'  => 1,
            'attemptopen'            => 1,
            'correctnessopen'        => 1,
            'maxmarksopen'           => 1,
            'marksopen'              => 1,
            'specificfeedbackopen'   => 1,
            'generalfeedbackopen'    => 1,
            'rightansweropen'        => 1,
            'overallfeedbackopen'    => 1,
            'attemptclosed'          => 1,
            'correctnessclosed'      => 1,
            'maxmarksclosed'         => 1,
            'marksclosed'            => 1,
            'specificfeedbackclosed' => 1,
            'generalfeedbackclosed'  => 1,
            'rightanswerclosed'      => 1,
            'overallfeedbackclosed'  => 1,
            'questionsperpage'       => 1,
            'navmethod'              => 'free',
            'shuffleanswers'         => 1,
            'sumgrades'              => 0,
            'grade'                  => 100,
            'timelimit'              => 0,
            'overduehandling'        => 'autosubmit',
            'graceperiod'            => 86400,
            // These columns are NOT NULL with no DB default — must be set explicitly
            // (strict databases such as PostgreSQL reject the null the form omits).
            'quizpassword'           => '',
            'subnet'                 => '',
            'browsersecurity'        => '-',
        ];
    }

    /**
     * Default Assignment settings (mirrors core's assign generator defaults, with the
     * online-text submission method enabled so the placeholder is immediately usable).
     *
     * @return array
     */
    protected static function assign_options(): array {
        return [
            'alwaysshowdescription'        => 1,
            'submissiondrafts'             => 0,
            'requiresubmissionstatement'   => 0,
            'sendnotifications'            => 0,
            'sendstudentnotifications'     => 1,
            'sendlatenotifications'        => 0,
            'duedate'                      => 0,
            'allowsubmissionsfromdate'     => 0,
            'grade'                        => 100,
            'cutoffdate'                   => 0,
            'gradingduedate'               => 0,
            'teamsubmission'               => 0,
            'requireallteammemberssubmit'  => 0,
            'teamsubmissiongroupingid'     => 0,
            'blindmarking'                 => 0,
            'attemptreopenmethod'          => 'none',
            'maxattempts'                  => -1, // Unlimited.
            'markingworkflow'              => 0,
            'markingallocation'            => 0,
            'markinganonymous'             => 0,
            'activityformat'               => 0,
            'timelimit'                    => 0,
            'submissionattachments'        => 0,
            // Submission/feedback plugins: enable online text + inline comments.
            'assignsubmission_onlinetext_enabled'           => 1,
            'assignsubmission_onlinetext_wordlimit'         => 0,
            'assignsubmission_onlinetext_wordlimit_enabled' => 0,
            'assignsubmission_file_enabled'                 => 0,
            'assignsubmission_comments_enabled'             => 0,
            'assignfeedback_comments_enabled'               => 1,
            'assignfeedback_comments_commentinline'         => 0,
            'assignfeedback_file_enabled'                   => 0,
            'assignfeedback_offline_enabled'                => 0,
        ];
    }

    /**
     * Section number (not id) of the course module carrying the given idnumber, or null.
     *
     * @param int $courseid
     * @param string $idnumber
     * @return int|null
     */
    protected static function find_section_number(int $courseid, string $idnumber): ?int {
        global $DB;
        $sql = "SELECT cs.section
                  FROM {course_modules} cm
                  JOIN {course_sections} cs ON cs.id = cm.section
                 WHERE cm.course = :courseid AND cm.idnumber = :idnumber";
        $section = $DB->get_field_sql($sql, ['courseid' => $courseid, 'idnumber' => $idnumber]);
        return ($section === false) ? null : (int) $section;
    }

    /**
     * Whether a course module with the given idnumber marker already exists.
     *
     * @param int $courseid
     * @param string $idnumber
     * @return bool
     */
    protected static function id_exists(int $courseid, string $idnumber): bool {
        global $DB;
        return $DB->record_exists('course_modules', ['course' => $courseid, 'idnumber' => $idnumber]);
    }

    /**
     * Delete empty sections whose number is greater than $keep (used to tidy the
     * default empty sections a brand-new course ships with). Never deletes section 0
     * or any section that holds activities.
     *
     * @param \stdClass $course
     * @param int $keep highest section number to preserve regardless
     * @return void
     */
    protected static function trim_empty_sections(\stdClass $course, int $keep): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        // Reverse order so deleting one does not renumber those still to check.
        foreach (array_reverse($sections) as $section) {
            if ($section->section <= $keep) {
                continue;
            }
            if (empty($modinfo->sections[$section->section])) {
                course_delete_section($course, $section, false);
            }
        }
    }
}
