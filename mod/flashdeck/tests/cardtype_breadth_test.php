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

use mod_flashdeck\cardtype\cloze;
use mod_flashdeck\cardtype\manager;

/**
 * Tests for the Phase 3 card types.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_flashdeck\cardtype\imagelabel
 * @covers     \mod_flashdeck\cardtype\cloze
 * @covers     \mod_flashdeck\cardtype\matching
 * @covers     \mod_flashdeck\cardtype\ordering
 * @covers     \mod_flashdeck\cardtype\comparecontrast
 * @covers     \mod_flashdeck\cardtype\qanda
 */
final class cardtype_breadth_test extends \advanced_testcase {

    /**
     * Build an unsaved card record for a type and content payload.
     *
     * @param string $cardtype the type identifier
     * @param array $content the content payload
     * @return \stdClass
     */
    protected function card(string $cardtype, array $content): \stdClass {
        return (object) ['id' => 1, 'cardtype' => $cardtype, 'content' => json_encode($content)];
    }

    /**
     * All eight card types are registered.
     */
    public function test_registry_breadth(): void {
        $this->assertSame(
            ['basic', 'termdissection', 'imagelabel', 'cloze', 'matching', 'ordering', 'comparecontrast', 'qanda'],
            array_keys(manager::get_types()));
    }

    /**
     * Cloze parsing splits text and blanks with alternatives.
     */
    public function test_cloze_parse(): void {
        $segments = cloze::parse('The [[mitochondrion|mitochondria]] makes [[ATP]].');

        $this->assertCount(5, $segments);
        $this->assertFalse($segments[0]['blank']);
        $this->assertTrue($segments[1]['blank']);
        $this->assertSame(['mitochondrion', 'mitochondria'], $segments[1]['answers']);
        $this->assertTrue($segments[3]['blank']);
        $this->assertSame(['ATP'], $segments[3]['answers']);
    }

    /**
     * Answer checking never punishes trivial variation.
     */
    public function test_cloze_matching_normalisation(): void {
        $accepted = ['Mitochondrion', 'mitochondria'];

        $this->assertTrue(cloze::matches('mitochondrion', $accepted, false));
        $this->assertTrue(cloze::matches('  MITOCHONDRIA  ', $accepted, false));
        $this->assertTrue(cloze::matches("mitochondrion\n", $accepted, false));
        $this->assertFalse(cloze::matches('chloroplast', $accepted, false));

        // Case-sensitive mode still forgives whitespace, not case.
        $this->assertTrue(cloze::matches(' Mitochondrion ', $accepted, true));
        $this->assertFalse(cloze::matches('mitochondrion', $accepted, true));

        // Internal whitespace is collapsed both sides.
        $this->assertTrue(cloze::matches('smooth  muscle', ['smooth muscle'], false));
    }

    /**
     * Cloze content must contain at least one usable blank.
     */
    public function test_cloze_validate_and_export(): void {
        $type = manager::get('cloze');

        $this->assertSame([], $type->validate_content(['text' => 'A [[blank]] here.']));
        $this->assertNotEmpty($type->validate_content(['text' => 'No blanks at all.']));
        $this->assertNotEmpty($type->validate_content(['text' => 'Empty [[ ]] blank.']));

        $card = $this->card('cloze', ['text' => 'A [[blank|hole]] here.', 'casesensitive' => false]);
        $export = $type->export_for_template($card, \context_system::instance());
        $blanks = array_values(array_filter($export['frontsegments'], static fn($s) => $s['isblank']));
        $this->assertCount(1, $blanks);
        $this->assertSame('["blank","hole"]', $blanks[0]['answersjson']);
        $backblanks = array_values(array_filter($export['backsegments'], static fn($s) => $s['isblank']));
        $this->assertSame('blank', $backblanks[0]['text']);
        $this->assertSame('hole', $backblanks[0]['alternatives']);

        $this->assertSame('A … here.', $type->get_summary($card));
    }

    /**
     * Matching: options are alphabetical and answers map to the pair index.
     */
    public function test_matching(): void {
        $type = manager::get('matching');
        $content = [
            'prompt' => 'Match prefixes',
            'pairs' => [
                ['left' => 'tachy-', 'right' => 'fast'],
                ['left' => 'brady-', 'right' => 'slow'],
            ],
        ];

        $this->assertSame([], $type->validate_content($content));
        $this->assertNotEmpty($type->validate_content(['prompt' => 'x', 'pairs' => [['left' => 'a', 'right' => 'b']]]));
        $this->assertNotEmpty($type->validate_content(['prompt' => 'x',
            'pairs' => [['left' => 'a', 'right' => ''], ['left' => 'b', 'right' => 'c']]]));

        $export = $type->export_for_template($this->card('matching', $content), \context_system::instance());
        // Options alphabetical: fast (pair 0), slow (pair 1).
        $this->assertSame(['fast', 'slow'], array_column($export['rows'][0]['options'], 'label'));
        // Each row's answer is its own pair index.
        $this->assertSame(0, $export['rows'][0]['answer']);
        $this->assertSame('tachy-', $export['rows'][0]['left']);
        $this->assertSame(1, $export['rows'][1]['answer']);
    }

