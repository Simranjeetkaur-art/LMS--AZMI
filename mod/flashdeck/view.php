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

$event = \mod_flashdeck\event\course_module_viewed::create([
    'objectid' => $deck->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('flashdeck', $deck);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/flashdeck/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . format_string($deck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($deck);

$cards = $DB->get_records('flashdeck_cards', ['deckid' => $deck->id], 'position ASC, id ASC');
$canmanage = has_capability('mod/flashdeck:managecards', $context);

/** @var \mod_flashdeck\output\renderer $renderer */
$renderer = $PAGE->get_renderer('mod_flashdeck');

echo $OUTPUT->header();
echo $renderer->render(new \mod_flashdeck\output\study_page($deck, $cm, $cards, $context, $canmanage));
echo $OUTPUT->footer();
