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

use local_azmsi\local\program;

/**
 * Scheduled task: roll up admin-console KPIs into cache_azmsi.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rollup_admin_kpis extends \core\task\scheduled_task {
    /**
     * Task display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_rollup_admin_kpis', 'local_azmsi');
    }

    /**
     * Compute and cache the KPI rollup.
     */
    public function execute(): void {
        global $DB;

        // Courses built = AZMSI courses currently flagged live in the catalog tree.
        $tree = program::get_catalog_tree();
        $coursesbuilt = 0;
        foreach ($tree['years'] as $year) {
            foreach ($year['quarters'] as $quarter) {
                foreach ($quarter['courses'] as $course) {
                    if ($course['status'] === 'in_progress') {
                        $coursesbuilt++;
                    }
                }
            }
        }
        $coursestotal = 0;
        foreach (program::catalog() as $info) {
            $coursestotal += count($info['courses']);
        }

        // Active students = distinct users with an active enrolment.
        $activestudents = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.status = 0 AND e.status = 0"
        );

        // Faculty = distinct users holding an editing-teacher/teacher role anywhere.
        $faculty = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ra.userid)
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE r.archetype IN ('editingteacher', 'teacher')"
        );

        $applications = $DB->count_records('local_azmsi_application', ['status' => 'open']);

        $kpis = [
            'activestudents' => (int) $activestudents,
            'applications'   => (int) $applications,
            'faculty'        => (int) $faculty,
            'coursesbuilt'   => (int) $coursesbuilt,
            'coursestotal'   => (int) $coursestotal,
            'generatedon'    => time(),
        ];

        \cache::make('local_azmsi', 'rollups')->set('admin_kpis', $kpis);
        mtrace('local_azmsi: admin KPIs rolled up (' . $coursesbuilt . '/' . $coursestotal . ' courses built).');
    }
}
