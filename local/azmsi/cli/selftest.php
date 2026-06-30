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

/**
 * AZMSI feature self-test (CLI).
 *
 * Exercises the eMD master template, the week cap, the learner course preview,
 * the ratings (submit -> moderate -> aggregate) flow and the content-completeness
 * analysis — end to end against a throwaway course that is created and deleted by
 * the run. Intended for automated/agent verification.
 *
 *   php local/azmsi/cli/selftest.php            # run all checks, clean up after
 *   php local/azmsi/cli/selftest.php --keep     # leave the test course for inspection
 *   php local/azmsi/cli/selftest.php --help
 *
 * Exit code is 0 when every check passes, 1 otherwise.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// Symlink-safe config include (the plugin is symlinked into public/).
require(is_file(__DIR__ . '/../../../config.php')
    ? __DIR__ . '/../../../config.php'
    : (getenv('MOODLE_ROOT') ? rtrim(getenv('MOODLE_ROOT'), '/') . '/config.php' : '/var/www/moodle/config.php'));
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');

use local_azmsi\local\reviews;
use local_azmsi\local\completeness;
use format_emd\local\master_template as emd_template;

[$options, $unrecognised] = cli_get_params(
    ['help' => false, 'keep' => false],
    ['h' => 'help', 'k' => 'keep']
);
if ($options['help']) {
    cli_writeln("AZMSI feature self-test.\n\n  --keep   Leave the throwaway test course in place.\n  --help   Show this help.");
    exit(0);
}

// Run as admin so renders and capability/enrolment reads behave.
\core\session\manager::set_user(get_admin());

// ---------------------------------------------------------------------------
// Tiny assertion harness.
// ---------------------------------------------------------------------------
$passed = 0;
$failed = 0;
$failures = [];
$check = function (string $label, bool $cond) use (&$passed, &$failed, &$failures) {
    if ($cond) {
        $passed++;
        cli_writeln("  [PASS] {$label}");
    } else {
        $failed++;
        $failures[] = $label;
        cli_writeln("  [FAIL] {$label}");
    }
};
$section = fn(string $t) => cli_writeln("\n== {$t} ==");

/**
 * Set a course custom field value (read-after-write is reliable in one run).
 */
$setfield = function (int $courseid, string $shortname, string $value): void {
    $handler = \core_course\customfield\course_handler::create();
    $data = (object) ['id' => $courseid, "customfield_{$shortname}" => $value];
    $handler->instance_form_save($data, false);
};

$testcourseid = 0;
$adminid = (int) get_admin()->id;

