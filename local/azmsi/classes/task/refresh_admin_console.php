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

use local_azmsi\local\admin_rollup;

/**
 * Adhoc task: rebuild the admin console dataset on demand.
 *
 * Queued (deduplicated) by {@see \local_azmsi\observer} whenever an event changes
 * something the dashboard shows — a course is created/updated/deleted, an
 * enrolment or role assignment changes, or a course is completed. Dedup means at
 * most one rebuild is ever pending no matter how many events fire, so the console
 * tracks live activity without per-event cost. The scheduled rollup is the safety
 * net if cron is idle between events.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_admin_console extends \core\task\adhoc_task {
    /**
     * Rebuild and cache the admin dataset.
     */
    public function execute(): void {
        admin_rollup::rebuild();
        mtrace('local_azmsi: admin console refreshed (event-driven).');
    }
}
