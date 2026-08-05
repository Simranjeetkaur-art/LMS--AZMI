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

use local_contentchecker\image\image_inserter;
use local_contentchecker\image\image_result;
use local_contentchecker\image\source_registry;
use local_contentchecker\local\content_source;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/licenselib.php');

/**
 * Tests for image search, insertion and attribution handling.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_contentchecker\image\image_result
 * @covers     \local_contentchecker\image\source_registry
 * @covers     \local_contentchecker\image\image_inserter
 * @covers     \local_contentchecker\local\content_source
 */
final class image_test extends \advanced_testcase {

    /**
     * Call a protected method on image_inserter.
     *
     * @param string $name Method name.
     * @param array $args Arguments.
     * @return mixed The return value.
     */
    private function call_inserter(string $name, array $args) {
        $method = new \ReflectionMethod(image_inserter::class, $name);
        return $method->invokeArgs(null, $args);
    }

    /**
     * A supplied attribution line is used verbatim.
     *
     * @return void
     */
    public function test_uses_supplied_attribution(): void {
        $result = new image_result(
            sourceid: 'openverse',
            ref: 'https://example.org/a.jpg',
            title: 'Heart',
            thumburl: '',
            fullurl: 'https://example.org/a.jpg',
            attribution: '"Heart" by Someone is licensed under CC BY 2.0.',
        );

        $this->assertSame('"Heart" by Someone is licensed under CC BY 2.0.',
            $result->attribution_line());
    }

    /**
     * With no ready-made attribution, one is assembled from the parts.
     *
     * @return void
     */
    public function test_builds_attribution_from_parts(): void {
        $this->resetAfterTest();

        $result = new image_result(
            sourceid: 'repository',
            ref: 'abc',
            title: 'Thorax',
            thumburl: '',
            fullurl: '',
            author: 'A Teacher',
            license: 'cc-by',
        );

        $line = $result->attribution_line();
        $this->assertStringContainsString('Thorax', $line);
        $this->assertStringContainsString('A Teacher', $line);
        $this->assertStringContainsString('CC BY', $line);
    }

    /**
     * An image with nothing known about it produces no attribution rather than
     * a misleading empty-quotes string.
     *
     * @return void
     */
    public function test_empty_attribution_when_nothing_known(): void {
        $result = new image_result(
            sourceid: 'repository', ref: 'x', title: '', thumburl: '', fullurl: '');

        $this->assertSame('', $result->attribution_line());
    }

    /**
     * A disabled source cannot be resolved by naming it.
     *
     * @return void
     */
    public function test_registry_refuses_disabled_source(): void {
        $this->resetAfterTest();

        set_config('image_openverse_enabled', 0, 'local_contentchecker');
        set_config('image_repository_enabled', 0, 'local_contentchecker');

        $this->assertSame([], source_registry::available());

        $this->expectException(\moodle_exception::class);
        source_registry::get('openverse');
    }

    /**
     * An enabled source is resolvable and appears in the picker menu.
     *
     * @return void
     */
    public function test_registry_exposes_enabled_source(): void {
        $this->resetAfterTest();

        set_config('image_openverse_enabled', 1, 'local_contentchecker');
        set_config('image_openverse_endpoint',
            'https://api.openverse.org/v1', 'local_contentchecker');

        $this->assertArrayHasKey('openverse', source_registry::available());
        $this->assertContains('openverse', array_column(source_registry::menu(), 'id'));
    }

    /**
     * A setting that has never been saved falls back to its documented default
     * rather than reading as "switched off".
     *
     * @return void
     */
    public function test_unset_setting_uses_documented_default(): void {
        $this->resetAfterTest();

        unset_config('image_openverse_enabled', 'local_contentchecker');
        unset_config('image_repository_enabled', 'local_contentchecker');

        $this->assertTrue(source_registry::setting_enabled('image_openverse_enabled'));
        $this->assertTrue(source_registry::setting_enabled('image_repository_enabled'));
        $this->assertFalse(source_registry::setting_enabled('never_set_at_all', false));

        // And an explicit off still means off.
        set_config('image_openverse_enabled', 0, 'local_contentchecker');
        $this->assertFalse(source_registry::setting_enabled('image_openverse_enabled'));
    }

    /**
     * Licence codes map onto Moodle licence shortnames, and an unrecognised one
     * becomes "unknown" rather than being guessed at.
     *
     * @return void
     */
    public function test_license_mapping(): void {
        $this->resetAfterTest();

        $known = array_keys(\license_manager::get_licenses());

        // Whatever it maps to must be a licence Moodle actually knows.
        $this->assertContains($this->call_inserter('map_license', ['by-4.0']), $known);
        $this->assertContains($this->call_inserter('map_license', ['cc0-1.0']), $known);

        $this->assertSame('unknown', $this->call_inserter('map_license', ['']));
        $this->assertSame('unknown',
            $this->call_inserter('map_license', ['not-a-real-licence']));
    }

