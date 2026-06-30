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

/**
 * Reads the composed admin console (S12) for the page.
 *
 * Every heavy widget — KPIs, courses-by-status, admissions funnel, system health,
 * course operations, faculty load, users-by-role — is produced by the CRON /
 * event-driven rollup ({@see admin_rollup}) and read straight from cache_azmsi
 * here, never recomputed inline. The only live reads are the production pipeline
 * (so a stage advance shows immediately) and the viewer-specific portal links.
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
        $data = self::dataset();

        $kpis = $data['kpis'] ?? [];
        $kpis['coursestotal'] = (int) ($kpis['coursestotal'] ?? 48);
        $kpis['stale'] = empty($data['generatedon']);

        // Show the most relevant slice of the course-operations table (the rollup
        // already sorts running-first); the header reports the live total.
        $allops = $data['courseops'] ?? [];
        $shownops = array_slice($allops, 0, self::COURSEOPS_SHOWN);

        return [
            'kpis'            => $kpis,
            'coursesbystatus' => $data['coursesbystatus'] ?? ['rows' => [], 'running' => 0, 'total' => 0],
            'funnel'          => $data['funnel'] ?? [],
            'systemhealth'    => $data['systemhealth'] ?? [],
            'operational'     => !empty($data['operational']),
            'courseops'       => $shownops,
            'courseopstotal'  => (int) ($data['courseopstotal'] ?? count($allops)),
            'courseopsshown'  => count($shownops),
            'facultyload'     => $data['facultyload'] ?? [],
            'facultyactive'   => (int) ($data['facultyactive'] ?? 0),
            'usersbyrole'     => $data['usersbyrole'] ?? [],
            'rolesaccess'     => $data['rolesaccess'] ?? ['columns' => []],
            'announcements'   => $data['announcements'] ?? ['items' => [], 'forumid' => 0],
            'pipeline'        => pipeline::get_all(),
            'reviewspending'  => reviews::count_pending(),
            'reviewsurl'      => (new \moodle_url('/local/azmsi/reviews.php'))->out(false),
            'generatedon'     => (int) ($data['generatedon'] ?? 0),
            'stale'           => empty($data['generatedon']),
        ];
    }

    /** @var int Course-operations rows shown in the table (header reports the total). */
    private const COURSEOPS_SHOWN = 8;

    /**
     * The cached rollup dataset. Bootstrap-computes ONCE if the cache is cold
     * (e.g. first load after a purge, before cron has run); thereafter the page
     * is a pure cache read and cron/events keep it fresh.
     *
     * @return array
     */
    protected static function dataset(): array {
        $data = \cache::make('local_azmsi', 'rollups')->get(admin_rollup::KEY);
        if (empty($data) || empty($data['generatedon'])) {
            $data = admin_rollup::rebuild();
        }
        return $data;
    }
}
