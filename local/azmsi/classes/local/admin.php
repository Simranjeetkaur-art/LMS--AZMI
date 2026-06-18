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

namespace local_azmsi\local;

use moodle_url;

/**
 * Composes the admin console (S12): KPIs (from the cache_azmsi rollup, never
 * computed inline), admissions funnel (live from local_azmsi_application), the
 * production pipeline, and per-role counts. Read-only aggregation.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin {
    /**
     * Build the admin console context.
     *
     * @return array
     */
    public static function console(): array {
        return [
            'kpis'     => self::kpis(),
            'funnel'   => self::funnel(),
            'pipeline' => pipeline::get_all(),
            'roles'    => self::roles(),
        ];
    }

    /**
     * KPIs read from the scheduled-task rollup in cache_azmsi (NOT computed here).
     *
     * @return array
     */
    protected static function kpis(): array {
        $kpis = \cache::make('local_azmsi', 'rollups')->get('admin_kpis') ?: [];
        return [
            'activestudents' => (int) ($kpis['activestudents'] ?? 0),
            'applications'   => (int) ($kpis['applications'] ?? 0),
            'faculty'        => (int) ($kpis['faculty'] ?? 0),
            'coursesbuilt'   => (int) ($kpis['coursesbuilt'] ?? 0),
            'coursestotal'   => (int) ($kpis['coursestotal'] ?? 48),
            'generatedon'    => (int) ($kpis['generatedon'] ?? 0),
            'stale'          => empty($kpis['generatedon']),
        ];
    }

    /**
     * Admissions funnel counts, live from local_azmsi_application.stage + status.
     *
     * @return array list of ['label','count','pct'] scaled to the largest stage
     */
    protected static function funnel(): array {
        global $DB;
        try {
            $applications = (int) $DB->count_records('local_azmsi_application', []);
            $aqe = (int) $DB->count_records_select(
                'local_azmsi_application',
                'stage = :a OR stage = :b',
                ['a' => 'aqe_scheduled', 'b' => 'aqe_completed']
            );
            $review = (int) $DB->count_records('local_azmsi_application', ['stage' => 'review']);
            $offers = (int) $DB->count_records('local_azmsi_application', ['stage' => 'decision']);
            $enrolled = (int) $DB->count_records('local_azmsi_application', ['status' => 'accepted']);
        } catch (\Throwable $e) {
            $applications = $aqe = $review = $offers = $enrolled = 0;
        }

        $rows = [
            ['label' => get_string('funnelapplications', 'local_azmsi'), 'count' => $applications],
            ['label' => get_string('funnelaqe', 'local_azmsi'), 'count' => $aqe],
            ['label' => get_string('funnelreview', 'local_azmsi'), 'count' => $review],
            ['label' => get_string('funneloffers', 'local_azmsi'), 'count' => $offers],
            ['label' => get_string('funnelenrolled', 'local_azmsi'), 'count' => $enrolled],
        ];
        $max = max(1, ...array_column($rows, 'count'));
        foreach ($rows as &$r) {
            $r['pct'] = (int) round($r['count'] / $max * 100);
        }
        return $rows;
    }

    /**
     * Per-role user counts with capability-gated portal deep-links.
     *
     * @return array
     */
    protected static function roles(): array {
        global $DB;
        $out = [];
        try {
            $defs = [
                ['archetypes' => ['student'], 'label' => get_string('rolestudents', 'local_azmsi'),
                    'url' => (new moodle_url('/my'))->out(false), 'cap' => null],
                ['archetypes' => ['editingteacher', 'teacher'], 'label' => get_string('rolefaculty', 'local_azmsi'),
                    'url' => (new moodle_url('/local/azmsi/faculty.php'))->out(false), 'cap' => 'local/azmsi:viewfacultyportal'],
                ['archetypes' => ['manager'], 'label' => get_string('rolemanagers', 'local_azmsi'),
                    'url' => (new moodle_url('/local/azmsi/admin.php'))->out(false), 'cap' => 'local/azmsi:viewadminconsole'],
            ];
            $syscontext = \core\context\system::instance();
            foreach ($defs as $d) {
                [$insql, $params] = $DB->get_in_or_equal($d['archetypes'], SQL_PARAMS_NAMED);
                $count = (int) $DB->count_records_sql(
                    "SELECT COUNT(DISTINCT ra.userid)
                       FROM {role_assignments} ra JOIN {role} r ON r.id = ra.roleid
                      WHERE r.archetype $insql",
                    $params
                );
                $out[] = [
                    'label'   => $d['label'],
                    'count'   => $count,
                    'url'     => $d['url'],
                    'canview' => is_null($d['cap']) || has_capability($d['cap'], $syscontext),
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }
}
