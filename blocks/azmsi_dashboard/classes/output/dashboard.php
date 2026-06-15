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

namespace block_azmsi_dashboard\output;

use core\output\named_templatable;
use renderable;
use renderer_base;
use local_azmsi\local\overview;

/**
 * AZMSI student dashboard (S4) renderable.
 *
 * Pulls the shared overview composition (local_azmsi) — greeting, continue card,
 * course cards, due-this-week, program map, KPIs — all from live Moodle data,
 * nothing hardcoded. Output via Mustache + theme_azmsi tokens.
 *
 * @package    block_azmsi_dashboard
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard implements named_templatable, renderable {
    /** @var int the user whose dashboard this is. */
    protected $userid;

    /**
     * Constructor.
     *
     * @param int $userid
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Template name.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'block_azmsi_dashboard/dashboard';
    }

    /**
     * Compose the template context from the shared overview.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = overview::for_user($this->userid);

        // Per-course presentation flags/labels (Mustache can't compare strings).
        $courses = array_map(static function ($c) {
            $inprogress = $c['status'] === 'in_progress';
            $c['status_inprogress'] = $inprogress;
            $c['statuslabel'] = get_string($inprogress ? 'statusinprogress' : 'statusplanned', 'block_azmsi_dashboard');
            $c['hascredits'] = $c['credits'] > 0;
            return $c;
        }, $data['courses']);
        $data['courses'] = $courses;

        // Capability-gated Switch Portal (reuse the theme component when present).
        $switchportal = null;
        if (class_exists('\\theme_azmsi\\output\\switch_portal')) {
            $portal = new \theme_azmsi\output\switch_portal();
            $exported = $portal->export_for_template($output);
            $switchportal = !empty($exported['haslinks']) ? $exported : null;
        }

        return [
            'firstname'        => $data['firstname'],
            'fullname'         => $data['fullname'],
            'average'          => $data['average'],
            'hasaverage'       => $data['average'] > 0,
            'modulescompleted' => $data['modulescompleted'],
            'coursecount'      => $data['coursecount'],
            'continue'         => $data['continue'],
            'courses'          => $data['courses'],
            'hascourses'       => !empty($data['courses']),
            'dueweek'          => $data['dueweek'],
            'hasdueweek'       => !empty($data['dueweek']),
            'duecount'         => count($data['dueweek']),
            'programmap'       => $data['programmap'],
            'switchportal'     => $switchportal,
            'hasswitchportal'  => !is_null($switchportal),
        ];
    }
}
