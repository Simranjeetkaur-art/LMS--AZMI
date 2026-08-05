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
 * Diff review for one week's findings.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_contentchecker\local\content_source;
use local_contentchecker\local\dashboard;

$courseid = required_param('courseid', PARAM_INT);
$sectionnum = required_param('sectionnum', PARAM_INT);
$showall = optional_param('showall', 0, PARAM_BOOL);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('local/contentchecker:view', $context);

$url = new moodle_url('/local/contentchecker/week.php',
    ['courseid' => $course->id, 'sectionnum' => $sectionnum]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('review:heading', 'local_contentchecker'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('coursedashboard', 'local_contentchecker'),
    new moodle_url('/local/contentchecker/index.php', ['courseid' => $course->id]));
$PAGE->navbar->add(get_string('review:heading', 'local_contentchecker'));

$canapprove = has_capability('local/contentchecker:approve', $context);
if ($canapprove) {
    $PAGE->requires->js_call_amd('local_contentchecker/diff_review', 'init');
}

// Findings for this week come from whichever activities the week actually
// holds now, so an activity moved to another section stops appearing here.
$cmids = array_values(array_unique(array_map(
    fn($item) => $item->cmid,
    content_source::for_section((int) $course->id, $sectionnum))));

$suggestions = [];
if ($cmids) {
    [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cm');
    $params['courseid'] = $course->id;

    $where = "ch.courseid = :courseid AND s.cmid {$insql}";
    if (!$showall) {
        [$vsql, $vparams] = $DB->get_in_or_equal(
            \local_contentchecker\local\pipeline::FLAGGED, SQL_PARAMS_NAMED, 'v');
        $where .= " AND s.verdict {$vsql}";
        $params += $vparams;
    }

    // Worst first, and undecided before decided, so the reviewer meets the
    // outstanding contradictions before anything already dealt with.
    $suggestions = $DB->get_records_sql(
        "SELECT s.*
           FROM {local_cchecker_suggestions} s
           JOIN {local_cchecker_checks} ch ON ch.id = s.checkid
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
        [$ssql, $sparams] = $DB->get_in_or_equal(array_keys($suggestions),
            SQL_PARAMS_NAMED, 'sg');
        $evidence = $DB->get_records_select('local_cchecker_evidence',
            "suggestionid {$ssql}", $sparams, 'score DESC');
        foreach ($suggestions as $suggestion) {
            $suggestion->evidence = array_values(array_filter($evidence,
                fn($e) => (int) $e->suggestionid === (int) $suggestion->id));
        }
    }
}

$renderer = $PAGE->get_renderer('local_contentchecker');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('review:heading', 'local_contentchecker'));

$toggle = new moodle_url($url, ['showall' => $showall ? 0 : 1]);
echo html_writer::div(
    html_writer::link($toggle, $showall
        ? get_string('review:showflagged', 'local_contentchecker')
        : get_string('review:showall', 'local_contentchecker'),
        ['class' => 'btn btn-sm btn-outline-secondary']),
    'mb-3');

echo $renderer->warnings();
echo $renderer->suggestion_list(array_values($suggestions), $canapprove);

echo $OUTPUT->footer();
