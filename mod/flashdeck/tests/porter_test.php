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

use mod_flashdeck\local\porter;
use mod_flashdeck\local\seeder;

/**
 * Tests for bulk import/export in JSON, CSV and GIFT.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_flashdeck\local\porter
 */
final class porter_test extends \advanced_testcase {

    /**
     * Create a course, deck and user.
     *
     * @return array [deck, user]
     */
    protected function setup_deck(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $deck = $generator->create_module('flashdeck', ['course' => $course->id]);
        $user = $generator->create_user();
        return [$deck, $user];
    }

    /**
     * JSON export of the full sample deck round-trips losslessly.
     */
    public function test_json_round_trip(): void {
        global $DB;
        $this->resetAfterTest();
        [$deck, $user] = $this->setup_deck();

        // Seed the deck (16 cards including dissections), export, import elsewhere.
        $count = seeder::seed($deck, $user->id);
        $cards = $DB->get_records('flashdeck_cards', ['deckid' => $deck->id], 'position ASC');
        $json = porter::export_json($deck, $cards);

        [$deck2, $user2] = $this->setup_deck();
        $imported = porter::import_json($deck2, $json, $user2->id);
        $this->assertSame($count, $imported);

        $copies = array_values($DB->get_records('flashdeck_cards', ['deckid' => $deck2->id], 'position ASC'));
        $originals = array_values($cards);
        foreach ($originals as $i => $original) {
            $this->assertSame($original->cardtype, $copies[$i]->cardtype);
            $this->assertSame($original->tags, $copies[$i]->tags);
            $this->assertEquals(json_decode($original->content, true), json_decode($copies[$i]->content, true));
        }
    }

    /**
     * A bad card anywhere in a file means nothing at all is imported.
     */
    public function test_import_is_atomic(): void {
        global $DB;
        $this->resetAfterTest();
        [$deck, $user] = $this->setup_deck();

        $json = json_encode(['cards' => [
            ['cardtype' => 'basic', 'content' => ['front' => 'ok', 'back' => 'ok']],
            ['cardtype' => 'basic', 'content' => ['front' => 'missing back']],
        ]]);

        try {
            porter::import_json($deck, $json, $user->id);
            $this->fail('Expected import to abort');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('2', $e->getMessage());
        }
        $this->assertSame(0, $DB->count_records('flashdeck_cards', ['deckid' => $deck->id]));
    }

    /**
     * CSV covers the text-friendly types and round-trips their content.
     */
    public function test_csv_round_trip(): void {
        global $DB;
        $this->resetAfterTest();
        [$deck, $user] = $this->setup_deck();

        $defs = [
            ['cardtype' => 'basic', 'tags' => 'week1',
                'content' => ['front' => 'What is -itis?', 'frontformat' => FORMAT_HTML,
                    'back' => 'Inflammation', 'backformat' => FORMAT_HTML]],
            ['cardtype' => 'cloze', 'tags' => null,
                'content' => ['text' => 'ATP is made in the [[mitochondrion]].', 'casesensitive' => false]],
            ['cardtype' => 'termdissection', 'tags' => 'terms',
                'content' => ['term' => 'hepatitis', 'definition' => 'Inflammation of the liver.',
                    'parts' => [
                        ['text' => 'hepat', 'role' => 'root', 'meaning' => 'liver'],
                        ['text' => 'itis', 'role' => 'suffix', 'meaning' => 'inflammation'],
                    ]]],
            ['cardtype' => 'matching', 'tags' => null,
                'content' => ['prompt' => 'Match', 'pairs' => [
                    ['left' => 'brady-', 'right' => 'slow'], ['left' => 'tachy-', 'right' => 'fast']]]],
            ['cardtype' => 'ordering', 'tags' => null,
                'content' => ['prompt' => 'Order mitosis', 'items' => ['Prophase', 'Metaphase', 'Anaphase']]],
            ['cardtype' => 'comparecontrast', 'tags' => null,
                'content' => ['prompt' => 'Compare', 'columna' => 'Beveridge', 'columnb' => 'Bismarck',
                    'rows' => [['aspect' => 'Funding', 'a' => 'Taxes', 'b' => 'Insurance']]]],
        ];
        porter::import_cards($deck, $defs, $user->id);
        $cards = $DB->get_records('flashdeck_cards', ['deckid' => $deck->id], 'position ASC');
        $csv = porter::export_csv($deck, $cards);

        [$deck2, $user2] = $this->setup_deck();
        $this->assertSame(count($defs), porter::import_csv($deck2, $csv, $user2->id));

        $copies = array_values($DB->get_records('flashdeck_cards', ['deckid' => $deck2->id], 'position ASC'));
        foreach ($defs as $i => $def) {
            $this->assertSame($def['cardtype'], $copies[$i]->cardtype);
            $imported = json_decode($copies[$i]->content, true);
            foreach ($def['content'] as $key => $value) {
                $this->assertEquals($value, $imported[$key], "{$def['cardtype']}.{$key}");
            }
        }
    }

    /**
     * CSV export skips types without a lossless plain-text form.
     */
    public function test_csv_export_skips_imagelabel(): void {
        $this->resetAfterTest();
        [$deck] = $this->setup_deck();

        $cards = [(object) ['id' => 1, 'cardtype' => 'imagelabel', 'tags' => null,
            'content' => json_encode(['variant' => 'identify', 'label' => 'Deltoid',
                'alttext' => 'x', 'region' => ['cx' => 50, 'cy' => 50, 'r' => 8]])]];

        $lines = array_filter(explode("\n", trim(porter::export_csv($deck, $cards))));
        // Header only: the imagelabel row is not representable in CSV.
        $this->assertCount(1, $lines);
    }

    /**
     * GIFT parsing: short answer, multichoice, true/false, missing word.
     */
    public function test_gift_parse(): void {
        $this->resetAfterTest();

        $gift = <<<'GIFT'
// Week 1 questions.
::Itis::What does the suffix -itis mean? {=inflammation =swelling #good}

What does brady- mean? {~fast =slow ~irregular}

Grant was buried in a tomb in New York City. {FALSE}

The suffix {=‑logy =logy} means the study of a field.

Essay to skip {}
GIFT;

        $defs = porter::parse_gift($gift);
        $this->assertCount(4, $defs);

        // Short answer -> basic with alternatives on the back.
        $this->assertSame('basic', $defs[0]['cardtype']);
        $this->assertSame('What does the suffix -itis mean?', $defs[0]['content']['front']);
        $this->assertSame('inflammation / swelling', $defs[0]['content']['back']);

        // Multichoice -> basic keeping only the correct answer.
        $this->assertSame('slow', $defs[1]['content']['back']);

        // True/false.
        $this->assertSame('basic', $defs[2]['cardtype']);

        // Missing word -> cloze with the blank in place.
        $this->assertSame('cloze', $defs[3]['cardtype']);
        $this->assertSame('The suffix [[‑logy|logy]] means the study of a field.',
            $defs[3]['content']['text']);
    }

    /**
     * GIFT import lands validated cards in the deck.
     */
    public function test_gift_import(): void {
        global $DB;
        $this->resetAfterTest();
        [$deck, $user] = $this->setup_deck();

        $count = porter::import_gift($deck,
            "What does -itis mean? {=inflammation}\n\nThe cell's engine is the {=mitochondrion} organelle.",
            $user->id);
        $this->assertSame(2, $count);
        $types = $DB->get_fieldset_sql(
            'SELECT cardtype FROM {flashdeck_cards} WHERE deckid = ? ORDER BY position', [$deck->id]);
        $this->assertSame(['basic', 'cloze'], $types);
    }
}
