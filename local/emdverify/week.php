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
 * The verification ledger for one week: claims, evidence and sign-off.
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_emdverify\local\ai_client;
use local_emdverify\local\corpus;
use local_emdverify\local\ledger;
use local_emdverify\task\run_verification;

$courseid = required_param('courseid', PARAM_INT);
$sectionnum = required_param('sectionnum', PARAM_INT);
$filter = optional_param('filter', 'flagged', PARAM_ALPHA);
$queue = optional_param('queue', 0, PARAM_BOOL);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('local/emdverify:view', $context);

$url = new moodle_url('/local/emdverify/week.php',
    ['courseid' => $course->id, 'sectionnum' => $sectionnum, 'filter' => $filter]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

$modinfo = get_fast_modinfo($course);
$section = $modinfo->get_section_info($sectionnum);
$weekname = $section && $section->name
    ? format_string($section->name)
    : get_string('week', 'local_emdverify', $sectionnum);
$PAGE->set_title(get_string('ledgerfor', 'local_emdverify', $weekname));

// Queueing is a state change, so require sesskey and redirect afterwards.
if ($queue) {
    require_sesskey();
    require_capability('local/emdverify:run', $context);

    if (!(new ai_client())->healthy()) {
        redirect($url, get_string('error:serverdown', 'local_emdverify'), null,
            \core\output\notification::NOTIFY_ERROR);
    }
    [$numsources] = corpus::status();
    if (!$numsources) {
        redirect($url, get_string('error:nocorpus', 'local_emdverify'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    run_verification::queue($course->id, $sectionnum, $USER->id);
    redirect($url, get_string('runqueued', 'local_emdverify'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$run = ledger::latest_run($course->id, $sectionnum);
$claims = [];
$counts = ['total' => 0, 'flagged' => 0, 'reviewed' => 0];

if ($run && $run->status === 'complete') {
    $counts['total'] = (int) $run->numclaims;
    $counts['flagged'] = (int) $run->numflagged;

    foreach (ledger::claims($run->id, $USER->id, $filter) as $claim) {
        if (!empty($claim->decision)) {
            $counts['reviewed']++;
        }
        $claims[] = [
            'id' => $claim->id,
            'text' => $claim->claimtext,
            'pagename' => $claim->pagename,
            'pageurl' => (new moodle_url('/mod/page/view.php', ['id' => $claim->cmid]))->out(false),
            'verdict' => $claim->verdict,
            'verdictlabel' => get_string('verdict:' . $claim->verdict, 'local_emdverify'),
            'flagged' => in_array($claim->verdict, ledger::FLAGGED, true),
            'contested' => $claim->verdict === 'contested',
            'quorum' => $claim->quorum,
            'topscore' => number_format((float) $claim->topscore, 3),
            'quote' => $claim->quote,
            'hasquote' => !empty($claim->quote),
            'quoteverbatim' => (bool) $claim->quoteverbatim,
            'reasoning' => $claim->reasoning,
            'correction' => $claim->correction,
            'hascorrection' => !empty($claim->correction),
            'confidence' => number_format((float) $claim->confidence, 2),
            'evidence' => array_map(fn($e) => [
                'title' => $e->title,
                'url' => $e->url,
                'tier' => $e->tier,
                'score' => number_format((float) $e->score, 3),
                'snippet' => $e->snippet,
            ], $claim->evidence),
            'hasevidence' => (bool) $claim->evidence,
            'decision' => $claim->decision,
            'reviewed' => !empty($claim->decision),
        ];
    }
}

$mkfilter = fn($f) => (new moodle_url('/local/emdverify/week.php',
    ['courseid' => $course->id, 'sectionnum' => $sectionnum, 'filter' => $f]))->out(false);

$data = [
    'courseid' => $course->id,
    'sectionnum' => $sectionnum,
    'weekname' => $weekname,
    'backurl' => (new moodle_url('/local/emdverify/index.php',
        ['courseid' => $course->id]))->out(false),
    'canrun' => has_capability('local/emdverify:run', $context),
    'canadjudicate' => has_capability('local/emdverify:adjudicate', $context),
    'queueurl' => (new moodle_url('/local/emdverify/week.php',
        ['courseid' => $course->id, 'sectionnum' => $sectionnum,
         'queue' => 1, 'sesskey' => sesskey()]))->out(false),
    'hasrun' => (bool) $run,
    'runid' => $run->id ?? 0,
    'running' => $run && in_array($run->status, ['queued', 'running'], true),
    'failed' => $run && $run->status === 'failed',
    'errormsg' => $run->errormsg ?? '',
    'progress' => (int) ($run->progress ?? 0),
    'complete' => $run && $run->status === 'complete',
    'claims' => $claims,
    'hasclaims' => (bool) $claims,
    'summary' => get_string('summary', 'local_emdverify', (object) $counts),
    'tier3only' => corpus::is_tier3_only(),
    'tierwarning' => get_string('tierwarning', 'local_emdverify'),
    'filters' => [
        ['label' => get_string('filterflagged', 'local_emdverify'),
         'url' => $mkfilter('flagged'), 'active' => $filter === 'flagged'],
        ['label' => get_string('filterunreviewed', 'local_emdverify'),
         'url' => $mkfilter('unreviewed'), 'active' => $filter === 'unreviewed'],
        ['label' => get_string('filterall', 'local_emdverify'),
         'url' => $mkfilter('all'), 'active' => $filter === 'all'],
    ],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_emdverify/ledger', $data);
echo $OUTPUT->footer();
