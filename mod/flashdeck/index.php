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
 * List all flashdeck instances in a course.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);

$course = get_course($id);
require_course_login($course);

$context = context_course::instance($course->id);

$PAGE->set_url('/mod/flashdeck/index.php', ['id' => $course->id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_flashdeck'));

$modinfo = get_fast_modinfo($course);
$strsectionname = get_string('sectionname', 'format_' . $course->format);

$table = new html_table();
$table->head = [$strsectionname, get_string('name')];
$table->align = ['left', 'left'];

$found = false;
foreach ($modinfo->get_instances_of('flashdeck') as $cm) {
    if (!$cm->uservisible) {
        continue;
    }
    $found = true;
    $link = html_writer::link($cm->url, format_string($cm->name),
        $cm->visible ? [] : ['class' => 'dimmed']);
    $table->data[] = [get_section_name($course, $cm->sectionnum), $link];
}

if (!$found) {
    notice(get_string('nodecksincourse', 'mod_flashdeck'),
        new moodle_url('/course/view.php', ['id' => $course->id]));
}

echo html_writer::table($table);
echo $OUTPUT->footer();
