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

namespace mod_flashdeck;

use mod_flashdeck\cardtype\card_type;
use mod_flashdeck\cardtype\manager;

/**
 * Tests for the card-type registry and the shipped card types.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_flashdeck\cardtype\manager
 * @covers     \mod_flashdeck\cardtype\basic
 * @covers     \mod_flashdeck\cardtype\termdissection
 */
final class cardtype_test extends \advanced_testcase {

    /**
     * The registry exposes the shipped types and rejects unknown ones.
     */
    public function test_registry(): void {
        $types = manager::get_types();

        $this->assertArrayHasKey('basic', $types);
        $this->assertArrayHasKey('termdissection', $types);
        foreach ($types as $identifier => $type) {
            $this->assertInstanceOf(card_type::class, $type);
            $this->assertSame($identifier, $type->get_identifier());
        }

        $this->assertTrue(manager::exists('basic'));
        $this->assertFalse(manager::exists('hologram'));

        $this->expectException(\moodle_exception::class);
        manager::get('hologram');
    }

    /**
     * Basic cards require non-empty text on both faces.
     */
    public function test_basic_validate_content(): void {
        $basic = manager::get('basic');

        $valid = ['front' => '<p>Prompt</p>', 'frontformat' => FORMAT_HTML,
            'back' => '<p>Answer</p>', 'backformat' => FORMAT_HTML];
        $this->assertSame([], $basic->validate_content($valid));

        $this->assertNotEmpty($basic->validate_content(['front' => '<p>Prompt</p>']));
        $this->assertNotEmpty($basic->validate_content(['front' => '  ', 'back' => 'x']));
        $this->assertNotEmpty($basic->validate_content([]));
    }

    /**
     * Term dissection cards require a term, a definition and 2+ valid parts.
     */
    public function test_termdissection_validate_content(): void {
        $type = manager::get('termdissection');

        $valid = [
            'term' => 'cardiology',
            'definition' => 'Study of the heart.',
            'parts' => [
                ['text' => 'cardi', 'role' => 'root', 'meaning' => 'heart'],
                ['text' => 'o', 'role' => 'link', 'meaning' => 'combining vowel'],
                ['text' => 'logy', 'role' => 'suffix', 'meaning' => 'study of'],
            ],
        ];
        $this->assertSame([], $type->validate_content($valid));

        $missingterm = $valid;
        unset($missingterm['term']);
        $this->assertNotEmpty($type->validate_content($missingterm));

        $onepart = $valid;
        $onepart['parts'] = [['text' => 'cardi', 'role' => 'root', 'meaning' => 'heart']];
        $this->assertNotEmpty($type->validate_content($onepart));

        $badrole = $valid;
        $badrole['parts'][0]['role'] = 'infix';
        $this->assertNotEmpty($type->validate_content($badrole));

        $emptypart = $valid;
        $emptypart['parts'][0]['text'] = '';
        $this->assertNotEmpty($type->validate_content($emptypart));
    }

    /**
     * Rendering exports escape-ready data for the templates.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $deck = $generator->create_module('flashdeck', ['course' => $course->id]);
        $context = \context_module::instance($deck->cmid);

        /** @var \mod_flashdeck_generator $plugingenerator */
        $plugingenerator = $generator->get_plugin_generator('mod_flashdeck');

        $basiccard = $plugingenerator->create_card([
            'deckid' => $deck->id,
            'front' => '<p>What does -itis mean?</p>',
            'back' => '<p>Inflammation</p>',
        ]);
        $export = manager::get('basic')->export_for_template($basiccard, $context);
        $this->assertStringContainsString('What does -itis mean?', $export['fronthtml']);
        $this->assertStringContainsString('Inflammation', $export['backhtml']);

        $dissectioncard = $plugingenerator->create_card([
            'deckid' => $deck->id,
            'cardtype' => 'termdissection',
            'content' => [
                'term' => 'hepatitis',
                'definition' => 'Inflammation of the liver.',
                'parts' => [
                    ['text' => 'hepat', 'role' => 'root', 'meaning' => 'liver'],
                    ['text' => 'itis', 'role' => 'suffix', 'meaning' => 'inflammation'],
                ],
            ],
        ]);
        $export = manager::get('termdissection')->export_for_template($dissectioncard, $context);
        $this->assertSame('hepatitis', $export['term']);
        $this->assertCount(2, $export['parts']);
        $this->assertSame('root', $export['parts'][0]['role']);
        $this->assertNotEmpty($export['parts'][0]['rolename']);

        // Summaries are plain text for teacher lists.
        $this->assertSame('What does -itis mean?', manager::get('basic')->get_summary($basiccard));
        $this->assertSame('hepatitis', manager::get('termdissection')->get_summary($dissectioncard));
    }
}
