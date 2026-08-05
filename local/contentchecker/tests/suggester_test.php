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

use local_contentchecker\image\image_result;
use local_contentchecker\local\suggester;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/contentchecker/tests/fixtures/stub_backend.php');

/**
 * Tests for content-aware enrichment matching.
 *
 * Every case here is a defect found by running the suggester over real course
 * content. Matching quality is the whole value of the feature: an editor shown
 * an irrelevant model concludes the tool did not read the page, and an editor
 * shown nothing concludes it is broken.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_contentchecker\local\suggester
 * @covers     \local_contentchecker\image\image_result
 */
final class suggester_test extends \advanced_testcase {

    /**
     * Invoke a protected method on the suggester.
     *
     * @param string $name Method name.
     * @param array $args Arguments.
     * @return mixed The return value.
     */
    private function call(string $name, array $args) {
        $method = new \ReflectionMethod(suggester::class, $name);
        return $method->invokeArgs(new suggester(new stub_backend()), $args);
    }

    /**
     * Register an asset so the matcher has something to find.
     *
     * @param string $name Asset name.
     * @param string $description Asset description.
     * @param string $type Asset type.
     * @return int The asset id.
     */
    private function asset(string $name, string $description = '',
            string $type = 'model3d'): int {
        global $DB;
        return $DB->insert_record('local_cchecker_assets', (object) [
            'name' => $name, 'assettype' => $type, 'viewer' => 'iframe',
            'url' => 'https://example.org/m', 'posterurl' => '', 'body' => '',
            'description' => $description, 'licence' => 'CC0', 'attribution' => 'x',
            'enabled' => 1, 'sortorder' => 0, 'usermodified' => 2,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * A single common word must not match an unrelated model.
     *
     * Observed live: a concept called "the four-part structure of a medical
     * term" matched "B-DNA dodecamer" and "COX-1 with ibuprofen" on the word
     * "structure" alone.
     *
     * @return void
     */
    public function test_common_word_alone_does_not_match(): void {
        $this->resetAfterTest();

        $this->asset('B-DNA dodecamer (PDB 1BNA)', 'Watson-Crick structure');
        $this->asset('COX-1 with ibuprofen (PDB 1EQG)', 'ovine structure');

        $concept = (object) [
            'concept' => 'The four-part structure of a medical term',
            'searchterms' => 'medical term structure',
            'mediatype' => 'diagram',
        ];

        $this->assertSame([], $this->call('matching_assets', [$concept]),
            'a shared stopword must not surface an unrelated model');
    }

    /**
     * A scientific acronym on its own IS enough to match.
     *
     * Observed live: "ATP Molecular Structure" failed to find the registered
     * "Mitochondrial ATP synthase" because ATP is three characters and was
     * filtered out, while the useless word "molecular" survived.
     *
     * @return void
     */
    public function test_acronym_alone_matches(): void {
        $this->resetAfterTest();

        $this->asset('Mitochondrial ATP synthase (PDB 5ARA)', 'Oxidative phosphorylation');
        $this->asset('Human deoxyhaemoglobin (PDB 4HHB)', 'Haemoglobin');

        $concept = (object) [
            'concept' => 'ATP Molecular Structure',
            'searchterms' => 'ATP molecule',
            'mediatype' => 'model3d',
        ];

        $matches = $this->call('matching_assets', [$concept]);

        $this->assertNotEmpty($matches, 'an acronym is the most distinctive term, not the least');
        $this->assertStringContainsString('ATP', $matches[0]->name);
    }

    /**
     * Keywords keep acronyms and identifiers, and drop filler.
     *
     * @return void
     */
    public function test_keyword_weighting(): void {
        $words = $this->call('keywords', ['ATP Molecular Structure p53']);

        $this->assertArrayHasKey('atp', $words);
        $this->assertArrayHasKey('p53', $words);
        $this->assertArrayHasKey('molecular', $words);
        // "Structure" is filler in a medical registry.
        $this->assertArrayNotHasKey('structure', $words);

        // An acronym outweighs an ordinary word, so one decisive hit is enough.
        $this->assertGreaterThan($words['molecular'], $words['atp']);
    }

    /**
     * A comma-separated list of alternatives collapses to one phrase.
     *
     * Observed live: the model returned "medical term structure, word parts
     * anatomy, prefix root suffix diagram" and the image search took the whole
     * string as one query, returning nothing every time.
     *
     * @return void
     */
    public function test_search_terms_reduced_to_one_phrase(): void {
        $this->assertSame('medical term structure',
            $this->call('clean_terms',
                ['medical term structure, word parts anatomy, prefix root suffix', 'x']));

        // Long phrases are capped rather than passed through whole.
        $this->assertSame('one two three four',
            $this->call('clean_terms', ['one two three four five six', 'x']));

        // Nothing usable falls back to the concept name.
        $this->assertSame('Fallback concept',
            $this->call('clean_terms', ['', 'Fallback concept']));
    }

    /**
     * The query ladder goes from precise to broad, without duplicates.
     *
     * @return void
     */
    public function test_query_ladder_broadens(): void {
        $concept = (object) ['searchterms' => 'mitochondrion structure diagram'];
        $ladder = $this->call('query_ladder', [$concept]);

        $this->assertSame('mitochondrion structure diagram', $ladder[0]);
        $this->assertGreaterThan(1, count($ladder), 'a miss must have somewhere to fall back to');
        $this->assertSame(count($ladder), count(array_unique($ladder)));

        // A single short phrase needs no ladder.
        $single = $this->call('query_ladder', [(object) ['searchterms' => 'kidney']]);
        $this->assertSame(['kidney'], $single);
    }

    /**
     * NonCommercial and NoDerivatives licences are flagged.
     *
     * These are the two that bite an institution republishing a course, and
     * both hide inside a code like "by-nc-nd-2.0".
     *
     * @return void
     */
    public function test_restrictive_licences_are_flagged(): void {
        $make = fn($licence) => new image_result(
            sourceid: 'openverse', ref: 'r', title: 't', thumburl: '', fullurl: '',
            license: $licence);

        $this->assertTrue($make('by-nc-nd-2.0')->is_restrictive());
        $this->assertTrue($make('by-nc-2.0')->is_restrictive());
        $this->assertTrue($make('by-nd-4.0')->is_restrictive());

        $this->assertFalse($make('by-4.0')->is_restrictive());
        $this->assertFalse($make('by-sa-4.0')->is_restrictive());
        $this->assertFalse($make('cc0-1.0')->is_restrictive());
        $this->assertFalse($make('')->is_restrictive());

        // The flag reaches the picker payload.
        $this->assertTrue($make('by-nc-2.0')->to_array()['restrictive']);
    }

    /**
     * A disabled asset is never proposed, so an entry still waiting for its
     * URL cannot be offered as insertable.
     *
     * @return void
     */
    public function test_disabled_assets_are_not_proposed(): void {
        global $DB;

        $this->resetAfterTest();

        $id = $this->asset('Knee Anatomy', 'joint ligaments');
        $DB->set_field('local_cchecker_assets', 'enabled', 0, ['id' => $id]);

        $concept = (object) [
            'concept' => 'Knee joint ligaments',
            'searchterms' => 'knee ligaments',
            'mediatype' => 'model3d',
        ];

        $this->assertSame([], $this->call('matching_assets', [$concept]));
    }
}
