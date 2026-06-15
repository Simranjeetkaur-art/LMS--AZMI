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

use local_azmsi\task\recompute_course_progress;

/**
 * Tests the event/react path: an adhoc recompute observably updates cache_azmsi,
 * and grading an assignment queues that recompute (AGENT_03 AC3).
 *
 * @package    local_azmsi
 * @covers     \local_azmsi\task\recompute_course_progress
 * @covers     \local_azmsi\observer
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class progress_cache_test extends \advanced_testcase {
    /**
     * The recompute task writes the per-course progress into cache_azmsi.
     */
    public function test_recompute_writes_cache(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $cache = \cache::make('local_azmsi', 'rollups');
        $key = "progress_{$course->id}_{$user->id}";
        $this->assertFalse($cache->get($key));

        $task = new recompute_course_progress();
        $task->set_custom_data(['courseid' => $course->id, 'userid' => $user->id]);
        $task->execute();

        $this->assertNotFalse($cache->get($key), 'Progress must be cached after recompute.');
        $this->assertIsInt($cache->get($key));
    }

    /**
     * Grading an assignment fires submission_graded, whose observer queues a
     * recompute_course_progress adhoc task.
     */
    public function test_assignment_graded_queues_recompute(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $assignrecord = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assignrecord->id);
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);

        // Grade the student -> triggers \mod_assign\event\submission_graded.
        $this->setUser($teacher);
        $data = new \stdClass();
        $data->grade = 80.0;
        $assign->save_grade($student->id, $data);

        $tasks = \core\task\manager::get_adhoc_tasks(recompute_course_progress::class);
        $this->assertNotEmpty($tasks, 'Grading should queue a recompute task.');
    }
}
