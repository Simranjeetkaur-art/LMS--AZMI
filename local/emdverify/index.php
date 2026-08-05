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
 * Verification overview for a course: one row per week.
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_emdverify\local\corpus;
use local_emdverify\local\ledger;

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('local/emdverify:view', $context);

$url = new moodle_url('/local/emdverify/index.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('courseoverview', 'local_emdverify'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

$canrun = has_capability('local/emdverify:run', $context);
$summary = ledger::course_summary($course->id);
$modinfo = get_fast_modinfo($course);

$weeks = [];
foreach ($modinfo->get_section_info_all() as $section) {
    if ($section->section === 0) {
        continue; // General section carries no weekly content.
    }
    $run = $summary[$section->section] ?? null;
    $name = $section->name ?: get_string('week', 'local_emdverify', $section->section);

    $weeks[] = [
        'sectionnum' => $section->section,
        'name' => format_string($name),
        'hasrun' => (bool) $run,
        'status' => $run->status ?? '',
        'running' => $run && in_array($run->status, ['queued', 'running'], true),
        'failed' => $run && $run->status === 'failed',
        'progress' => (int) ($run->progress ?? 0),
        'numclaims' => (int) ($run->numclaims ?? 0),
        'numflagged' => (int) ($run->numflagged ?? 0),
        'clean' => $run && $run->status === 'complete' && (int) $run->numflagged === 0,
        'lastverified' => !empty($run->timefinished)
            ? userdate($run->timefinished, get_string('strftimedatetimeshort'))
            : '',
        'ledgerurl' => (new moodle_url('/local/emdverify/week.php',
            ['courseid' => $course->id, 'sectionnum' => $section->section]))->out(false),
    ];
}

[$numsources, $numchunks] = corpus::status();

$data = [
    'courseid' => $course->id,
    'coursename' => format_string($course->fullname),
    'canrun' => $canrun,
    'weeks' => $weeks,
    'hasweeks' => (bool) $weeks,
    'sesskey' => sesskey(),
    'corpusstatus' => get_string('corpusstatus', 'local_emdverify',
        (object) ['sources' => $numsources, 'chunks' => $numchunks]),
    'tier3only' => corpus::is_tier3_only(),
    'tierwarning' => get_string('tierwarning', 'local_emdverify'),
    'accuracy' => ledger::accuracy($course->id),
];
$data['hasaccuracy'] = (bool) $data['accuracy'];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_emdverify/overview', $data);
echo $OUTPUT->footer();
