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

use core\task\manager;
use local_azmsi\task\recompute_course_progress;
use local_azmsi\task\notify_grade_released;
use local_azmsi\task\revalidate_website;
use local_azmsi\task\refresh_admin_console;

/**
 * Event observers — the write/react path (01_ARCHITECTURE.md §4).
 *
 * Observers stay cheap: they only queue adhoc tasks (deduplicated) that do the
 * aggregation / notification / website revalidation. No heavy work inline.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Queue a course-progress recompute for one user (deduplicated).
     *
     * @param int $courseid
     * @param int $userid
     */
    protected static function queue_progress(int $courseid, int $userid): void {
        if (!$courseid || !$userid) {
            return;
        }
        $task = new recompute_course_progress();
        $task->set_custom_data(['courseid' => $courseid, 'userid' => $userid]);
        manager::queue_adhoc_task($task, true);
    }

    /**
     * Queue a rebuild of the admin-console dataset (deduplicated).
     *
     * Cheap by design: the heavy aggregation runs later in the adhoc task, and
     * dedup collapses a burst of events into a single pending rebuild.
     */
    protected static function queue_admin_refresh(): void {
        manager::queue_adhoc_task(new refresh_admin_console(), true);
    }

    /**
     * Course created: catalog/status counts changed — refresh the console.
     *
     * @param \core\event\course_created $event
     */
    public static function on_course_created(\core\event\course_created $event): void {
        self::queue_admin_refresh();
    }

    /**
     * Course updated (status flip, visibility, dates): refresh the console.
     *
     * @param \core\event\course_updated $event
     */
    public static function on_course_updated(\core\event\course_updated $event): void {
        self::queue_admin_refresh();
    }

    /**
     * Course deleted: refresh the console so totals drop.
     *
     * @param \core\event\course_deleted $event
     */
    public static function on_course_deleted(\core\event\course_deleted $event): void {
        self::queue_admin_refresh();
    }

    /**
     * Role assigned: users-by-role + faculty-load counts changed.
     *
     * @param \core\event\role_assigned $event
     */
    public static function on_role_assigned(\core\event\role_assigned $event): void {
        self::queue_admin_refresh();
    }

    /**
     * Role unassigned: users-by-role + faculty-load counts changed.
     *
     * @param \core\event\role_unassigned $event
     */
    public static function on_role_unassigned(\core\event\role_unassigned $event): void {
        self::queue_admin_refresh();
    }

    /**
     * Enrolment deleted: active-student + per-course counts changed.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function on_enrolment_deleted(\core\event\user_enrolment_deleted $event): void {
        self::queue_admin_refresh();
    }

    /**
     * Enrolment created: invalidate the student's overview cache so it rebuilds.
     *
     * @param \core\event\user_enrolment_created $event
     */
    public static function on_enrolment_created(\core\event\user_enrolment_created $event): void {
        $userid = (int) $event->relateduserid;
        if ($userid) {
            \cache::make('local_azmsi', 'rollups')->delete('overview_' . $userid);
        }
        self::queue_admin_refresh();
        // AGENT_09 seeds local_azmsi_research when the course is Q10+.
    }

    /**
     * Activity completion updated: recompute course % for the affected user.
     *
     * @param \core\event\course_module_completion_updated $event
     */
    public static function on_activity_completion(\core\event\course_module_completion_updated $event): void {
        self::queue_progress((int) $event->courseid, (int) $event->relateduserid);
    }

    /**
     * Quiz attempt submitted: recompute progress (AQE branch handled in AGENT_08).
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function on_quiz_submitted(\mod_quiz\event\attempt_submitted $event): void {
        $userid = (int) ($event->relateduserid ?: $event->userid);
        self::queue_progress((int) $event->courseid, $userid);
        // AGENT_08 advances the application stage when the quiz is the AQE.
    }

    /**
     * Assignment graded: recompute progress and queue a (thin) grade notification.
     *
     * @param \mod_assign\event\submission_graded $event
     */
    public static function on_assignment_graded(\mod_assign\event\submission_graded $event): void {
        $userid = (int) $event->relateduserid;
        self::queue_progress((int) $event->courseid, $userid);

        $notify = new notify_grade_released();
        $notify->set_custom_data([
            'courseid' => (int) $event->courseid,
            'userid'   => $userid,
            'cmid'     => (int) $event->contextinstanceid,
        ]);
        manager::queue_adhoc_task($notify, true);
    }

    /**
     * Course completed: trigger website on-demand revalidation.
     *
     * @param \core\event\course_completed $event
     */
    public static function on_course_completed(\core\event\course_completed $event): void {
        $userid = (int) $event->relateduserid;
        if ($userid) {
            \cache::make('local_azmsi', 'rollups')->delete('overview_' . $userid);
        }
        self::queue_admin_refresh();
        manager::queue_adhoc_task(new revalidate_website(), true);
    }
}
