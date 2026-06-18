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

namespace local_azmsi\output;

use core\output\named_templatable;
use renderable;
use renderer_base;
use local_azmsi\local\admin;

/**
 * Admin console (S12) renderable — gold-accented manager home.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_console implements named_templatable, renderable {
    /** @var bool whether the viewer may advance pipeline stages. */
    protected $canmanage;

    /**
     * Constructor.
     *
     * @param bool $canmanage viewer holds local/azmsi:managepipeline
     */
    public function __construct(bool $canmanage) {
        $this->canmanage = $canmanage;
    }

    /**
     * Template name.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'local_azmsi/admin_console';
    }

    /**
     * Compose the console context.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = admin::console();

        // Decorate pipeline stages with value flags + the next forward value.
        $next = ['queued' => 'active', 'active' => 'done'];
        $pipeline = array_map(function ($course) use ($next) {
            $course['stages'] = array_map(function ($s) use ($next) {
                $s['isdone']    = $s['value'] === 'done';
                $s['isactive']  = $s['value'] === 'active';
                $s['isqueued']  = $s['value'] === 'queued';
                $s['cannext']   = isset($next[$s['value']]) && $this->canmanage;
                $s['nextvalue'] = $next[$s['value']] ?? '';
                return $s;
            }, $course['stages']);
            return $course;
        }, $data['pipeline']);

        $opslabel = get_string(
            'showingxofy',
            'local_azmsi',
            ['shown' => $data['courseopsshown'], 'total' => $data['courseopstotal']]
        );
        $generatedonstr = $data['generatedon']
            ? userdate($data['generatedon'], get_string('strftimedatetimeshort', 'langconfig')) : '';

        return [
            'kpis'            => $data['kpis'],
            'coursesbystatus' => $data['coursesbystatus'],
            'funnel'          => $data['funnel'],
            'systemhealth'    => $data['systemhealth'],
            'courseops'       => $data['courseops'],
            'courseopslabel'  => $opslabel,
            'facultyload'     => $data['facultyload'],
            'usersbyrole'     => $data['usersbyrole'],
            'pipeline'        => $pipeline,
            'portals'         => $data['portals'],
            'generatedonstr'  => $generatedonstr,
            'stale'           => $data['stale'],
            'canmanage'       => $this->canmanage,
            'sesskey'         => sesskey(),
        ];
    }
}
