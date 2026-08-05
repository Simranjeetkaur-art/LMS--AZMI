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

namespace local_contentchecker;

use local_contentchecker\local\dashboard;
use local_contentchecker\local\questions;
use local_contentchecker\local\templates;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/local/contentchecker/tests/fixtures/stub_backend.php');

/**
 * Tests for publish layouts, status badges and follow-up question delivery.
 *
 * The gradebook tests here are the point of the exercise: "these questions are
 * not graded" is a promise about what does NOT happen, and the only way to
 * check that is to run the flow and then look at the gradebook.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_contentchecker\local\templates
 * @covers     \local_contentchecker\local\dashboard
 * @covers     \local_contentchecker\local\questions
 */
final class delivery_test extends \advanced_testcase {

    /**
     * Every shipped layout renders with real content in every slot.
     *
     * @return void
     */
    public function test_all_four_layouts_render(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        $PAGE->set_context(\context_system::instance());
        $renderer = $PAGE->get_renderer('local_contentchecker');

        $config = [
            'media' => '<img src="heart.png" alt="Heart">',
            'body' => '<p>The heart has four chambers.</p>',
            'intro' => '<p>Compare the two drug classes.</p>',
            'sidebar' => '<ul><li>Four chambers</li></ul>',
            'callouts' => [['title' => 'Clinical note', 'body' => '<p>Avoid in asthma.</p>']],
            'tabs' => [
                ['title' => 'Beta blockers', 'body' => '<p>Slow the heart.</p>'],
                ['title' => 'ACE inhibitors', 'body' => '<p>May cause cough.</p>'],
            ],
        ];

        $layouts = templates::all();
        $this->assertCount(4, $layouts, 'the spec asks for four layouts');

        foreach ($layouts as $key => $spec) {
            $html = $renderer->render_from_template($spec['mustache'],
                templates::context($key, $config));

            $this->assertNotEmpty(trim($html), "{$key} rendered nothing");
            $this->assertStringContainsString('cct-layout-' . $key, $html);

            // Every slot the layout declares must actually reach the output.
            foreach ($spec['slots'] as $slot) {
                $needle = [
                    'media' => 'heart.png',
                    'body' => 'four chambers',
                    'intro' => 'Compare the two',
                    'sidebar' => 'Four chambers',
                    'callouts' => 'Clinical note',
                    'tabs' => 'Beta blockers',
                ][$slot];
                $this->assertStringContainsString($needle, $html,
                    "{$key} did not render its {$slot} slot");
            }
        }
    }

    /**
     * The tabbed layout produces real tab semantics, not styled links.
     *
     * @return void
     */
    public function test_tabbed_layout_is_accessible(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());

