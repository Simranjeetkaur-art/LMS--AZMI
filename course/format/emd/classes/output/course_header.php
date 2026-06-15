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

namespace format_emd\output;

use core\output\named_templatable;
use renderable;
use renderer_base;
use core_course\customfield\course_handler;

/**
 * eMD course-home header (03_SCREEN_SPECS S5): title, code, credits, faculty,
 * live course % (completion) and current grade (gradebook).
 *
 * Every value is read from a live API — nothing hardcoded. Rendered at the top
 * of the course content via format_emd::course_content_header().
 *
 * @package    format_emd
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_header implements named_templatable, renderable {
    /** @var \stdClass the course record. */
    protected $course;

    /**
     * Constructor.
     *
     * @param \stdClass $course the course being viewed
     */
    public function __construct(\stdClass $course) {
        $this->course = $course;
    }

    /**
     * Template name.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_emd/course_header';
    }

    /**
     * Compose the header context from live Moodle data.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER, $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        // Course custom fields (code/credits/faculty) — read live, never hardcoded.
        $fields = [];
        foreach (course_handler::create()->get_instance_data($this->course->id, true) as $data) {
            $fields[$data->get_field()->get('shortname')] = $data->export_value();
        }

        // Live completion percentage for the viewing user.
        $percentage = \core_completion\progress::get_course_progress_percentage($this->course, $USER->id);
        $hasprogress = !is_null($percentage);
        $progress = $hasprogress ? (int) round($percentage) : 0;

        // Current course grade for the viewing user (formatted with %).
        $gradedisplay = null;
        if ($item = \grade_item::fetch_course_item($this->course->id)) {
            $grade = new \grade_grade(['itemid' => $item->id, 'userid' => $USER->id], true);
            if (!is_null($grade->finalgrade)) {
                $gradedisplay = grade_format_gradevalue($grade->finalgrade, $item, true, GRADE_DISPLAY_TYPE_PERCENTAGE);
            }
        }

        return [
            'title'       => format_string($this->course->fullname, true, ['escape' => false]),
            'code'        => (string) ($fields['course_code'] ?? $this->course->idnumber),
            'credits'     => isset($fields['credits']) ? (int) $fields['credits'] : null,
            'faculty'     => (string) ($fields['faculty_name'] ?? ''),
            'hasprogress' => $hasprogress,
            'progress'    => $progress,
            'hasgrade'    => !is_null($gradedisplay),
            'grade'       => $gradedisplay,
        ];
    }
}