try {
    // -----------------------------------------------------------------------
    $section('1. Plugins & schema installed');
    $check('format_emd installed', (bool) get_config('format_emd', 'version'));
    $check('local_azmsi installed', (bool) get_config('local_azmsi', 'version'));
    $check('block_azmsi_rating installed', (bool) get_config('block_azmsi_rating', 'version'));
    $check('review table exists', $DB->get_manager()->table_exists('local_azmsi_review'));
    foreach (['max_weeks', 'contact_hours', 'textbook', 'objectives', 'delivery_mode'] as $f) {
        $check("custom field '{$f}' exists",
            $DB->record_exists_sql("SELECT 1 FROM {customfield_field} f
                JOIN {customfield_category} c ON c.id = f.categoryid
                WHERE c.component = 'core_course' AND f.shortname = ?", [$f]));
    }
    $check('capability submitreview defined', (bool) get_capability_info('local/azmsi:submitreview'));
    $check('capability moderatereviews defined', (bool) get_capability_info('local/azmsi:moderatereviews'));

    // -----------------------------------------------------------------------
    $section('2. Master template auto-populates a new eMD course');
    $catid = $DB->get_field_sql('SELECT id FROM {course_categories} ORDER BY id ASC', [], IGNORE_MULTIPLE);
    $course = create_course((object) [
        'fullname'  => 'ZZ AZMSI Self-Test',
        'shortname' => 'ZZSELFTEST' . time(),
        'category'  => $catid,
        'format'    => 'emd',
    ]);
    $testcourseid = (int) $course->id;

    $byid = [];
    $counts = ['page' => 0, 'forum' => 0, 'quiz' => 0, 'assign' => 0];
    foreach (get_fast_modinfo($course)->get_cms() as $cm) {
        if ($cm->idnumber) {
            $byid[$cm->idnumber] = $cm;
        }
        if (isset($counts[$cm->modname])) {
            $counts[$cm->modname]++;
        }
    }
    $check('intro: Course Overview page', isset($byid[emd_template::ID_OVERVIEW]));
    $check('intro: Welcome Video page', isset($byid[emd_template::ID_WELCOME]));
    $check('intro: Syllabus page', isset($byid[emd_template::ID_SYLLABUS]));
    $check('course-level Final Exam quiz', isset($byid[emd_template::ID_FINALEXAM]));
    // Week 1 holds the 7-item master set (3 pages incl. intro pages are in sec 0,
    // so course-wide: >=6 pages, >=1 forum incl. Announcements, >=1 quiz, >=2 assign).
    $check('quiz present (week + final)', $counts['quiz'] >= 2);
    $check('assignments present (assignment + reflection)', $counts['assign'] >= 2);
    $check('exactly one week (Final Exam excluded)', emd_template::count_weeks($course) === 1);

    // -----------------------------------------------------------------------
    $section('3. "Add a week" + week cap');
    $check('default max weeks = 10', emd_template::get_max_weeks($course) === 10);

    $setfield($testcourseid, 'max_weeks', '1');
    $course = get_course($testcourseid);
    $check('cap honoured: cannot add when at limit', emd_template::can_add_week($course) === false);
    $threw = false;
    try {
        emd_template::add_week($course);
    } catch (\moodle_exception $e) {
        $threw = ($e->errorcode === 'weekcapreached');
    }
    $check('add_week refuses past the cap (weekcapreached)', $threw);

    $setfield($testcourseid, 'max_weeks', '3');
    $course = get_course($testcourseid);
    $check('cap raised: can add again', emd_template::can_add_week($course) === true);
    emd_template::add_week($course);
    emd_template::add_week($course);
    $check('two weeks added (now 3)', emd_template::count_weeks($course) === 3);
    $check('cap reached again at 3', emd_template::can_add_week($course) === false);

    // -----------------------------------------------------------------------
    $section('4. Learner course-preview renders');
    $PAGE->set_course($course);
    $PAGE->set_url('/course/view.php', ['id' => $testcourseid]);
    $PAGE->set_pagelayout('course');
    $renderer = $PAGE->get_renderer('format_emd');
    $html = $renderer->render(new \format_emd\output\coursepreview($course));
    $check('preview renders non-empty', strlen($html) > 1000);
    $check('preview has weekly modules', strpos($html, 'az-prev-weeks') !== false);
    $check('preview has activity modal', strpos($html, 'az-activity-modal') !== false);

    // -----------------------------------------------------------------------
    $section('5. Ratings: submit -> pending -> approve -> aggregate');
    // Rate this course; use admin as a stand-in instructor id (isolated to this run).
    reviews::save($testcourseid, $adminid, $adminid, 5, 'Self-test course review', 4, 'Self-test instructor review');
    $mine = reviews::get_user_review($testcourseid, $adminid);
    $check('review saved as pending', $mine && $mine->status === reviews::PENDING);
    $check('pending not counted in course rating', reviews::course_rating($testcourseid)['has'] === false);

    reviews::set_status((int) $mine->id, reviews::APPROVED, $adminid);
    $cr = reviews::course_rating($testcourseid);
    $ir = reviews::instructor_rating($adminid);
    $check('approved course rating avg = 5.0', $cr['has'] && (float) $cr['avg'] === 5.0);
    $check('approved instructor rating avg = 4.0', $ir['has'] && (float) $ir['avg'] === 4.0);

    reviews::set_status((int) $mine->id, reviews::REJECTED, $adminid);
    $check('rejected review excluded from average', reviews::course_rating($testcourseid)['has'] === false);

    // -----------------------------------------------------------------------
    $section('6. Content-completeness analysis');
    $a = completeness::analyze($course);
    $check('analysis counts 3 weeks (final excluded)', $a['totalweeks'] === 3);
    $week1 = $a['weeks'][0] ?? null;
    $check('week 1 has all required activities present', $week1 && $week1['activitiesok'] === true);
    $check('week 1 content incomplete (placeholders)', $week1 && $week1['contentok'] === false);
    $check('course not complete (placeholders + missing fields)', $a['iscomplete'] === false);

    $level = [];
    foreach ($a['courselevel'] as $c) {
        $level[$c['key']] = $c['ok'];
    }
    $check('course-level: contact hours initially unset', ($level['contacthours'] ?? true) === false);
    $check('course-level: textbook initially unset', ($level['textbook'] ?? true) === false);

    // Set the two fields and confirm the checklist flips.
    $setfield($testcourseid, 'contact_hours', '45');
    $setfield($testcourseid, 'textbook', 'Medical Terminology for Health Professions');
    $a2 = completeness::analyze(get_course($testcourseid));
    $level2 = [];
    foreach ($a2['courselevel'] as $c) {
        $level2[$c['key']] = $c['ok'];
    }
    $check('course-level: contact hours now set', ($level2['contacthours'] ?? false) === true);
    $check('course-level: textbook now set', ($level2['textbook'] ?? false) === true);

} catch (\Throwable $e) {
    $failed++;
    $failures[] = 'Uncaught exception: ' . $e->getMessage();
    cli_writeln("\n  [ERROR] " . $e->getMessage() . "\n  " . $e->getFile() . ':' . $e->getLine());
} finally {
    // Always clean up: drop test reviews, then the test course (unless --keep).
    if ($testcourseid) {
        $DB->delete_records('local_azmsi_review', ['courseid' => $testcourseid]);
        $DB->delete_records('local_azmsi_review', ['instructorid' => $adminid]);
        if (!$options['keep']) {
            delete_course($testcourseid, false);
            cli_writeln("\n  (cleaned up test course {$testcourseid})");
        } else {
            cli_writeln("\n  (kept test course {$testcourseid} as requested)");
        }
    }
}

// ---------------------------------------------------------------------------
cli_writeln("\n----------------------------------------");
cli_writeln(sprintf("RESULT: %d passed, %d failed", $passed, $failed));
if ($failures) {
    cli_writeln("Failures:");
    foreach ($failures as $f) {
        cli_writeln("  - {$f}");
    }
}
cli_writeln("----------------------------------------");
exit($failed === 0 ? 0 : 1);
