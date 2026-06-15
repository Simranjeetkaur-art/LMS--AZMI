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

/**
 * Event observers — keep them cheap; queue heavy work as adhoc tasks.
 *
 * The event -> action contract is in 01_ARCHITECTURE.md §4. Each method is a
 * no-op stub for now; implement per AGENT_03.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Seed research-tracker row (if Q10+) and warm the dashboard cache.
     *
     * @param \core\event\user_enrolment_created $event
     */
    public static function on_enrolment_created(\core\event\user_enrolment_created $event): void {
        // TODO (AGENT_03/09): seed local_azmsi_research, invalidate rollups cache.
    }

    /**
     * Recompute course % + week checklist cache.
     *
     * @param \core\event\course_module_completion_updated $event
     */
    public static function on_activity_completion(\core\event\course_module_completion_updated $event): void {
        // TODO (AGENT_03/05): invalidate course/week progress in cache_azmsi.
    }

    /**
     * Recompute grade rollup; if the quiz is the AQE, advance application stage.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function on_quiz_submitted(\mod_quiz\event\attempt_submitted $event): void {
        // TODO (AGENT_03/08): queue grade rollup adhoc task; handle AQE branch.
    }

    /**
     * Update gradebook rollup, mark faculty queue item, notify student.
     *
     * @param \mod_assign\event\submission_graded $event
     */
    public static function on_assignment_graded(\mod_assign\event\submission_graded $event): void {
        // TODO (AGENT_03/06): update rollup + faculty queue; queue notification.
    }

    /**
     * Advance the program/quarter map; set graduation flag on final course.
     *
     * @param \core\event\course_completed $event
     */
    public static function on_course_completed(\core\event\course_completed $event): void {
        // TODO (AGENT_03/07): advance quarter map; trigger website revalidation task.
    }
}
