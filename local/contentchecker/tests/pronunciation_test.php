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

use local_contentchecker\local\pronunciation;

/**
 * Tests for the TTS pronunciation dictionary.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_contentchecker\local\pronunciation
 */
final class pronunciation_test extends \advanced_testcase {

    /**
     * Add a dictionary entry.
     *
     * @param string $term The term.
     * @param string $replacement What to say instead.
     * @param string $matchtype word|substring.
     * @return void
     */
    private function add(string $term, string $replacement, string $matchtype = 'word'): void {
        global $DB;

        $DB->insert_record('local_cchecker_pronounce', (object) [
            'term' => $term,
            'replacement' => $replacement,
            'matchtype' => $matchtype,
            'enabled' => 1,
            'usermodified' => 2,
            'timemodified' => time(),
        ]);
        pronunciation::invalidate();
    }

    /**
     * A whole-word entry is applied.
     *
     * @return void
     */
    public function test_applies_word_entry(): void {
        $this->resetAfterTest();
        $this->add('angina', 'an-JY-nuh');

        $this->assertSame('The patient reports an-JY-nuh today.',
            pronunciation::apply('The patient reports angina today.'));
    }

    /**
     * A whole-word entry does not corrupt a longer word containing it. This is
     * the failure mode that makes word matching the default.
     *
     * @return void
     */
    public function test_word_entry_does_not_match_inside_a_word(): void {
        $this->resetAfterTest();
        $this->add('ia', 'EYE-ay');

        $this->assertSame('The iatrogenic cause was noted.',
            pronunciation::apply('The iatrogenic cause was noted.'));
    }

    /**
     * A substring entry matches inside a word when that is what was asked for.
     *
     * @return void
     */
    public function test_substring_entry_matches_inside_a_word(): void {
        $this->resetAfterTest();
        $this->add('phragm', 'fram', 'substring');

        $this->assertSame('The diafram moves.',
            pronunciation::apply('The diaphragm moves.'));
    }

    /**
     * Longer terms are applied first, so a multi-word entry is not broken up
     * by a single-word one that matches part of it.
     *
     * @return void
     */
    public function test_longer_terms_win(): void {
        $this->resetAfterTest();
        $this->add('angina', 'an-JY-nuh');
        $this->add('angina pectoris', 'an-JY-nuh PEK-tor-iss');

        $this->assertSame('Diagnosed with an-JY-nuh PEK-tor-iss.',
            pronunciation::apply('Diagnosed with angina pectoris.'));
    }

    /**
     * Disabled entries are ignored.
     *
     * @return void
     */
    public function test_disabled_entries_are_ignored(): void {
        global $DB;

        $this->resetAfterTest();
        $this->add('angina', 'an-JY-nuh');
        $DB->set_field('local_cchecker_pronounce', 'enabled', 0, ['term' => 'angina']);
        pronunciation::invalidate();

        $this->assertSame('Reports angina.', pronunciation::apply('Reports angina.'));
    }

    /**
     * A replacement containing a dollar sign survives.
     *
     * @return void
     */
    public function test_dollar_in_replacement_survives(): void {
        $this->resetAfterTest();
        $this->add('cost', 'cost of $5');

        $this->assertStringContainsString('$5', pronunciation::apply('The cost is high.'));
    }
}