        $html = $PAGE->get_renderer('local_contentchecker')->render_from_template(
            'local_contentchecker/layout_tabbedcomparison',
            templates::context('tabbedcomparison', [
                'intro' => '<p>Intro.</p>',
                'tabs' => [
                    ['title' => 'A', 'body' => '<p>a</p>'],
                    ['title' => 'B', 'body' => '<p>b</p>'],
                ],
            ]));

        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('role="tab"', $html);
        $this->assertStringContainsString('role="tabpanel"', $html);
        $this->assertStringContainsString('aria-selected="true"', $html);
        $this->assertStringContainsString('aria-controls=', $html);
    }

    /**
     * A layout choice persists per section and can be changed afterwards.
     *
     * @return void
     */
    public function test_template_persists_and_is_re_editable(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $section = $DB->get_record('course_sections',
            ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);

        templates::set((int) $course->id, (int) $section->id, 0, 'medialeft',
            ['media' => '<img src="a.png" alt="a">', 'body' => '<p>first</p>']);

        $stored = templates::get((int) $section->id, 0);
        $this->assertNotNull($stored);
        $this->assertSame('medialeft', $stored->templatekey);
        $this->assertSame('<p>first</p>', json_decode($stored->config, true)['body']);

        // Change it: this must UPDATE, not create a second row, or the unique
        // index would be violated and the old choice would linger.
        templates::set((int) $course->id, (int) $section->id, 0, 'tabbedcomparison',
            ['intro' => '<p>second</p>', 'tabs' => []]);

        $this->assertSame(1, $DB->count_records('local_cchecker_templates',
            ['sectionid' => $section->id, 'cmid' => 0]));

        $stored = templates::get((int) $section->id, 0);
        $this->assertSame('tabbedcomparison', $stored->templatekey);
        $this->assertSame('<p>second</p>', json_decode($stored->config, true)['intro']);

        // And the change is auditable.
        $this->assertTrue($DB->record_exists('local_cchecker_audit',
            ['objecttype' => 'template', 'action' => 'updated']));
    }

    /**
     * A page-level choice wins over the section-level one.
     *
     * @return void
     */
    public function test_page_level_template_overrides_section(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $section = $DB->get_record('course_sections',
            ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);
        $page = $this->getDataGenerator()->create_module('page',
            ['course' => $course->id, 'section' => 1]);

        templates::set((int) $course->id, (int) $section->id, 0, 'medialeft', []);
        templates::set((int) $course->id, (int) $section->id, (int) $page->cmid,
            'fullwidthvisual', []);

        $this->assertSame('fullwidthvisual',
            templates::get((int) $section->id, (int) $page->cmid)->templatekey);
        $this->assertSame('medialeft',
            templates::get((int) $section->id, 0)->templatekey);
    }

    /**
     * An unknown layout key is refused rather than stored.
     *
     * @return void
     */
    public function test_unknown_layout_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $this->expectException(\moodle_exception::class);
        templates::set((int) $course->id, 1, 0, 'not-a-layout', []);
    }

    /**
     * A week that has never been checked reports "never", and one whose
     * findings are all decided reports "ok".
     *
     * @return void
     */
    public function test_status_badges_reflect_reality(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'content' => '<p>' . str_repeat('Clinical prose. ', 30) . '</p>',
            'contentformat' => FORMAT_HTML,
        ]);

        $rows = dashboard::for_course((int) $course->id);
        $week1 = null;
        foreach ($rows as $row) {
            if ($row->sectionnum === 1) {
                $week1 = $row;
            }
        }
        $this->assertNotNull($week1);
        $this->assertSame(dashboard::NEVER, $week1->status);

        // A completed check with a flagged, undecided finding -> needs review.
        $checkid = $DB->insert_record('local_cchecker_checks', (object) [
            'courseid' => $course->id, 'sectionnum' => 1, 'cmid' => 0, 'jobid' => 0,
            'runmode' => 'live', 'status' => 'complete', 'usermodified' => 2,
            'timequeued' => time(), 'timefinished' => time(),
        ]);
        $cmid = $DB->get_field_sql(
            'SELECT MIN(id) FROM {course_modules} WHERE course = ?', [$course->id]);
        $DB->insert_record('local_cchecker_suggestions', (object) [
            'checkid' => $checkid, 'cmid' => $cmid, 'itemtype' => 'page',
            'itemname' => 'x', 'claim' => 'c', 'kind' => 'factual',
            'verdict' => 'contradicted', 'topscore' => 0.9, 'quoteverbatim' => 0,
            'confidence' => 0.5, 'decision' => 'pending', 'applied' => 0,
            'timecreated' => time(),
        ]);

        $rows = dashboard::for_course((int) $course->id);
        foreach ($rows as $row) {
            if ($row->sectionnum === 1) {
                $this->assertSame(dashboard::NEEDS_REVIEW, $row->status);
                $this->assertSame(1, $row->pending);
            }
        }

        // Once decided, the week goes green without needing a re-run.
        $DB->set_field('local_cchecker_suggestions', 'decision', 'rejected',
            ['checkid' => $checkid]);
        foreach (dashboard::for_course((int) $course->id) as $row) {
            if ($row->sectionnum === 1) {
                $this->assertSame(dashboard::OK, $row->status);
            }
        }

        // A failed check surfaces as failed.
        $DB->set_field('local_cchecker_checks', 'status', 'failed', ['id' => $checkid]);
        foreach (dashboard::for_course((int) $course->id) as $row) {
            if ($row->sectionnum === 1) {
                $this->assertSame(dashboard::FAILED, $row->status);
            }
        }
    }

    /**
     * Generating and approving follow-up questions creates NO gradebook item
     * and no grade for anyone.
     *
     * This is the explicit non-graded verification: the promise is about what
     * does not happen, so the check is made against the gradebook itself.
     *
     * @return void
     */
    public function test_questions_never_touch_the_gradebook(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<h2>Cardiac anatomy</h2><p>'
                . str_repeat('The heart has four chambers. ', 20) . '</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $before = $DB->count_records('grade_items', ['courseid' => $course->id]);

        $backend = new stub_backend([[
            'questions' => [
                [
                    'qtype' => 'multichoice',
                    'qtext' => 'How many chambers does the heart have?',
                    'options' => ['Two', 'Three', 'Four', 'Five'],
                    'answer' => [2],
                    'explanation' => 'Two atria and two ventricles.',
                ],
                [
                    'qtype' => 'truefalse',
                    'qtext' => 'The heart has four chambers.',
                    'options' => ['True', 'False'],
                    'answer' => [0],
                    'explanation' => 'It does.',
                ],
                [
                    'qtype' => 'checkbox',
                    'qtext' => 'Which are heart chambers?',
                    'options' => ['Atrium', 'Ventricle', 'Alveolus', 'Nephron'],
                    'answer' => [0, 1],
                    'explanation' => 'Atria and ventricles.',
                ],
            ],
        ]]);

        $created = (new questions($backend))->generate_for_cm((int) $page->cmid);
        $this->assertSame(3, $created, 'all three question types should be accepted');

        // Everything starts as a draft, invisible to learners.
        $this->assertSame(0, count(questions::approved_for((int) $page->cmid)));
        $drafts = $DB->get_records('local_cchecker_questions', ['cmid' => $page->cmid]);
        foreach ($drafts as $draft) {
            $this->assertSame('draft', $draft->status);
            questions::set_status((int) $draft->id, 'approved');
        }

        $approved = questions::approved_for((int) $page->cmid);
        $this->assertCount(3, $approved);

        // All three types survived with a usable answer key for client-side
        // marking.
        $types = array_column($approved, 'qtype');
        sort($types);
        $this->assertSame(['checkbox', 'multichoice', 'truefalse'], $types);
        foreach ($approved as $question) {
            $this->assertNotEmpty($question->answer);
            $this->assertNotEmpty($question->explanation);
        }

        // The actual promise: nothing reached the gradebook.
        $this->assertSame($before,
            $DB->count_records('grade_items', ['courseid' => $course->id]),
            'approving questions must not create a grade item');
        $this->assertFalse($DB->record_exists('grade_items',
            ['courseid' => $course->id, 'itemmodule' => 'contentchecker']));
        $this->assertFalse($DB->record_exists('grade_items',
            ['courseid' => $course->id, 'itemtype' => 'manual',
             'itemname' => 'Content checker']));

        $items = $DB->get_records('grade_items', ['courseid' => $course->id]);
        foreach ($items as $item) {
            $this->assertNotSame('local_contentchecker', $item->itemmodule);
        }

        // And no grade rows exist for the learner from this plugin.
        $grades = $DB->count_records_sql(
            'SELECT COUNT(g.id) FROM {grade_grades} g
               JOIN {grade_items} i ON i.id = g.itemid
              WHERE i.courseid = ? AND g.userid = ?',
            [$course->id, $student->id]);
        $this->assertSame(0, $grades);

        // No completion state was written either.
        $this->assertFalse($DB->record_exists('course_modules_completion',
            ['coursemoduleid' => $page->cmid]));
    }

    /**
     * Queueing a whole-course job creates one check per populated week and an
     * adhoc task to run each, so cron makes progress in bounded steps.
     *
     * @return void
     */
    public function test_background_job_fans_out_per_week(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['numsections' => 4]);
        // Two weeks with content, one deliberately left empty.
        foreach ([1, 3] as $section) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'section' => $section,
                'content' => '<p>' . str_repeat('Clinical prose. ', 30) . '</p>',
                'contentformat' => FORMAT_HTML,
            ]);
        }

        $job = \local_contentchecker\local\job_manager::queue([(int) $course->id]);

        $this->assertSame('queued', $job->status);
        $this->assertSame(2, (int) $job->numchecks, 'empty weeks should not be queued');

        $checks = $DB->get_records('local_cchecker_checks', ['jobid' => $job->id]);
        $this->assertCount(2, $checks);
        foreach ($checks as $check) {
            $this->assertSame('background', $check->runmode);
            $this->assertSame('queued', $check->status);
            $this->assertContains((int) $check->sectionnum, [1, 3]);
        }

        // One adhoc task per check, ready for cron.
        $tasks = \core\task\manager::get_adhoc_tasks(
            \local_contentchecker\task\verify_course_adhoc::class);
        $this->assertCount(2, $tasks);

        // Completing both checks settles the job and marks it for notification.
        foreach ($checks as $check) {
            $DB->set_field('local_cchecker_checks', 'status', 'complete', ['id' => $check->id]);
        }
        \local_contentchecker\local\job_manager::check_finished((int) $job->id);

        $settled = $DB->get_record('local_cchecker_jobs', ['id' => $job->id]);
        $this->assertSame('complete', $settled->status);
        $this->assertSame(2, (int) $settled->numdone);
    }

    /**
     * A job counts completions from the check rows, so a task retried by cron
     * cannot push the progress count past the total.
     *
     * @return void
     */
    public function test_job_progress_cannot_overshoot(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'content' => '<p>' . str_repeat('Prose. ', 60) . '</p>',
            'contentformat' => FORMAT_HTML,
        ]);

        $job = \local_contentchecker\local\job_manager::queue([(int) $course->id]);
        $DB->set_field('local_cchecker_checks', 'status', 'complete', ['jobid' => $job->id]);

        // Called repeatedly, as a cron retry would.
        for ($i = 0; $i < 3; $i++) {
            \local_contentchecker\local\job_manager::check_finished((int) $job->id);
        }

        $settled = $DB->get_record('local_cchecker_jobs', ['id' => $job->id]);
        $this->assertSame(1, (int) $settled->numdone);
        $this->assertSame(1, (int) $settled->numchecks);
    }

    /**
     * Editors can regenerate drafts without destroying approved questions.
     *
     * @return void
     */
    public function test_regenerate_preserves_approved_questions(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<h2>Anatomy</h2><p>' . str_repeat('Detail here. ', 30) . '</p>',
            'contentformat' => FORMAT_HTML,
        ]);

        $one = [[
            'questions' => [[
                'qtype' => 'truefalse', 'qtext' => 'First question.',
                'options' => ['True', 'False'], 'answer' => [0], 'explanation' => 'Yes.',
            ]],
        ]];

        (new questions(new stub_backend($one)))->generate_for_cm((int) $page->cmid);
        $first = $DB->get_records('local_cchecker_questions', ['cmid' => $page->cmid]);
        $this->assertCount(1, $first);
        questions::set_status((int) reset($first)->id, 'approved');

        // Regenerating with replace=true clears DRAFTS only.
        (new questions(new stub_backend($one)))->generate_for_cm((int) $page->cmid, true);

        $approved = $DB->count_records('local_cchecker_questions',
            ['cmid' => $page->cmid, 'status' => 'approved']);
        $this->assertSame(1, $approved, 'an approved question must survive regeneration');
    }
}
