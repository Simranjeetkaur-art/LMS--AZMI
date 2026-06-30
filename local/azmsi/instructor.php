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
 * AZMSI instructor course view (S11).
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(is_file(__DIR__ . '/../../../config.php')
    ? __DIR__ . '/../../../config.php'
    : (getenv('MOODLE_ROOT') ? rtrim(getenv('MOODLE_ROOT'), '/') . '/config.php' : '/var/www/moodle/config.php'));

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('local/azmsi:viewfacultyportal', context_system::instance());
require_once($CFG->dirroot . '/local/azmsi/classes/local/faculty.php');
if (!\local_azmsi\local\faculty::teaches_course($USER->id, $courseid)) {
    throw new \moodle_exception('cannotaccess', 'error');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/azmsi/instructor.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('instructorcourse', 'local_azmsi'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('azmsi-faculty'); // Teal accent variant.

$output = $PAGE->get_renderer('local_azmsi');
echo $output->header();
echo $output->render(new \local_azmsi\output\instructor_course((int) $courseid));
echo $output->footer();
