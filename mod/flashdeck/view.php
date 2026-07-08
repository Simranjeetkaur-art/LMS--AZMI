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
 * Student study view of a flashdeck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$id = optional_param('id', 0, PARAM_INT);   // Course module id.
$f = optional_param('f', 0, PARAM_INT);     // Flashdeck instance id.

if ($f) {
    $deck = $DB->get_record('flashdeck', ['id' => $f], '*', MUST_EXIST);
    [$course, $cm] = get_course_and_cm_from_instance($deck, 'flashdeck');
} else {
    [$course, $cm] = get_course_and_cm_from_cmid($id, 'flashdeck');
    $deck = $DB->get_record('flashdeck', ['id' => $cm->instance], '*', MUST_EXIST);
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/flashdeck:view', $context);

$mode = optional_param('mode', 'learn', PARAM_ALPHA);
$canstudy = has_capability('mod/flashdeck:study', $context);

// No-JS fallback for the four-button grade: a plain form post, handled
// through the same api the AJAX loop uses, then redirect (PRG).
$grade = optional_param('grade', null, PARAM_INT);
$gradecardid = optional_param('cardid', 0, PARAM_INT);
if ($grade !== null && $gradecardid && data_submitted()) {
    require_sesskey();
    require_capability('mod/flashdeck:study', $context);
    if ($grade < \mod_flashdeck\scheduler\scheduler::GRADE_AGAIN
            || $grade > \mod_flashdeck\scheduler\scheduler::GRADE_EASY) {
        throw new moodle_exception('errinvalidgrade', 'mod_flashdeck');
    }
    $gradecard = $DB->get_record('flashdeck_cards',
        ['id' => $gradecardid, 'deckid' => $deck->id], '*', MUST_EXIST);
    \mod_flashdeck\local\api::grade_card($deck, $gradecard, $USER->id, $grade, $context);
    redirect(new moodle_url('/mod/flashdeck/view.php', ['id' => $cm->id]));
}

$event = \mod_flashdeck\event\course_module_viewed::create([
    'objectid' => $deck->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('flashdeck', $deck);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$urlparams = ['id' => $cm->id];
if ($mode === 'browse') {
    $urlparams['mode'] = 'browse';
}
$PAGE->set_url('/mod/flashdeck/view.php', $urlparams);
$PAGE->set_title(format_string($course->shortname) . ': ' . format_string($deck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($deck);

/** @var \mod_flashdeck\output\renderer $renderer */
$renderer = $PAGE->get_renderer('mod_flashdeck');

echo $OUTPUT->header();
if ($mode !== 'browse' && $canstudy) {
    // Learn mode: the spaced-repetition session (default).
    echo $renderer->render(new \mod_flashdeck\output\learn_page($deck, $cm, $context, $USER->id));
} else {
    // Browse mode: the sequential card browser; also the read-only
    // fallback for users without the study capability (e.g. guests).
    $cards = $DB->get_records('flashdeck_cards', ['deckid' => $deck->id], 'position ASC, id ASC');
    $canmanage = has_capability('mod/flashdeck:managecards', $context);
    echo $renderer->render(new \mod_flashdeck\output\study_page($deck, $cm, $cards, $context, $canmanage));
}
echo $OUTPUT->footer();
