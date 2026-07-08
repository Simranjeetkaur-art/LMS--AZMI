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
 * Add or edit a single card.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use mod_flashdeck\cardtype\card_type;
use mod_flashdeck\cardtype\manager;

$id = required_param('id', PARAM_INT);                    // Course module id.
$cardid = optional_param('cardid', 0, PARAM_INT);         // Existing card, or 0 to add.
$typeid = optional_param('type', '', PARAM_ALPHANUMEXT);  // Card type when adding.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'flashdeck');
$deck = $DB->get_record('flashdeck', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/flashdeck:managecards', $context);

$card = null;
$content = [];
if ($cardid) {
    $card = $DB->get_record('flashdeck_cards', ['id' => $cardid, 'deckid' => $deck->id], '*', MUST_EXIST);
    $typeid = $card->cardtype;
    $content = card_type::decode($card);
}
if (!manager::exists($typeid)) {
    throw new moodle_exception('errunknowncardtype', 'mod_flashdeck', '', $typeid);
}
$type = manager::get($typeid);

$urlparams = ['id' => $cm->id] + ($cardid ? ['cardid' => $cardid] : ['type' => $typeid]);
$PAGE->set_url('/mod/flashdeck/editcard.php', $urlparams);
$PAGE->set_title(format_string($course->shortname) . ': ' . format_string($deck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($deck);

$returnurl = new moodle_url('/mod/flashdeck/edit.php', ['id' => $cm->id]);

$form = new \mod_flashdeck\form\card_form($PAGE->url->out(false), [
    'cardtype' => $type,
    'content' => $content,
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    $now = time();
    $record = (object) [
        'cardtype' => $typeid,
        'tags' => trim($data->tags ?? ''),
        'content' => json_encode($type->process_form($data)),
        'usermodified' => $USER->id,
        'timemodified' => $now,
    ];
    if ($card) {
        $record->id = $card->id;
        $DB->update_record('flashdeck_cards', $record);
    } else {
        $record->deckid = $deck->id;
        $record->timecreated = $now;
        $record->position = 1 + (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(position), 0) FROM {flashdeck_cards} WHERE deckid = ?', [$deck->id]);
        $DB->insert_record('flashdeck_cards', $record);
    }
    redirect($returnurl, get_string('cardsaved', 'mod_flashdeck'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$form->set_data($type->form_defaults($content) + [
    'id' => $cm->id,
    'cardid' => $cardid,
    'type' => $typeid,
    'tags' => $card->tags ?? '',
]);

echo $OUTPUT->header();
echo $OUTPUT->heading($card
    ? get_string('editcard', 'mod_flashdeck')
    : get_string('addcardoftype', 'mod_flashdeck', $type->get_display_name()), 3);
$form->display();
echo $OUTPUT->footer();
