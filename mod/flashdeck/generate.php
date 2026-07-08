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
 * AI-assisted card generation: request, review the proposal, import.
 *
 * The model output is never trusted or auto-saved: every proposed card
 * is validated through its card type, rendered for the teacher with the
 * real card templates, and only the teacher's selection is imported.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use mod_flashdeck\cardtype\manager;
use mod_flashdeck\local\ai_client;
use mod_flashdeck\local\ai_generator;
use mod_flashdeck\local\porter;

$id = required_param('id', PARAM_INT); // Course module id.
$action = optional_param('action', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'flashdeck');
$deck = $DB->get_record('flashdeck', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/flashdeck:managecards', $context);
require_capability('mod/flashdeck:generateai', $context);

$PAGE->set_url('/mod/flashdeck/generate.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . format_string($deck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($deck);

$returnurl = new moodle_url('/mod/flashdeck/edit.php', ['id' => $cm->id]);

if (!ai_generator::is_available()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('erraiunconfigured', 'mod_flashdeck'), 'info');
    echo $OUTPUT->continue_button($returnurl);
    echo $OUTPUT->footer();
    die;
}

// Stage 3: the teacher confirmed a selection from the preview.
if ($action === 'confirm' && data_submitted()) {
    require_sesskey();
    $payload = json_decode(required_param('payload', PARAM_RAW), true);
    $include = optional_param_array('include', [], PARAM_INT);
    if (!is_array($payload) || !$include) {
        redirect($PAGE->url, get_string('ainoneselected', 'mod_flashdeck'), null,
            \core\output\notification::NOTIFY_WARNING);
    }

    $aitag = trim((string) get_config('mod_flashdeck', 'aitag'));
    $selected = [];
    foreach ($include as $index) {
        if (!isset($payload[$index]) || !is_array($payload[$index])) {
            continue;
        }
        $def = $payload[$index];
        if ($aitag !== '') {
            $def['tags'] = trim(($def['tags'] ?? '') !== '' ? $def['tags'] . ',' . $aitag : $aitag);
        }
        $selected[] = $def;
    }

    // Porter re-validates everything and imports atomically.
    $count = porter::import_cards($deck, $selected, $USER->id);
    redirect($returnurl, get_string('aidone', 'mod_flashdeck', $count), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$maxcards = (int) get_config('mod_flashdeck', 'aimaxcards') ?: 20;
$form = new \mod_flashdeck\form\generate_form($PAGE->url->out(false), ['maxcards' => $maxcards]);
$form->set_data(['id' => $cm->id]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

$generationerror = null;
if ($data = $form->get_data()) {
    // Stage 2: run the inference and preview the proposal.
    $typeids = array_keys(array_filter((array) ($data->types ?? [])));
    \core_php_time_limit::raise((new ai_client())->is_configured() ? 900 : 60);
    $result = ai_generator::generate($data->source, $typeids, (int) $data->count);

    if ($result['error'] !== '') {
        $generationerror = $result['error'];
    } else if (!$result['cards']) {
        $generationerror = get_string('erraiunparseable', 'mod_flashdeck');
    } else {
        $previews = [];
        foreach ($result['cards'] as $index => $def) {
            $type = manager::get($def['cardtype']);
            $fakecard = (object) ['id' => 0, 'cardtype' => $def['cardtype'],
                'content' => json_encode($def['content'])];
            $previews[] = [
                'index' => $index,
                'typename' => $type->get_display_name(),
                'tags' => $def['tags'],
                'cardhtml' => $OUTPUT->render_from_template($type->get_template(),
                    $type->export_for_template($fakecard, $context)),
            ];
        }
        $rejected = array_map(static function(array $reject): array {
            return [
                'cardtype' => $reject['def']['cardtype'],
                'problem' => $reject['problem'],
            ];
        }, $result['rejected']);

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('aipreviewheading', 'mod_flashdeck'), 3);
        echo $OUTPUT->render_from_template('mod_flashdeck/generate_preview', [
            'actionurl' => $PAGE->url->out(false),
            'sesskey' => sesskey(),
            'payload' => json_encode($result['cards']),
            'previews' => $previews,
            'previewcount' => count($previews),
            'hasrejected' => !empty($rejected),
            'rejectedcount' => count($rejected),
            'rejected' => $rejected,
            'cancelurl' => $returnurl->out(false),
        ]);
        echo $OUTPUT->footer();
        die;
    }
}

// Stage 1: the request form (redisplayed with a notification on errors).
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('aigenerate', 'mod_flashdeck'), 3);
if ($generationerror !== null) {
    echo $OUTPUT->notification($generationerror, 'error');
}
$form->display();
echo $OUTPUT->footer();
