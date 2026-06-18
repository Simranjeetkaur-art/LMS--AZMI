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
use local_azmsi\local\instructor;

/**
 * Instructor course view (S11) renderable.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instructor_course implements named_templatable, renderable {
    /** @var int course id. */
    protected $courseid;

    /**
     * Constructor.
     *
     * @param int $courseid
     */
    public function __construct(int $courseid) {
        $this->courseid = $courseid;
    }

    /**
     * Template name.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'local_azmsi/instructor_course';
    }

    /**
     * Compose the instructor-course template context.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = instructor::for_course($this->courseid);
        return [
            'coursename'      => $data['coursename'],
            'coursecode'      => $data['coursecode'],
            'submissions'     => $data['submissions'],
            'hassubmissions'  => !empty($data['submissions']),
            'submissioncount' => count($data['submissions']),
            'atrisk'          => $data['atrisk'],
            'hasatrisk'       => !empty($data['atrisk']),
            'roster'          => $data['roster'],
            'hasroster'       => !empty($data['roster']),
            'rostercount'     => count($data['roster']),
            'rubric'          => $data['rubric'],
        ];
    }
}
