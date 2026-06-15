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

use core_courseformat\output\section_renderer;

/**
 * Renderer for the eMD course format.
 *
 * Mirrors the core numbered-format renderers (like weeks); core handles the
 * reactive section/activity rendering so completion, grades and availability
 * stay native.
 *
 * @package    format_emd
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends section_renderer {
    /**
     * The section title with a link to the section page (multi-page display).
     *
     * @param \stdClass $section the course_section entry
     * @param \stdClass $course the course entry
     * @return string HTML
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * The section title without a link (single section page).
     *
     * @param \stdClass $section the course_section entry
     * @param \stdClass $course the course entry
     * @return string HTML
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }
}
