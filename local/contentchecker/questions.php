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
 * Review, edit and approve auto-generated follow-up questions.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_contentchecker\local\content_source;

$courseid = required_param('courseid', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('local/contentchecker:manage', $context);

$url = new moodle_url('/local/contentchecker/questions.php',
    ['courseid' => $course->id] + ($cmid ? ['cmid' => $cmid] : []));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('questions:manage', 'local_contentchecker'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('coursedashboard', 'local_contentchecker'),
    new moodle_url('/local/contentchecker/index.php', ['courseid' => $course->id]));
$PAGE->navbar->add(get_string('questions:manage', 'local_contentchecker'));

$PAGE->requires->js_call_amd('local_contentchecker/question_review', 'init', [[
    'courseid' => (int) $course->id,
]]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('questions:manage', 'local_contentchecker'));

if (!get_config('local_contentchecker', 'questions_enabled')) {
    echo $OUTPUT->notification(get_string('error:questionsdisabled', 'local_contentchecker'),
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', get_string('questions:intro', 'local_contentchecker'),
    ['class' => 'text-muted']);

// Activity picker.
$activities = [0 => get_string('questions:allactivities', 'local_contentchecker')];
foreach (content_source::for_course((int) $course->id) as $item) {
    $activities[$item->cmid] = get_string('enrich:target', 'local_contentchecker', (object) [
        'section' => $item->sectionnum,
        'name' => $item->name,
    ]);
}

echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'form-inline mb-3']);
echo html_writer::empty_tag('input',
    ['type' => 'hidden', 'name' => 'courseid', 'value' => $course->id]);
echo html_writer::select($activities, 'cmid', $cmid, false,
    ['class' => 'custom-select mr-2',
     'aria-label' => get_string('questions:activity', 'local_contentchecker')]);
echo html_writer::tag('button', get_string('questions:filter', 'local_contentchecker'),
    ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

if ($cmid) {
    echo html_writer::div(
        html_writer::tag('button',
            get_string('questions:generate', 'local_contentchecker'), [
                'type' => 'button',
                'class' => 'btn btn-primary',
                'data-cct-generate' => $cmid,
            ]) . ' ' .
        html_writer::tag('button',
            get_string('questions:regenerate', 'local_contentchecker'), [
                'type' => 'button',
                'class' => 'btn btn-outline-primary',
                'data-cct-generate' => $cmid,
                'data-cct-replace' => '1',
            ]),
        'mb-3');
}

$conditions = ['courseid' => $course->id];
if ($cmid) {
    $conditions['cmid'] = $cmid;
}
$questions = $DB->get_records('local_cchecker_questions', $conditions,
    'cmid, blockref, sortorder, id');

if (!$questions) {
    echo $OUTPUT->notification(get_string('questions:none', 'local_contentchecker'),
        \core\output\notification::NOTIFY_INFO);
} else {
    $renderer = $PAGE->get_renderer('local_contentchecker');
    echo $renderer->question_list($questions);
}

echo $OUTPUT->footer();
