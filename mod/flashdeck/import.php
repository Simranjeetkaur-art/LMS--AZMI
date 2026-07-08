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
 * Bulk card import (JSON / CSV / GIFT).
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use mod_flashdeck\local\porter;

$id = required_param('id', PARAM_INT); // Course module id.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'flashdeck');
$deck = $DB->get_record('flashdeck', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/flashdeck:managecards', $context);

$PAGE->set_url('/mod/flashdeck/import.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . format_string($deck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($deck);

$returnurl = new moodle_url('/mod/flashdeck/edit.php', ['id' => $cm->id]);
$form = new \mod_flashdeck\form\import_form($PAGE->url->out(false));
$form->set_data(['id' => $cm->id]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

$importerror = null;
if ($data = $form->get_data()) {
    $content = $form->get_import_content();
    try {
        switch ($data->format) {
            case 'csv':
                $count = porter::import_csv($deck, $content, $USER->id);
                break;
            case 'gift':
                $count = porter::import_gift($deck, $content, $USER->id);
                break;
            default:
                $count = porter::import_json($deck, $content, $USER->id);
        }
        redirect($returnurl, get_string('importdone', 'mod_flashdeck', $count), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        // Nothing was written; show the reason above the form.
        $importerror = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importcards', 'mod_flashdeck'), 3);
if ($importerror !== null) {
    echo $OUTPUT->notification($importerror, 'error');
}
$form->display();
echo $OUTPUT->footer();
