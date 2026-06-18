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
use local_azmsi\local\faculty;

/**
 * Faculty dashboard (S10) renderable — teal-accented teacher home.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class faculty_dashboard implements named_templatable, renderable {
    /** @var int teacher user id. */
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
        return 'local_azmsi/faculty_dashboard';
    }

    /**
     * Compose the template context from the shared faculty overview.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = faculty::overview_for($this->userid);

        $courses = array_map(static function ($c) {
            $c['hasungraded'] = $c['ungraded'] > 0;
            return $c;
        }, $data['courses']);

        return [
            'coursecount'  => $data['coursecount'],
            'studenttotal' => $data['studenttotal'],
            'queuetotal'   => $data['queuetotal'],
            'ontracktotal' => $data['ontracktotal'],
            'atrisktotal'  => $data['atrisktotal'],
            'courses'      => $courses,
            'hascourses'   => !empty($courses),
            'agenda'       => $data['agenda'],
            'hasagenda'    => !empty($data['agenda']),
        ];
    }
}
