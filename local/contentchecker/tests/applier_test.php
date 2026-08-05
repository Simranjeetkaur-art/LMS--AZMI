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

use local_contentchecker\local\applier;

/**
 * Tests for locating and replacing a sentence inside stored HTML.
 *
 * These matter more than most: this is the only code that writes to live
 * medical content, and the interesting cases are all the ones where it must
 * REFUSE rather than do its best.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_contentchecker\local\applier
 */
final class applier_test extends \advanced_testcase {

    /**
     * A plain sentence is replaced in place, leaving surrounding markup alone.
     *
     * @return void
     */
    public function test_replaces_plain_sentence(): void {
        $html = '<p>Intro text.</p><p>The heart has three chambers.</p><p>Later text.</p>';

        $result = applier::replace_in_html($html,
            'The heart has three chambers.',
            'The heart has four chambers.');

        $this->assertSame(
            '<p>Intro text.</p><p>The heart has four chambers.</p><p>Later text.</p>',
            $result);
    }

    /**
     * Inline markup between words does not defeat the match.
     *
     * @return void
     */
    public function test_matches_across_inline_markup(): void {
        $html = '<p>The <strong>heart</strong> has three chambers in total.</p>';

        $result = applier::replace_in_html($html,
            'The heart has three chambers in total.',
            'The heart has four chambers in total.');

        $this->assertSame('<p>The heart has four chambers in total.</p>', $result);
    }

    /**
     * An entity in the stored HTML still matches its decoded form.
     *
     * @return void
     */
    public function test_matches_across_entities(): void {
        $html = '<p>Sodium &amp; potassium are both electrolytes here.</p>';

        $result = applier::replace_in_html($html,
            'Sodium & potassium are both electrolytes here.',
            'Sodium and potassium are both electrolytes.');

        $this->assertSame('<p>Sodium and potassium are both electrolytes.</p>', $result);
    }

    /**
     * A sentence appearing twice is refused, because there is no way to know
     * which occurrence was judged.
     *
     * @return void
     */
    public function test_refuses_ambiguous_match(): void {
        $html = '<p>Check the dose.</p><p>Something else.</p><p>Check the dose.</p>';

        $this->assertNull(applier::replace_in_html($html,
            'Check the dose.', 'Check the dosage.'));
    }

    /**
     * A sentence that is not present is refused rather than approximated.
     *
     * @return void
     */
    public function test_refuses_absent_sentence(): void {
        $html = '<p>The heart has four chambers.</p>';

        $this->assertNull(applier::replace_in_html($html,
            'The liver has four lobes and sits below the diaphragm.',
            'The liver has four lobes.'));
    }

    /**
     * A very short fragment is refused: a tag-tolerant pattern built from two
     * or three common words would match far too much.
     *
     * @return void
     */
    public function test_refuses_short_fragment(): void {
        $html = '<p>The <em>heart</em> beats.</p>';

        $this->assertNull(applier::replace_in_html($html, 'The heart beats.', 'It beats.'));
    }

    /**
     * The replacement is escaped, so a suggestion containing markup cannot
     * inject it into the page.
     *
     * @return void
     */
    public function test_escapes_replacement(): void {
        $html = '<p>The heart has three chambers.</p>';

        $result = applier::replace_in_html($html,
            'The heart has three chambers.',
            'Four chambers <script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    /**
     * A dollar sign in the replacement survives, rather than being eaten as a
     * regex backreference.
     *
     * @return void
     */
    public function test_preserves_dollar_in_replacement(): void {
        $html = '<p>The <strong>cost</strong> is roughly one hundred units.</p>';

        $result = applier::replace_in_html($html,
            'The cost is roughly one hundred units.',
            'The cost is roughly $100 per course.');

        $this->assertStringContainsString('$100', $result);
    }
}
