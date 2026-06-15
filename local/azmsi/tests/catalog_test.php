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

namespace local_azmsi;

use local_azmsi\local\program;

/**
 * Tests for catalog seeding + the live tree read-back (AGENT_03 AC1).
 *
 * @package    local_azmsi
 * @covers     \local_azmsi\local\program
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalog_test extends \advanced_testcase {
    /**
     * Seeding builds the full 3-year / 12-quarter / 48-course tree with Q1 live.
     */
    public function test_seed_builds_full_tree(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = program::seed();
        $this->assertSame(48, $result['created']);
        $this->assertSame(0, $result['updated']);

        $tree = program::get_catalog_tree();
        $this->assertSame('eMD', $tree['program']);
        $this->assertCount(3, $tree['years']);

        $quarters = 0;
        $courses = 0;
        $q1status = null;
        $q2status = null;
        foreach ($tree['years'] as $year) {
            foreach ($year['quarters'] as $quarter) {
                $quarters++;
                $courses += count($quarter['courses']);
                if ($quarter['number'] === 1) {
                    $q1status = $quarter['status'];
                }
                if ($quarter['number'] === 2) {
                    $q2status = $quarter['status'];
                }
            }
        }
        $this->assertSame(12, $quarters);
        $this->assertSame(48, $courses);
        $this->assertSame('in_progress', $q1status, 'Q1 must be live');
        $this->assertSame('planned', $q2status, 'Q2 must be planned');
    }

    /**
     * Re-running the seed updates in place (idempotent, no duplicates).
     */
    public function test_seed_is_idempotent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        program::seed();
        $second = program::seed();
        $this->assertSame(0, $second['created']);
        $this->assertSame(48, $second['updated']);
    }

    /**
     * Renaming a course in Moodle changes the WS tree with no code change
     * (the "nothing static" guarantee).
     */
    public function test_rename_course_reflects_in_tree(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once(__DIR__ . '/../../../course/lib.php');

        program::seed();

        $course = $DB->get_record('course', ['idnumber' => 'EMD-101'], '*', MUST_EXIST);
        $course->fullname = 'Renamed Terminology Course';
        update_course($course);

        $tree = program::get_catalog_tree();
        $found = null;
        foreach ($tree['years'] as $year) {
            foreach ($year['quarters'] as $quarter) {
                foreach ($quarter['courses'] as $c) {
                    if ($c['code'] === 'EMD-101') {
                        $found = $c['name'];
                    }
                }
            }
        }
        $this->assertSame('Renamed Terminology Course', $found);
    }
}
