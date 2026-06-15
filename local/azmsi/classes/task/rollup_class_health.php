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
 * Scheduled task: roll up faculty class-health (avg grade, at-risk) into cache_azmsi.
 *
 * The per-course aggregation lands with the faculty views (AGENT_06); this
 * records the run marker so the cache definition is exercised and observable.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rollup_class_health extends \core\task\scheduled_task {
    /**
     * Task display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_rollup_class_health', 'local_azmsi');
    }

    /**
     * Compute and cache class-health rollups.
     */
    public function execute(): void {
        // AGENT_06 computes per-course average + at-risk counts.
        \cache::make('local_azmsi', 'rollups')->set('class_health', ['generatedon' => time()]);
        mtrace('local_azmsi: class-health rollup marker written.');
    }
}
