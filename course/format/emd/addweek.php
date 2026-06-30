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
 * Add a new weekly module (a section pre-populated with the master activity set).
 *
 * @package    format_emd
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Symlink-safe config include: the plugin is symlinked into public/, so the
// conventional ../../../config.php (resolved through the symlink) misses it.
require(is_file(__DIR__ . '/../../../config.php')
    ? __DIR__ . '/../../../config.php'
    : (getenv('MOODLE_ROOT') ? rtrim(getenv('MOODLE_ROOT'), '/') . '/config.php' : '/var/www/moodle/config.php'));
require_once($CFG->dirroot . '/course/lib.php');

use format_emd\local\master_template;

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
require_login($course);
require_sesskey();

$context = context_course::instance($course->id);
require_capability('moodle/course:update', $context);
require_capability('moodle/course:manageactivities', $context);

if ($course->format !== 'emd') {
    throw new moodle_exception('notemdcourse', 'format_emd');
}

// Respect the per-course week cap — redirect with a notice rather than erroring.
if (!master_template::can_add_week($course)) {
    redirect(
        course_get_url($course),
        get_string('weekcapreached', 'format_emd', master_template::get_max_weeks($course)),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

$section = master_template::add_week($course);

redirect(
    course_get_url($course, $section->section),
    get_string('weekadded', 'format_emd'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
