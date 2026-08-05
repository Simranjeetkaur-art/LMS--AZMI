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

use local_contentchecker\local\blocks;

/**
 * Tests for splitting page content into addressable blocks.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_contentchecker\local\blocks
 */
final class blocks_test extends \advanced_testcase {

    /**
     * Build a paragraph long enough to clear the minimum block length.
     *
     * @param string $seed Distinguishing text.
     * @return string HTML paragraph.
     */
    private function para(string $seed): string {
        return '<p>' . $seed . ' ' . str_repeat('Clinical detail follows here. ', 12) . '</p>';
    }

    /**
     * Content is cut at headings, one block per heading.
     *
     * @return void
     */
    public function test_splits_at_headings(): void {
        $html = '<h2>Anatomy</h2>' . $this->para('Anatomy body.')
            . '<h2>Physiology</h2>' . $this->para('Physiology body.');

        $result = blocks::split($html);

        $this->assertCount(2, $result);
        $this->assertSame('Anatomy', $result[0]->title);
        $this->assertSame('Physiology', $result[1]->title);
    }

    /**
     * A block reference is derived from its heading, so inserting a new section
     * ahead of it does not change the reference and orphan its questions.
     *
     * @return void
     */
    public function test_ref_is_stable_across_insertion(): void {
        $before = '<h2>Physiology</h2>' . $this->para('Physiology body.');
        $after = '<h2>Anatomy</h2>' . $this->para('Anatomy body.')
            . '<h2>Physiology</h2>' . $this->para('Physiology body.');

        $refbefore = blocks::split($before)[0]->ref;
        $refafter = blocks::split($after)[1]->ref;

        $this->assertSame($refbefore, $refafter);
    }

    /**
     * Two identical headings on one page get distinct references, or they
     * would show each other's questions.
     *
     * @return void
     */
    public function test_duplicate_headings_get_distinct_refs(): void {
        $html = '<h2>Summary</h2>' . $this->para('First summary.')
            . '<h2>Summary</h2>' . $this->para('Second summary.');

        $result = blocks::split($html);

        $this->assertCount(2, $result);
        $this->assertNotSame($result[0]->ref, $result[1]->ref);
    }

    /**
     * Content before the first heading becomes its own lead block.
     *
     * @return void
     */
    public function test_lead_content_becomes_a_block(): void {
        $html = $this->para('Lead paragraph.') . '<h2>Anatomy</h2>' . $this->para('Body.');

        $result = blocks::split($html);

        $this->assertCount(2, $result);
        $this->assertSame('', $result[0]->title);
        $this->assertSame('b-lead', $result[0]->ref);
    }

    /**
     * Blocks too short to be worth questioning are dropped.
     *
     * @return void
     */
    public function test_drops_short_blocks(): void {
        $html = '<h2>Stub</h2><p>Too short.</p><h2>Real</h2>' . $this->para('Real body.');

        $result = blocks::split($html);

        $this->assertCount(1, $result);
        $this->assertSame('Real', $result[0]->title);
    }

    /**
     * Content with no headings at all still yields one addressable block.
     *
     * @return void
     */
    public function test_handles_content_without_headings(): void {
        $result = blocks::split($this->para('Just prose.'));

        $this->assertCount(1, $result);
        $this->assertSame('b-lead', $result[0]->ref);
    }
}
