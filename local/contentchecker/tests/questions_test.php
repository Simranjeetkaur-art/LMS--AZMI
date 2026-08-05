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

use local_contentchecker\local\questions;
use local_contentchecker\local\templates;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/stub_backend.php');

/**
 * Tests for validating generated follow-up questions and layout slots.
 *
 * The question cases are about rejecting malformed model output. A question
 * whose answer key indexes past the end of its options would tell a learner
 * that a correct answer is wrong, which is worse than showing no question.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_contentchecker\local\questions
 * @covers     \local_contentchecker\local\templates
 */
final class questions_test extends \advanced_testcase {

    /**
     * Run one generated question through validation.
     *
     * @param array $q The raw question.
     * @param bool $allowshort Whether free text is permitted.
     * @return \stdClass|null The normalised record, or null when rejected.
     */
    private function normalise(array $q, bool $allowshort = false): ?\stdClass {
        // No setAccessible() call: it has had no effect since PHP 8.1 and is
        // deprecated as of 8.5.
        $method = new \ReflectionMethod(questions::class, 'normalise');
        return $method->invoke(new questions(new stub_backend()), $q, $allowshort);
    }

    /**
     * A well-formed multiple choice question is accepted.
     *
     * @return void
     */
    public function test_accepts_valid_multichoice(): void {
        $result = $this->normalise([
            'qtype' => 'multichoice',
            'qtext' => 'How many chambers does the heart have?',
            'options' => ['Two', 'Three', 'Four', 'Five'],
            'answer' => [2],
            'explanation' => 'The human heart has four chambers.',
        ]);

        $this->assertNotNull($result);
        $this->assertSame([2], json_decode($result->answer, true));
    }

    /**
     * An answer index past the end of the options list is rejected.
     *
     * @return void
     */
    public function test_rejects_out_of_range_answer(): void {
        $this->assertNull($this->normalise([
            'qtype' => 'multichoice',
            'qtext' => 'Which one?',
            'options' => ['A', 'B'],
            'answer' => [7],
            'explanation' => 'Because.',
        ]));
    }

    /**
     * A multiple choice question with two correct answers is rejected, because
     * the widget renders it as radio buttons that cannot express that.
     *
     * @return void
     */
    public function test_rejects_multichoice_with_two_answers(): void {
        $this->assertNull($this->normalise([
            'qtype' => 'multichoice',
            'qtext' => 'Which one?',
            'options' => ['A', 'B', 'C'],
            'answer' => [0, 1],
            'explanation' => 'Because.',
        ]));
    }

    /**
     * A checkbox question where every option is correct teaches nothing.
     *
     * @return void
     */
    public function test_rejects_checkbox_where_everything_is_correct(): void {
        $this->assertNull($this->normalise([
            'qtype' => 'checkbox',
            'qtext' => 'Select all that apply.',
            'options' => ['A', 'B', 'C'],
            'answer' => [0, 1, 2],
            'explanation' => 'All of them.',
        ]));
    }

    /**
     * True/false options are normalised regardless of what the model returned.
     *
     * @return void
     */
    public function test_normalises_truefalse_options(): void {
        $result = $this->normalise([
            'qtype' => 'truefalse',
            'qtext' => 'The heart has four chambers.',
            'options' => ['Yes', 'No', 'Maybe'],
            'answer' => [0],
            'explanation' => 'It does.',
        ]);

        $this->assertNotNull($result);
        $this->assertSame(['True', 'False'], json_decode($result->options, true));
    }

    /**
     * Free-text questions are dropped when the site has not enabled them.
     *
     * @return void
     */
    public function test_rejects_shorttext_when_disabled(): void {
        $this->assertNull($this->normalise([
            'qtype' => 'shorttext',
            'qtext' => 'Name the chambers.',
            'options' => [],
            'answer' => [],
            'explanation' => 'Two atria and two ventricles.',
        ], false));

        $this->assertNotNull($this->normalise([
            'qtype' => 'shorttext',
            'qtext' => 'Name the chambers.',
            'options' => [],
            'answer' => [],
            'explanation' => 'Two atria and two ventricles.',
        ], true));
    }

    /**
     * An empty stem is rejected.
     *
     * @return void
     */
    public function test_rejects_empty_stem(): void {
        $this->assertNull($this->normalise([
            'qtype' => 'multichoice',
            'qtext' => '   ',
            'options' => ['A', 'B'],
            'answer' => [0],
            'explanation' => 'Because.',
        ]));
    }

    /**
     * A layout only receives the slots it declares, so switching layout cannot
     * leak a stale slot into the rendered output.
     *
     * @return void
     */
    public function test_layout_context_drops_undeclared_slots(): void {
        $context = templates::context('medialeft', [
            'media' => '<img src="x.png" alt="x">',
            'body' => '<p>Body.</p>',
            'sidebar' => '<p>Should not appear.</p>',
        ]);

        $this->assertArrayHasKey('media', $context);
        $this->assertArrayHasKey('body', $context);
        $this->assertArrayNotHasKey('sidebar', $context);
    }

    /**
     * The first tab is marked active and every tab gets an id, which the
     * template needs and Mustache cannot work out for itself.
     *
     * @return void
     */
    public function test_layout_context_marks_first_tab_active(): void {
        $context = templates::context('tabbedcomparison', [
            'intro' => '<p>Compare.</p>',
            'tabs' => [
                ['title' => 'Drug A', 'body' => '<p>A</p>'],
                ['title' => 'Drug B', 'body' => '<p>B</p>'],
            ],
        ]);

        $this->assertTrue($context['tabs'][0]['active']);
        $this->assertArrayNotHasKey('active', $context['tabs'][1]);
        $this->assertSame('cct-0', $context['tabs'][0]['tabid']);
        $this->assertSame('cct-1', $context['tabs'][1]['tabid']);
    }
}
