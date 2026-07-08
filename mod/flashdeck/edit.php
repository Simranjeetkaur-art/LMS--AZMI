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
 * Teacher card management: list, reorder, delete, seed sample deck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);              // Course module id.
$action = optional_param('action', '', PARAM_ALPHA);
$cardid = optional_param('cardid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'flashdeck');
$deck = $DB->get_record('flashdeck', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/flashdeck:managecards', $context);

$baseurl = new moodle_url('/mod/flashdeck/edit.php', ['id' => $cm->id]);

$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($course->shortname) . ': ' . format_string($deck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($deck);

$card = null;
if ($cardid) {
    $card = $DB->get_record('flashdeck_cards', ['id' => $cardid, 'deckid' => $deck->id], '*', MUST_EXIST);
}

if ($action === 'delete' && $card) {
    if (!$confirm) {
        // Ask before destroying authored content.
        echo $OUTPUT->header();
        $yesurl = new moodle_url($baseurl, [
            'action' => 'delete', 'cardid' => $card->id, 'confirm' => 1, 'sesskey' => sesskey(),
        ]);
        echo $OUTPUT->confirm(get_string('deletecardconfirm', 'mod_flashdeck'), $yesurl, $baseurl);
        echo $OUTPUT->footer();
        die;
    }
    require_sesskey();
    $DB->delete_records('flashdeck_review', ['cardid' => $card->id]);
    $DB->delete_records('flashdeck_cards', ['id' => $card->id]);
    flashdeck_resequence($deck->id);
    redirect($baseurl, get_string('carddeleted', 'mod_flashdeck'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if (($action === 'moveup' || $action === 'movedown') && $card) {
    require_sesskey();
    $cards = array_values($DB->get_records('flashdeck_cards', ['deckid' => $deck->id],
        'position ASC, id ASC', 'id, position'));
    foreach ($cards as $i => $row) {
        if ((int) $row->id !== (int) $card->id) {
            continue;
        }
        $swapwith = ($action === 'moveup') ? $i - 1 : $i + 1;
        if (isset($cards[$swapwith])) {
            $DB->set_field('flashdeck_cards', 'position', $swapwith + 1, ['id' => $row->id]);
            $DB->set_field('flashdeck_cards', 'position', $i + 1, ['id' => $cards[$swapwith]->id]);
        }
        break;
    }
    redirect($baseurl);
}

if ($action === 'seed') {
    require_sesskey();
    $count = \mod_flashdeck\local\seeder::seed($deck, $USER->id);
    redirect($baseurl, get_string('sampledeckloaded', 'mod_flashdeck', $count), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$cards = $DB->get_records('flashdeck_cards', ['deckid' => $deck->id], 'position ASC, id ASC');

/** @var \mod_flashdeck\output\renderer $renderer */
$renderer = $PAGE->get_renderer('mod_flashdeck');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecards', 'mod_flashdeck'), 3);
echo $renderer->render(new \mod_flashdeck\output\manage_page($deck, $cm, $cards));
echo $OUTPUT->footer();

/**
 * Renumber a deck's cards 1..n keeping their current order.
 *
 * @param int $deckid the flashdeck id
 */
function flashdeck_resequence(int $deckid): void {
    global $DB;
    $rows = $DB->get_records('flashdeck_cards', ['deckid' => $deckid], 'position ASC, id ASC', 'id, position');
    $position = 0;
    foreach ($rows as $row) {
        $position++;
        if ((int) $row->position !== $position) {
            $DB->set_field('flashdeck_cards', 'position', $position, ['id' => $row->id]);
        }
    }
}
