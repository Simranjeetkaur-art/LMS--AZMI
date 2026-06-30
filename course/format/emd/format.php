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

/**
 * Renders the eMD course format home page.
 *
 * Required (via course/view.php) for the course landing page. Section/activity
 * rendering, completion and availability all stay native — this just dispatches
 * to the reactive content output class, like the core topics/weeks formats.
 *
 * Variables available from course/view.php: $course, $context, $PAGE, $USER,
 * $CFG, $DB, $OUTPUT, $section, $displaysection, $marker.
 *
 * @package    format_emd
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');

// Retrieve course format option fields and add them to the $course object.
$format = course_get_format($course);
$course = $format->get_course();
$context = context_course::instance($course->id);

// Allow setting the "current" section marker (same behaviour as topics).
if (($marker >= 0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    $course->marker = $marker;
    course_set_marker($course->id, $marker);
}

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);

$renderer = $PAGE->get_renderer('format_emd');

// The rich course-preview page (S5) is what everyone sees on the course home with
// Edit mode OFF. Turning Edit mode ON (only available to teachers/managers) reveals
// the normal Moodle course with the editing UI; students never can, so they always
// get the preview. Section pages always use the native content.
if (!$PAGE->user_is_editing() && is_null($displaysection)) {
    echo $renderer->render(new \format_emd\output\coursepreview($course));
} else {
    if (!is_null($displaysection)) {
        $format->set_sectionnum($displaysection);
    }
    $outputclass = $format->get_output_classname('content');
    $widget = new $outputclass($format);
    echo $renderer->render($widget);
}
