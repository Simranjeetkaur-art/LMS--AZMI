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
 * Per-course content checker dashboard.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This plugin is symlinked into the Moodle tree, so __DIR__ resolves to the
// real path outside it and the conventional relative require misses config.php
// entirely. Fall back the same way the other AZMSI plugins do.
require(is_file(__DIR__ . '/../../../config.php')
    ? __DIR__ . '/../../../config.php'
    : (getenv('MOODLE_ROOT')
        ? rtrim(getenv('MOODLE_ROOT'), '/') . '/config.php'
        : '/var/www/moodle/config.php'));

use local_contentchecker\local\dashboard;

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('local/contentchecker:view', $context);

$url = new moodle_url('/local/contentchecker/index.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coursedashboard', 'local_contentchecker'));
$PAGE->set_heading($course->fullname);

$canrun = has_capability('local/contentchecker:manage', $context);
$canjob = has_capability('local/contentchecker:runbackground', $context);

if ($canrun) {
    $PAGE->requires->js_call_amd('local_contentchecker/verification_dashboard', 'init', [[
        'courseid' => (int) $course->id,
    ]]);
}

$renderer = $PAGE->get_renderer('local_contentchecker');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursedashboard', 'local_contentchecker'));

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/contentchecker/enrich.php', ['courseid' => $course->id]),
        get_string('enrich:heading', 'local_contentchecker'),
        ['class' => 'btn btn-outline-secondary btn-sm']) . ' ' .
    html_writer::link(
        new moodle_url('/local/contentchecker/questions.php', ['courseid' => $course->id]),
        get_string('questions:manage', 'local_contentchecker'),
        ['class' => 'btn btn-outline-secondary btn-sm']) . ' ' .
    html_writer::link(
        new moodle_url('/local/contentchecker/audit.php', ['courseid' => $course->id]),
        get_string('audit:heading', 'local_contentchecker'),
        ['class' => 'btn btn-outline-secondary btn-sm']),
    'mb-3');

echo $renderer->course_dashboard($course, dashboard::for_course((int) $course->id),
    $canrun, $canjob);

echo $OUTPUT->footer();
