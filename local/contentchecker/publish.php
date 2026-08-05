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
 * Publish layout picker with live preview.
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

use local_contentchecker\local\templates;

$courseid = required_param('courseid', PARAM_INT);
$sectionnum = required_param('sectionnum', PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('local/contentchecker:manage', $context);

$section = $DB->get_record('course_sections',
    ['course' => $course->id, 'section' => $sectionnum], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/local/contentchecker/publish.php',
    ['courseid' => $course->id, 'sectionnum' => $sectionnum]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('publish:heading', 'local_contentchecker'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('coursedashboard', 'local_contentchecker'),
    new moodle_url('/local/contentchecker/index.php', ['courseid' => $course->id]));
$PAGE->navbar->add(get_string('publish:heading', 'local_contentchecker'));

$PAGE->requires->js_call_amd('local_contentchecker/publish_preview', 'init', [[
    'courseid' => (int) $course->id,
    'sectionid' => (int) $section->id,
    'cmid' => 0,
]]);

$stored = templates::get((int) $section->id, 0);
$config = $stored ? (json_decode($stored->config, true) ?: []) : [];
$chosen = $stored ? $stored->templatekey : 'medialeft';

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('publish:heading', 'local_contentchecker'));
echo html_writer::tag('p', get_string('publish:intro', 'local_contentchecker'),
    ['class' => 'text-muted']);

echo html_writer::start_div('row');

// --- The picker -----------------------------------------------------------
echo html_writer::start_div('col-12 col-lg-5');
echo html_writer::start_tag('form', ['data-cct-publish-form' => '1',
    'onsubmit' => 'return false;']);

echo html_writer::start_div('form-group');
echo html_writer::tag('label', get_string('publish:layout', 'local_contentchecker'),
    ['for' => 'cct-template']);
echo html_writer::select(templates::menu(), 'templatekey', $chosen, false, [
    'id' => 'cct-template',
    'class' => 'custom-select',
    'data-cct-template' => '1',
]);
echo html_writer::end_div();

// Every slot any layout can use is rendered; the server drops the ones the
// selected layout does not declare, so switching layout keeps whatever the
// editor already typed into a shared slot such as "body".
$slots = [
    'media' => 'publish:slot:media',
    'body' => 'publish:slot:body',
    'intro' => 'publish:slot:intro',
    'sidebar' => 'publish:slot:sidebar',
];
foreach ($slots as $slot => $stringkey) {
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', get_string($stringkey, 'local_contentchecker'),
        ['for' => 'cct-slot-' . $slot]);
    echo html_writer::tag('textarea', s((string) ($config[$slot] ?? '')), [
        'id' => 'cct-slot-' . $slot,
        'class' => 'form-control',
        'rows' => 4,
        'data-cct-slot' => $slot,
    ]);
    echo html_writer::end_div();
}

// Repeating slots: three rows is enough for a week's key points or a
// three-way comparison, and more can be added by editing the stored config.
foreach (['callouts' => 'publish:slot:callouts', 'tabs' => 'publish:slot:tabs'] as $slot => $key) {
    echo html_writer::tag('h4', get_string($key, 'local_contentchecker'), ['class' => 'h6 mt-3']);
    $entries = is_array($config[$slot] ?? null) ? $config[$slot] : [];
    for ($i = 0; $i < 3; $i++) {
        echo html_writer::start_div('border rounded p-2 mb-2', ['data-cct-repeat' => $slot]);
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'class' => 'form-control mb-1',
            'data-cct-field' => 'title',
            'value' => s((string) ($entries[$i]['title'] ?? '')),
            'placeholder' => get_string('publish:entrytitle', 'local_contentchecker'),
            'aria-label' => get_string('publish:entrytitle', 'local_contentchecker'),
        ]);
        echo html_writer::tag('textarea', s((string) ($entries[$i]['body'] ?? '')), [
            'class' => 'form-control',
            'rows' => 2,
            'data-cct-field' => 'body',
            'placeholder' => get_string('publish:entrybody', 'local_contentchecker'),
            'aria-label' => get_string('publish:entrybody', 'local_contentchecker'),
        ]);
        echo html_writer::end_div();
    }
}

echo html_writer::tag('button', get_string('publish:save', 'local_contentchecker'), [
    'type' => 'button',
    'class' => 'btn btn-primary',
    'data-cct-save' => '1',
    'data-cct-saved-message' => get_string('publish:saved', 'local_contentchecker'),
]);

echo html_writer::end_tag('form');
echo html_writer::end_div();

// --- The preview ----------------------------------------------------------
echo html_writer::start_div('col-12 col-lg-7');
echo html_writer::tag('h3', get_string('publish:preview', 'local_contentchecker'),
    ['class' => 'h5']);
echo html_writer::div('', 'cct-preview border rounded p-3', ['data-cct-preview' => '1']);
echo html_writer::end_div();

echo html_writer::end_div();
echo $OUTPUT->footer();
