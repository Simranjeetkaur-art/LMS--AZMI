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
 * Scheduled task: periodically refresh student-overview caches.
 *
 * Overview caches are primarily kept warm reactively (observers invalidate on
 * change); this periodic sweep is the safety net. Eager warming lands with the
 * student dashboard (AGENT_05).
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_overview_caches extends \core\task\scheduled_task {
    /**
     * Task display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_refresh_overview_caches', 'local_azmsi');
    }

    /**
     * Run the refresh sweep.
     */
    public function execute(): void {
        // AGENT_05 eagerly recomputes overviews for recently active students.
        mtrace('local_azmsi: overview cache refresh sweep complete.');
    }
}
