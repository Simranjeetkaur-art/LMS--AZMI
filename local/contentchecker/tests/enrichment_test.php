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

use local_contentchecker\form\enrich_form;
use local_contentchecker\local\content_source;
use local_contentchecker\local\enrichment;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/local/contentchecker/classes/form/enrich_form.php');

/**
 * End-to-end tests for the enrichment insertion flows.
 *
 * These exist because "the DB table is there" is not evidence that an editor
 * can actually create a comparison table and have it survive. Each test drives
 * the real path -- parse, render, write, re-read from the database -- and
 * checks the surrounding content came through untouched.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_contentchecker\local\enrichment
 * @covers     \local_contentchecker\form\enrich_form
 */
final class enrichment_test extends \advanced_testcase {

    /** @var string Marker text that must survive every insertion. */
    const ORIGINAL = 'The original clinical prose must survive intact.';

    /**
     * Create a page with known content.
     *
     * @return array [course, page]
     */
    private function make_page(): array {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>' . self::ORIGINAL . '</p><p>'
                . str_repeat('Supporting detail. ', 25) . '</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        return [$course, $page];
    }

    /**
     * Re-read an activity's stored content from the database.
     *
     * @param \stdClass $page The page.
     * @return string Stored HTML.
     */
    private function stored(\stdClass $page): string {
        global $DB;
        return (string) $DB->get_field('page', 'content', ['id' => $page->id]);
    }

    /**
     * Pasted CSV becomes rows of cells.
     *
     * @return void
     */
    public function test_csv_parses_into_rows(): void {
        $rows = enrich_form::parse_csv("Feature,Drug A,Drug B\nOnset,Rapid,Slow\nRoute,Oral,IV");

        $this->assertCount(3, $rows);
        $this->assertSame(['Feature', 'Drug A', 'Drug B'], $rows[0]);
        $this->assertSame(['Route', 'Oral', 'IV'], $rows[2]);
    }

    /**
     * Quoted cells containing commas survive parsing.
     *
     * @return void
     */
    public function test_csv_handles_quoted_commas(): void {
        $rows = enrich_form::parse_csv("Drug,Notes\nAtenolol,\"Avoid in asthma, use with care\"");

        $this->assertCount(2, $rows);
        $this->assertSame('Avoid in asthma, use with care', $rows[1][1]);
    }

    /**
     * The full comparison-table flow: CSV in, real table in the activity,
     * original prose untouched.
     *
     * @return void
     */
    public function test_comparison_table_end_to_end(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $page] = $this->make_page();

        $rows = enrich_form::parse_csv(
            "Feature,Beta blocker,ACE inhibitor\nOnset,Rapid,Gradual\nCough,No,Yes");
        $headers = array_shift($rows);

        $html = enrichment::render_table('Drug comparison', $headers, $rows);
        $this->assertTrue(enrichment::insert_into_cm((int) $page->cmid, $html));

        $stored = $this->stored($page);