    /**
     * The rendered figure uses @@PLUGINFILE@@ so Moodle rewrites it into a
     * correctly permissioned URL, and escapes the attribution.
     *
     * @return void
     */
    public function test_render_uses_pluginfile_and_escapes(): void {
        $this->resetAfterTest();

        $html = $this->call_inserter('render', ['heart.jpg', [
            'title' => 'Heart <script>alert(1)</script>',
            'attribution' => 'By "Someone" & Co',
            'licenseurl' => 'https://creativecommons.org/licenses/by/4.0/',
        ], 42]);

        $this->assertStringContainsString('@@PLUGINFILE@@/heart.jpg', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&amp;', $html);
        $this->assertStringContainsString('creativecommons.org', $html);
    }

    /**
     * A page's images belong to the module context under mod_page/content, so
     * @@PLUGINFILE@@ resolves against the area the renderer rewrites.
     *
     * @return void
     */
    public function test_file_area_for_page(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>' . str_repeat('Clinical content here. ', 30) . '</p>',
            'contentformat' => FORMAT_HTML,
        ]);

        $items = content_source::for_cm((int) $page->cmid);
        $this->assertNotEmpty($items);

        $area = content_source::file_area($items[0]);
        $this->assertSame('mod_page', $area['component']);
        $this->assertSame('content', $area['filearea']);
        $this->assertSame(0, $area['itemid']);
        $this->assertSame(\context_module::instance($page->cmid)->id, $area['contextid']);
    }

    /**
     * An activity whose prose lives in its intro uses the intro file area.
     *
     * @return void
     */
    public function test_file_area_for_intro_based_module(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'intro' => '<p>' . str_repeat('Assignment briefing text. ', 30) . '</p>',
            'introformat' => FORMAT_HTML,
        ]);

        $items = content_source::for_cm((int) $assign->cmid);
        $this->assertNotEmpty($items);

        $area = content_source::file_area($items[0]);
        $this->assertSame('mod_assign', $area['component']);
        $this->assertSame('intro', $area['filearea']);
    }

    /**
     * Inserting an image really does store the file, write the licence onto it,
     * and reference it from the activity's content.
     *
     * @return void
     */
    public function test_insert_stores_file_with_licence_and_references_it(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>' . str_repeat('Original clinical prose. ', 30) . '</p>',
            'contentformat' => FORMAT_HTML,
        ]);

        $items = content_source::for_cm((int) $page->cmid);
        $area = content_source::file_area($items[0]);

        // Stand in for a fetched image: a real 1x1 PNG.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $fs = get_file_storage();
        $file = $fs->create_file_from_string([
            'contextid' => $area['contextid'],
            'component' => $area['component'],
            'filearea' => $area['filearea'],
            'itemid' => $area['itemid'],
            'filepath' => '/',
            'filename' => 'heart.png',
            'author' => 'A Photographer',
            'license' => 'cc-4.0',
        ], $png);

        $this->assertSame('A Photographer', $file->get_author());
        $this->assertSame('cc-4.0', $file->get_license());

        // Now append a reference to it the way the inserter does.
        $html = $this->call_inserter('render', ['heart.png', [
            'title' => 'Heart',
            'attribution' => '"Heart" by A Photographer, CC BY 4.0',
        ], $file->get_id()]);

        $this->assertTrue(
            \local_contentchecker\local\enrichment::insert_into_cm((int) $page->cmid, $html));

        $stored = $DB->get_field('page', 'content', ['id' => $page->id]);
        $this->assertStringContainsString('@@PLUGINFILE@@/heart.png', $stored);
        $this->assertStringContainsString('A Photographer', $stored);
        // The original prose must survive the append untouched.
        $this->assertStringContainsString('Original clinical prose.', $stored);
    }

    /**
     * A filename collision does not overwrite the image already there.
     *
     * @return void
     */
    public function test_unique_filename_avoids_collision(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>' . str_repeat('Prose. ', 60) . '</p>',
            'contentformat' => FORMAT_HTML,
        ]);

        $items = content_source::for_cm((int) $page->cmid);
        $area = content_source::file_area($items[0]);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $area['contextid'],
            'component' => $area['component'],
            'filearea' => $area['filearea'],
            'itemid' => $area['itemid'],
            'filepath' => '/',
            'filename' => 'heart.png',
        ], 'not-really-an-image');

        $name = $this->call_inserter('unique_filename', [$fs, $area, 'heart.png']);
        $this->assertNotSame('heart.png', $name);
        $this->assertSame('heart-2.png', $name);
    }

    /**
     * The stored filename comes from the image title, not from an opaque
     * remote URL, and always keeps a real extension.
     *
     * @return void
     */
    public function test_filename_from_title(): void {
        $this->resetAfterTest();

        $this->assertSame('cardiac-anatomy-drawing.webp',
            $this->call_inserter('name_from_title',
                ['Cardiac anatomy drawing', 'cHJpdmF0ZS9sci9pbWFnZXM.webp']));

        // Punctuation and case collapse into a clean slug.
        $this->assertSame('heart-the-left-ventricle.png',
            $this->call_inserter('name_from_title',
                ['Heart: the LEFT ventricle!', 'x.png']));

        // No usable title: keep whatever the source gave us.
        $this->assertSame('original.jpg',
            $this->call_inserter('name_from_title', ['', 'original.jpg']));
        $this->assertSame('original.jpg',
            $this->call_inserter('name_from_title', ['!!', 'original.jpg']));
    }

    /**
     * Images cannot be pushed into quiz question text, where the file area is
     * not the module's and a wrong guess would produce a broken image.
     *
     * @return void
     */
    public function test_refuses_unsupported_target(): void {
        $this->resetAfterTest();

        $item = (object) [
            'cmid' => 0,
            'modname' => 'quiz',
            'table' => 'question',
            'field' => 'questiontext',
            'recordid' => 1,
        ];

        $this->expectException(\moodle_exception::class);
        content_source::file_area($item);
    }
}
