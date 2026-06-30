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
 * Pulls the shared overview composition (local_azmsi) — greeting, continue banner,
 * in-progress course cards, due-this-week, eMD journey — all from live Moodle data.
 * No switch-portal block and no admissions content on the student dashboard.
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
        $due = $data['dueweek'] ?? ['items' => [], 'range' => '', 'hasitems' => false];
        $journey = $data['journey'] ?? [];

        $courses = array_map(static function ($c) {
            $inprogress = $c['status'] === 'in_progress';
            $c['status_inprogress'] = $inprogress;
            $c['statuslabel'] = get_string($inprogress ? 'statusinprogress' : 'statusplanned', 'block_azmsi_dashboard');
            $c['hascredits'] = $c['credits'] > 0;
            $c['hasinstructor'] = !empty($c['instructor']);
            $c['weeklabel'] = get_string('weekn', 'block_azmsi_dashboard', (int) ($c['week'] ?? 1));
            return $c;
        }, $data['courses']);

        $continuedata = $data['continue'] ?? ['has' => false];
        if (!empty($continuedata['has']) && empty($continuedata['coursecode'])) {
            $continuedata['coursecode'] = '';
        }

        return [
            'firstname'            => $data['firstname'],
            'fullname'             => $data['fullname'],
            'programsubtitle'      => $data['programsubtitle'] ?? '',
            'average'              => $data['average'],
            'hasaverage'           => $data['average'] > 0,
            'modulescompleted'     => $data['modulescompleted'],
            'coursecount'          => $data['coursecount'],
            'currentquarter'       => (int) ($data['currentquarter'] ?? 0),
            'inprogressmeta'       => $data['inprogressmeta'] ?? '',
            'inprogresstitle'      => (int) ($data['currentquarter'] ?? 0)
                ? get_string('inprogressquarter', 'block_azmsi_dashboard', $data['currentquarter'])
                : get_string('inprogress', 'block_azmsi_dashboard'),
            'continue'             => $continuedata,
            'courses'              => $courses,
            'hascourses'           => !empty($courses),
            'dueweek'              => $due['items'] ?? [],
            'duerange'             => $due['range'] ?? '',
            'hasdueweek'           => !empty($due['hasitems']),
            'duecount'             => count($due['items'] ?? []),
            'journey'              => $journey,
            'hasjourney'           => !empty($journey['quarters']),
            'hasnextliveclass'     => !empty($journey['hasnextliveclass']),
        ];
    }
}
