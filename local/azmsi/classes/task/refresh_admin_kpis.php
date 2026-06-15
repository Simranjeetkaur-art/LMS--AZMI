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
 * Scheduled task: recompute admin KPIs + class-health into cache_azmsi.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_admin_kpis extends \core\task\scheduled_task {
    /**
     * Task name shown in the scheduled tasks admin page.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_refresh_admin_kpis', 'local_azmsi');
    }

    /**
     * Execute the rollup.
     */
    public function execute(): void {
        // TODO (AGENT_07): compute KPIs (active students, applications, faculty,
        // courses-built X/48, funnel, pipeline) and store in the rollups cache.
        mtrace('local_azmsi: refresh_admin_kpis stub — nothing to do yet.');
    }
}