        // A real table, not a pre-formatted blob.
        $this->assertStringContainsString('<table', $stored);
        $this->assertStringContainsString('Drug comparison', $stored);
        $this->assertStringContainsString('Beta blocker', $stored);
        $this->assertStringContainsString('Gradual', $stored);
        // Accessible: header row plus row-scoped first column.
        $this->assertStringContainsString('scope="row"', $stored);
        $this->assertStringContainsString('<caption', $stored);
        // And the page it was added to still says what it said before.
        $this->assertStringContainsString(self::ORIGINAL, $stored);
    }

    /**
     * Cell content is escaped, so a table cannot inject markup into a page.
     *
     * @return void
     */
    public function test_table_escapes_cell_content(): void {
        $html = enrichment::render_table('T', ['A', 'B'],
            [['<script>alert(1)</script>', 'ok']]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * A 3D model asset renders as an embeddable viewer with a fallback.
     *
     * @return void
     */
    public function test_model3d_asset_renders_and_inserts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $page] = $this->make_page();

        $asset = (object) [
            'name' => 'Heart model',
            'assettype' => 'model3d',
            'viewer' => 'modelviewer',
            'url' => 'https://example.org/heart.glb',
            'posterurl' => 'https://example.org/heart.png',
            'body' => '',
            'description' => 'A human heart',
            'licence' => 'CC BY 4.0',
            'attribution' => 'BodyParts3D',
        ];

        $html = enrichment::render_asset($asset);

        $this->assertStringContainsString('<model-viewer', $html);
        $this->assertStringContainsString('heart.glb', $html);
        // The poster is inside the element, so a browser without the custom
        // element still shows something.
        $this->assertStringContainsString('heart.png', $html);
        $this->assertStringContainsString('BodyParts3D', $html);
        $this->assertStringContainsString('CC BY 4.0', $html);

        $this->assertTrue(enrichment::insert_into_cm((int) $page->cmid, $html));
        $stored = $this->stored($page);
        $this->assertStringContainsString('model-viewer', $stored);
        $this->assertStringContainsString(self::ORIGINAL, $stored);
    }

    /**
     * A diagram renders as readable source that degrades without the library.
     *
     * @return void
     */
    public function test_diagram_asset_renders(): void {
        $asset = (object) [
            'name' => 'Cardiac cycle',
            'assettype' => 'diagram',
            'viewer' => 'mermaid',
            'url' => '',
            'posterurl' => '',
            'body' => "graph TD;\n  A[Systole]-->B[Diastole];",
            'description' => '',
            'licence' => '',
            'attribution' => '',
        ];

        $html = enrichment::render_asset($asset);

        $this->assertStringContainsString('data-cct-mermaid', $html);
        $this->assertStringContainsString('Systole', $html);
        // A div, not a pre: Mermaid swaps the host element's innerHTML for an
        // SVG, and a pre is styled for preformatted text so the diagram never
        // lays out. CSS keeps the source readable until the renderer runs.
        $this->assertStringContainsString('<div class="cct-mermaid"', $html);
        $this->assertStringNotContainsString('<pre', $html);
    }

    /**
     * An iframe embed is sandboxed, so a third-party viewer gets no ambient
     * authority over the Moodle page around it.
     *
     * @return void
     */
    public function test_iframe_asset_is_sandboxed(): void {
        $asset = (object) [
            'name' => 'External viewer',
            'assettype' => 'embed',
            'viewer' => 'iframe',
            'url' => 'https://example.org/viewer',
            'posterurl' => '', 'body' => '', 'description' => '',
            'licence' => '', 'attribution' => '',
        ];

        $html = enrichment::render_asset($asset);
        $this->assertStringContainsString('sandbox=', $html);
    }

    /**
     * Several insertions in a row all survive, in order, with the original
     * content still intact. This is the round-trip the audit asks for.
     *
     * @return void
     */
    public function test_multiple_insertions_round_trip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $page] = $this->make_page();
        $before = $this->stored($page);

        $table = enrichment::render_table('Comparison', ['A', 'B'], [['one', 'two']]);
        enrichment::insert_into_cm((int) $page->cmid, $table);

        $asset = (object) [
            'name' => 'Model', 'assettype' => 'model3d', 'viewer' => 'modelviewer',
            'url' => 'https://example.org/m.glb', 'posterurl' => '', 'body' => '',
            'description' => 'Model', 'licence' => '', 'attribution' => '',
        ];
        enrichment::insert_into_cm((int) $page->cmid, enrichment::render_asset($asset));

        $after = $this->stored($page);

        // Everything that was there before is still there, verbatim.
        $this->assertStringContainsString($before, $after);
        $this->assertStringContainsString(self::ORIGINAL, $after);
        $this->assertStringContainsString('Comparison', $after);
        $this->assertStringContainsString('m.glb', $after);

        // Order is preserved: the table went in before the model.
        $this->assertLessThan(strpos($after, 'm.glb'), strpos($after, 'Comparison'));

        // The content is still readable as prose, i.e. not double-escaped into
        // visible tag soup.
        $text = content_source::to_text($after);
        $this->assertStringContainsString('The original clinical prose', $text);
        $this->assertStringNotContainsString('&lt;table', $after);
    }

    /**
     * Inserting into an activity that does not exist fails cleanly rather than
     * writing somewhere unexpected.
     *
     * @return void
     */
    public function test_insert_into_missing_activity_fails_safely(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertFalse(enrichment::insert_into_cm(0, '<p>x</p>'));
    }

    /**
     * Inserting is recorded in the audit trail.
     *
     * @return void
     */
    public function test_insertion_is_audited(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $page] = $this->make_page();
        enrichment::insert_into_cm((int) $page->cmid,
            enrichment::render_table('T', ['A'], [['1']]));

        $this->assertTrue($DB->record_exists('local_cchecker_audit', [
            'courseid' => $course->id,
            'cmid' => $page->cmid,
            'action' => 'inserted',
        ]));
    }
}
