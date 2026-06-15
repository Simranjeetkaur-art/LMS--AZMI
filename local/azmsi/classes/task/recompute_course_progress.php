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

namespace local_azmsi\task;

/**
 * Adhoc task: recompute one user's course-progress and refresh the rollups cache.
 *
 * Queued by observers on quiz submission, assignment grading and activity
 * completion. Writes the per-course progress into cache_azmsi and invalidates
 * the user's composed overview so the next read rebuilds it (01_ARCHITECTURE §4/§5).
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recompute_course_progress extends \core\task\adhoc_task {
    /**
     * Recompute and cache the progress.
     */
    public function execute(): void {
        $data = (array) $this->get_custom_data();
        $courseid = (int) ($data['courseid'] ?? 0);
        $userid = (int) ($data['userid'] ?? 0);
        if (!$courseid || !$userid) {
            return;
        }

        $course = get_course($courseid);
        $percentage = \core_completion\progress::get_course_progress_percentage($course, $userid);
        $progress = is_null($percentage) ? 0 : (int) round($percentage);

        $cache = \cache::make('local_azmsi', 'rollups');
        $cache->set("progress_{$courseid}_{$userid}", $progress);
        // Force the composed overview to rebuild on next read.
        $cache->delete("overview_{$userid}");
    }
}
