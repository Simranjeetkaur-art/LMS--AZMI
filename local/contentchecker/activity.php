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
 * One activity's content, with its findings underneath.
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

use local_contentchecker\local\content_source;
use local_contentchecker\local\pipeline;

$cmid = required_param('cmid', PARAM_INT);
$showall = optional_param('showall', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($cmid);
require_login($course, false, $cm);

$context = context_course::instance($course->id);
require_capability('local/contentchecker:view', $context);

$url = new moodle_url('/local/contentchecker/activity.php', ['cmid' => $cmid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(format_string($cm->name));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('coursedashboard', 'local_contentchecker'),
    new moodle_url('/local/contentchecker/index.php', ['courseid' => $course->id]));
$PAGE->navbar->add(get_string('review:heading', 'local_contentchecker'),
    new moodle_url('/local/contentchecker/week.php',
        ['courseid' => $course->id, 'sectionnum' => $cm->sectionnum]));
$PAGE->navbar->add(format_string($cm->name));

$canrun = has_capability('local/contentchecker:manage', $context);
$canapprove = has_capability('local/contentchecker:approve', $context);

if ($canrun) {
    $PAGE->requires->js_call_amd('local_contentchecker/verification_dashboard', 'init', [[
        'courseid' => (int) $course->id,
    ]]);
}
if ($canapprove) {
    $PAGE->requires->js_call_amd('local_contentchecker/diff_review', 'init');
}
if ($canrun) {
    $PAGE->requires->js_call_amd('local_contentchecker/enrich_suggest', 'init', [[
        'cmid' => $cmid,
    ]]);
}

$items = content_source::for_cm($cmid);

// Findings for this activity, worst and undecided first.
$params = ['cmid' => $cmid];
$where = 's.cmid = :cmid';
if (!$showall) {
    [$vsql, $vparams] = $DB->get_in_or_equal(pipeline::FLAGGED, SQL_PARAMS_NAMED, 'v');
    $where .= " AND s.verdict {$vsql}";
    $params += $vparams;
}

$suggestions = $DB->get_records_sql(
    "SELECT s.*
       FROM {local_cchecker_suggestions} s
      WHERE {$where}
   ORDER BY CASE s.decision WHEN 'pending' THEN 0 ELSE 1 END,
            CASE s.verdict
                 WHEN 'contradicted' THEN 0
                 WHEN 'contested' THEN 1
                 WHEN 'outdated' THEN 2
                 WHEN 'partially_supported' THEN 3
                 ELSE 4 END,
            s.id DESC", $params);

if ($suggestions) {
    [$ssql, $sparams] = $DB->get_in_or_equal(array_keys($suggestions), SQL_PARAMS_NAMED, 'sg');
    $evidence = $DB->get_records_select('local_cchecker_evidence',
        "suggestionid {$ssql}", $sparams, 'score DESC');
    foreach ($suggestions as $suggestion) {
        $suggestion->evidence = array_values(array_filter($evidence,
            fn($e) => (int) $e->suggestionid === (int) $suggestion->id));
    }
}

$renderer = $PAGE->get_renderer('local_contentchecker');

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($cm->name));

// --- actions --------------------------------------------------------------
$buttons = '';
if ($canrun && $items) {
    $buttons .= html_writer::tag('button',
        get_string('activity:verify', 'local_contentchecker'), [
            'type' => 'button',
            'class' => 'btn btn-primary',
            'data-cct-verify' => '1',
            'data-cct-section' => (int) $cm->sectionnum,
            'data-cct-cmid' => $cmid,
        ]) . ' ';
}
$buttons .= html_writer::link(
    new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cmid]),
    get_string('activity:open', 'local_contentchecker'),
    ['class' => 'btn btn-outline-secondary', 'target' => '_blank', 'rel' => 'noopener']);
$buttons .= ' ' . html_writer::link(
    new moodle_url('/local/contentchecker/week.php',
        ['courseid' => $course->id, 'sectionnum' => (int) $cm->sectionnum]),
    get_string('activity:backtoweek', 'local_contentchecker'),
    ['class' => 'btn btn-outline-secondary']);

echo html_writer::div($buttons, 'mb-3',
    ['data-cct-row' => $cmid]);

// --- the content itself ---------------------------------------------------
echo $OUTPUT->heading(get_string('activity:content', 'local_contentchecker'), 3);

if (!$items) {
    echo $OUTPUT->notification(
        get_string('activity:nocontent', 'local_contentchecker'),
        \core\output\notification::NOTIFY_INFO);
} else {
    echo $renderer->activity_content($items);
}

// --- content-aware illustration suggestions --------------------------------
if ($canrun && $items) {
    echo $OUTPUT->heading(get_string('suggest:heading', 'local_contentchecker'), 3);
    echo html_writer::tag('p', get_string('suggest:intro', 'local_contentchecker'),
        ['class' => 'text-muted']);
    echo html_writer::div(
        html_writer::tag('button',
            get_string('suggest:button', 'local_contentchecker'), [
                'type' => 'button',
                'class' => 'btn btn-primary',
                'data-cct-suggest' => '1',
            ]),
        'mb-3');
    echo html_writer::div('', 'cct-suggestions', ['data-cct-suggestions' => '1']);
}

// --- its findings ---------------------------------------------------------
echo $OUTPUT->heading(get_string('review:heading', 'local_contentchecker'), 3);

$toggle = new moodle_url($url, ['showall' => $showall ? 0 : 1]);
echo html_writer::div(
    html_writer::link($toggle, $showall
        ? get_string('review:showflagged', 'local_contentchecker')
        : get_string('review:showall', 'local_contentchecker'),
        ['class' => 'btn btn-sm btn-outline-secondary']),
    'mb-3');

echo $renderer->suggestion_list(array_values($suggestions), $canapprove);

echo $OUTPUT->footer();
