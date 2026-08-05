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

use local_contentchecker\local\decision;
use local_contentchecker\local\pipeline;

/**
 * End-to-end tests for the human decision on an AI suggestion.
 *
 * This is the path that writes to live medical content, so every branch is
 * driven for real: approve changes the activity, reject does not, edit writes
 * the reviewer's wording rather than the model's, and an unlocatable sentence
 * is refused instead of guessed at. Each is checked against what actually
 * landed in the database, not against a return value.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_contentchecker\local\decision
 * @covers     \local_contentchecker\local\applier
 * @covers     \local_contentchecker\local\pipeline
 */
final class decision_test extends \advanced_testcase {

    /** @var string The sentence a suggestion targets. */
    const TARGET = 'The human heart has three chambers.';

    /** @var string What the model proposes instead. */
    const FIXED = 'The human heart has four chambers.';

    /**
     * Build a course, a page, a check and one pending suggestion.
     *
     * @param string|null $currenttext Override the located sentence.
     * @return array [course, page, suggestionid]
     */
    private function scaffold(?string $currenttext = self::TARGET): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Introduction paragraph here.</p><p>' . self::TARGET
                . '</p><p>' . str_repeat('Further detail. ', 25) . '</p>',
            'contentformat' => FORMAT_HTML,
        ]);

        $checkid = $DB->insert_record('local_cchecker_checks', (object) [
            'courseid' => $course->id,
            'sectionnum' => 0,
            'cmid' => $page->cmid,
            'jobid' => 0,
            'runmode' => 'live',
            'status' => 'complete',
            'usermodified' => 2,
            'timequeued' => time(),
            'timefinished' => time(),
        ]);

        $suggestionid = $DB->insert_record('local_cchecker_suggestions', (object) [
            'checkid' => $checkid,
            'cmid' => $page->cmid,
            'itemtype' => 'page',
            'itemname' => 'Week 1',
            'claim' => 'The heart has three chambers.',
            'kind' => 'factual',
            'verdict' => 'contradicted',
            'quorum' => '3/3',
            'topscore' => 0.9,
            'currenttext' => $currenttext,
            'suggestedtext' => self::FIXED,
            'sourceref' => 'https://example.org/heart',
            'quoteverbatim' => 1,
            'confidence' => 0.95,
            'reasoning' => 'The source says four.',
            'decision' => 'pending',
            'applied' => 0,
            'timecreated' => time(),
        ]);

        return [$course, $page, $suggestionid];
    }

    /**
     * The page's stored content.
     *
     * @param \stdClass $page The page.
     * @return string Stored HTML.
     */
    private function stored(\stdClass $page): string {
        global $DB;
        return (string) $DB->get_field('page', 'content', ['id' => $page->id]);
    }

    /**
     * Approving rewrites the live activity and records who did it.
     *
     * @return void
     */
    public function test_approve_changes_live_content_and_audits(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $page, $suggestionid] = $this->scaffold();

        $result = decision::record($suggestionid, 'approve');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['applied']);

        $stored = $this->stored($page);
        $this->assertStringContainsString(self::FIXED, $stored);
        $this->assertStringNotContainsString(self::TARGET, $stored);
        // Surrounding content untouched.
        $this->assertStringContainsString('Introduction paragraph here.', $stored);

        $row = $DB->get_record('local_cchecker_suggestions', ['id' => $suggestionid]);
        $this->assertSame('approved', $row->decision);
        $this->assertSame(1, (int) $row->applied);
        $this->assertSame((int) $USER->id, (int) $row->decidedby);
        $this->assertNotEmpty($row->decidedat);

        $audit = $DB->get_record('local_cchecker_audit', [
            'objecttype' => 'suggestion', 'objectid' => $suggestionid, 'action' => 'applied']);
        $this->assertNotFalse($audit);
        $this->assertSame((int) $USER->id, (int) $audit->userid);
        // The audit holds the real before/after of the field that changed.
        $this->assertStringContainsString(self::TARGET, $audit->beforetext);
        $this->assertStringContainsString(self::FIXED, $audit->aftertext);
        $this->assertSame((int) $course->id, (int) $audit->courseid);
    }

    /**
     * Rejecting records the decision and leaves the content alone.
     *
     * @return void
     */
    public function test_reject_leaves_content_untouched(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [, $page, $suggestionid] = $this->scaffold();
        $before = $this->stored($page);

        $result = decision::record($suggestionid, 'reject', null, 'Course is correct.');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['applied']);
        $this->assertSame($before, $this->stored($page));

        $row = $DB->get_record('local_cchecker_suggestions', ['id' => $suggestionid]);
        $this->assertSame('rejected', $row->decision);
        $this->assertSame(0, (int) $row->applied);
        $this->assertSame('Course is correct.', $row->notes);

        $this->assertTrue($DB->record_exists('local_cchecker_audit', [
            'objecttype' => 'suggestion', 'objectid' => $suggestionid, 'action' => 'rejected']));
    }

    /**
     * Edit-and-approve writes the reviewer's wording, not the model's.
     *
     * @return void
     */
    public function test_edit_then_approve_writes_reviewer_text(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [, $page, $suggestionid] = $this->scaffold();
        $edited = 'The human heart has four chambers: two atria and two ventricles.';

        $result = decision::record($suggestionid, 'edit', $edited);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['applied']);

        $stored = $this->stored($page);
        $this->assertStringContainsString('two atria and two ventricles', $stored);
        // The model's own wording was NOT what got published.
        $this->assertStringNotContainsString(self::FIXED . '</p>', $stored);

        $row = $DB->get_record('local_cchecker_suggestions', ['id' => $suggestionid]);
        $this->assertSame('edited', $row->decision);
        $this->assertSame($edited, $row->appliedtext);
    }

    /**
     * A suggestion whose original sentence cannot be located is refused, and
     * the refusal is recorded rather than silently swallowed.
     *
     * @return void
     */
    public function test_unlocatable_sentence_is_refused(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [, $page, $suggestionid] = $this->scaffold('A sentence that is not on the page at all.');
        $before = $this->stored($page);

        $result = decision::record($suggestionid, 'approve');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['applied']);
        $this->assertNotNull($result['reason']);
        // Crucially: the page was not touched.
        $this->assertSame($before, $this->stored($page));

        $row = $DB->get_record('local_cchecker_suggestions', ['id' => $suggestionid]);
        $this->assertSame('approved', $row->decision);
        $this->assertSame(0, (int) $row->applied);

        $audit = $DB->get_record('local_cchecker_audit',
            ['objecttype' => 'suggestion', 'objectid' => $suggestionid]);
        $detail = json_decode($audit->detail, true);
        $this->assertFalse($detail['applied']);
        $this->assertNotEmpty($detail['reason']);
    }

    /**
     * A user without the approve capability cannot decide, even with a valid
     * suggestion id.
     *
     * @return void
     */
    public function test_requires_approve_capability(): void {
        $this->resetAfterTest();

        [$course, , $suggestionid] = $this->scaffold();

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        decision::record($suggestionid, 'approve');
    }

    /**
     * The structured result matches the contract the UI consumes.
     *
     * @return void
     */
    public function test_result_shape_matches_contract(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [, , $suggestionid] = $this->scaffold();
        $checkid = $DB->get_field('local_cchecker_suggestions', 'checkid', ['id' => $suggestionid]);

        $DB->insert_record('local_cchecker_evidence', (object) [
            'suggestionid' => $suggestionid,
            'title' => 'Cardiac anatomy',
            'url' => 'https://example.org/heart',
            'tier' => 3,
            'score' => 0.81,
            'snippet' => 'The human heart has four chambers.',
        ]);

        $result = pipeline::result_for((int) $checkid);

        $this->assertSame('needs_review', $result['status']);
        $this->assertCount(1, $result['issues']);

        $issue = $result['issues'][0];
        foreach (['claim', 'current_text', 'suggested_text', 'source_reference',
                  'confidence', 'verdict', 'decision'] as $key) {
            $this->assertArrayHasKey($key, $issue, "issue is missing {$key}");
        }
        $this->assertSame(self::TARGET, $issue['current_text']);
        $this->assertSame(self::FIXED, $issue['suggested_text']);
        $this->assertSame('https://example.org/heart', $issue['source_reference']);

        $this->assertCount(1, $result['sources_checked']);
        $this->assertSame('Cardiac anatomy', $result['sources_checked'][0]['title']);
    }

    /**
     * A check with nothing flagged reports ok, which is what turns the week's
     * badge green.
     *
     * @return void
     */
    public function test_clean_check_reports_ok(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $checkid = $DB->insert_record('local_cchecker_checks', (object) [
            'courseid' => $course->id,
            'sectionnum' => 1,
            'cmid' => 0,
            'jobid' => 0,
            'runmode' => 'live',
            'status' => 'complete',
            'usermodified' => 2,
            'timequeued' => time(),
            'timefinished' => time(),
        ]);

        $result = pipeline::result_for($checkid);
        $this->assertSame('ok', $result['status']);
        $this->assertSame([], $result['issues']);
    }
}
