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
 * Adhoc task: notify a student that a grade/feedback was released.
 *
 * Queued by the assignment-graded observer. The message composition lands with
 * the faculty views (AGENT_06); for now it invalidates the student's overview
 * cache so the dashboard feedback card refreshes.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify_grade_released extends \core\task\adhoc_task {
    /**
     * Run the notification.
     */
    public function execute(): void {
        $data = (array) $this->get_custom_data();
        $userid = (int) ($data['userid'] ?? 0);
        if ($userid) {
            \cache::make('local_azmsi', 'rollups')->delete('overview_' . $userid);
        }
        // AGENT_06 sends the message_send() feedback-released notification.
    }
}
