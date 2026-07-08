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
 * Teacher analytics: per-learner study state for a deck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT); // Course module id.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'flashdeck');
$deck = $DB->get_record('flashdeck', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/flashdeck:viewreports', $context);

$PAGE->set_url('/mod/flashdeck/report.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . format_string($deck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($deck);

$totalcards = $DB->count_records('flashdeck_cards', ['deckid' => $deck->id]);
$now = time();

$userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', true)->selects;
$sql = "SELECT u.id{$userfields},
               COUNT(r.id) AS studied,
               SUM(CASE WHEN r.state = 'review' THEN 1 ELSE 0 END) AS graduated,
               SUM(CASE WHEN r.duedate <= :now THEN 1 ELSE 0 END) AS duenow,
               SUM(r.lapses) AS lapses,
               MAX(r.lastreviewed) AS lastreviewed
          FROM {user} u
          JOIN {flashdeck_review} r ON r.userid = u.id
         WHERE r.deckid = :deckid
      GROUP BY u.id{$userfields}
      ORDER BY MAX(r.lastreviewed) DESC";
$learners = $DB->get_records_sql($sql, ['deckid' => $deck->id, 'now' => $now]);

$points = $DB->get_records_sql_menu(
    'SELECT userid, SUM(points) FROM {flashdeck_session} WHERE deckid = ? GROUP BY userid', [$deck->id]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report', 'mod_flashdeck'), 3);
echo html_writer::tag('p', get_string('reportsummary', 'mod_flashdeck', (object) [
    'cards' => $totalcards,
    'learners' => count($learners),
]));

if ($learners) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable flashdeck-reporttable';
    $table->head = [
        get_string('fullname'),
        get_string('reportstudied', 'mod_flashdeck'),
        get_string('reportgraduated', 'mod_flashdeck'),
        get_string('reportmastery', 'mod_flashdeck'),
        get_string('reportduenow', 'mod_flashdeck'),
        get_string('reportlapses', 'mod_flashdeck'),
        get_string('statpoints', 'mod_flashdeck'),
        get_string('reportlastactivity', 'mod_flashdeck'),
    ];
    foreach ($learners as $learner) {
        $mastery = $totalcards ? round(100 * $learner->graduated / $totalcards) : 0;
        $table->data[] = [
            html_writer::link(new moodle_url('/user/view.php',
                ['id' => $learner->id, 'course' => $course->id]), fullname($learner)),
            (int) $learner->studied,
            (int) $learner->graduated,
            $mastery . '%',
            (int) $learner->duenow,
            (int) $learner->lapses,
            (int) ($points[$learner->id] ?? 0),
            userdate($learner->lastreviewed, get_string('strftimedatetimeshort', 'langconfig')),
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('reportnodata', 'mod_flashdeck'), 'info');
}

echo html_writer::div(
    html_writer::link(new moodle_url('/mod/flashdeck/view.php', ['id' => $cm->id]),
        get_string('studymodelearn', 'mod_flashdeck'), ['class' => 'btn btn-outline-secondary']),
    'flashdeck-managelink');
echo $OUTPUT->footer();