    /**
     * Ordering: alphabetical presentation with correct position answers.
     */
    public function test_ordering(): void {
        $type = manager::get('ordering');
        $content = [
            'prompt' => 'Order the stages of mitosis',
            'items' => ['Prophase', 'Metaphase', 'Anaphase', 'Telophase'],
        ];

        $this->assertSame([], $type->validate_content($content));
        $this->assertNotEmpty($type->validate_content(['prompt' => 'x', 'items' => ['One']]));

        $export = $type->export_for_template($this->card('ordering', $content), \context_system::instance());
        $this->assertSame(['Anaphase', 'Metaphase', 'Prophase', 'Telophase'],
            array_column($export['scrambled'], 'text'));
        $this->assertSame([3, 2, 1, 4], array_column($export['scrambled'], 'answer'));
        $this->assertSame($content['items'], $export['ordered']);
        $this->assertCount(4, $export['scrambled'][0]['positions']);
    }

    /**
     * Compare/contrast requires prompt, columns and one row.
     */
    public function test_comparecontrast(): void {
        $type = manager::get('comparecontrast');
        $content = [
            'prompt' => 'Compare the models',
            'columna' => 'Beveridge',
            'columnb' => 'Bismarck',
            'rows' => [['aspect' => 'Funding', 'a' => 'Taxation', 'b' => 'Social insurance']],
        ];

        $this->assertSame([], $type->validate_content($content));
        $this->assertNotEmpty($type->validate_content(['prompt' => 'x', 'columna' => 'A', 'columnb' => 'B', 'rows' => []]));
        $this->assertNotEmpty($type->validate_content(['prompt' => '', 'columna' => 'A', 'columnb' => 'B',
            'rows' => $content['rows']]));

        $export = $type->export_for_template($this->card('comparecontrast', $content), \context_system::instance());
        $this->assertSame('Beveridge', $export['columna']);
        $this->assertSame('Funding', $export['rows'][0]['aspect']);
    }

    /**
     * Image/label validation covers label, alt text and region ranges.
     */
    public function test_imagelabel_validation_and_export(): void {
        $type = manager::get('imagelabel');
        $content = [
            'variant' => 'hotspot',
            'label' => 'Deltoid',
            'alttext' => 'Shoulder muscles, lateral view',
            'question' => '',
            'description' => 'Abducts the arm.',
            'region' => ['cx' => 42.5, 'cy' => 31.0, 'r' => 8.0],
        ];

        $this->assertSame([], $type->validate_content($content));

        $noalt = $content;
        $noalt['alttext'] = ' ';
        $this->assertNotEmpty($type->validate_content($noalt));

        $badregion = $content;
        $badregion['region']['cx'] = 130;
        $this->assertNotEmpty($type->validate_content($badregion));
        $badregion = $content;
        $badregion['region']['r'] = 0.2;
        $this->assertNotEmpty($type->validate_content($badregion));

        // Export in a non-module context: no file lookup, graceful no-image state.
        $export = $type->export_for_template($this->card('imagelabel', $content), \context_system::instance());
        $this->assertTrue($export['ishotspot']);
        $this->assertFalse($export['hasimage']);
        $this->assertSame(16.0, $export['d']);
        // The default hotspot prompt names the structure to find.
        $this->assertStringContainsString('Deltoid', $export['question']);
        $this->assertSame('Deltoid', $type->get_summary($this->card('imagelabel', $content)));
    }

    /**
     * Q&A inherits basic validation and adds guidance.
     */
    public function test_qanda(): void {
        $type = manager::get('qanda');
        $content = [
            'front' => '<p>Why spaced repetition?</p>', 'frontformat' => FORMAT_HTML,
            'back' => '<p>The spacing effect.</p>', 'backformat' => FORMAT_HTML,
            'guidance' => 'Mention retrieval practice.',
        ];

        $this->assertSame([], $type->validate_content($content));
        $this->assertNotEmpty($type->validate_content(['front' => 'x']));

        $export = $type->export_for_template($this->card('qanda', $content), \context_system::instance());
        $this->assertStringContainsString('spacing effect', $export['backhtml']);
        $this->assertSame('Mention retrieval practice.', $export['guidance']);
    }
}
