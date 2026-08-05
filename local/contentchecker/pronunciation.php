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
 * The custom pronunciation dictionary for the high-quality TTS voice.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_contentchecker\api\tts_client;
use local_contentchecker\form\pronounce_form;
use local_contentchecker\local\pronunciation;

admin_externalpage_setup('local_contentchecker_pronounce');

require_capability('local/contentchecker:manageconfig', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$url = new moodle_url('/local/contentchecker/pronunciation.php');

if ($action === 'delete' && $id && confirm_sesskey()) {
    $DB->delete_records('local_cchecker_pronounce', ['id' => $id]);
    pronunciation::invalidate();
    redirect($url, get_string('pronounce:deleted', 'local_contentchecker'));
}

$editing = $action === 'edit' || $action === 'add';
$entry = $id ? $DB->get_record('local_cchecker_pronounce', ['id' => $id], '*', MUST_EXIST) : null;

$form = new pronounce_form(new moodle_url($url, ['action' => $action, 'id' => $id]));
if ($entry) {
    $form->set_data($entry);
}

if ($form->is_cancelled()) {
    redirect($url);
} else if ($data = $form->get_data()) {
    $record = (object) [
        'term' => trim($data->term),
        'replacement' => trim($data->replacement),
        'matchtype' => $data->matchtype,
        'notes' => (string) ($data->notes ?? ''),
        'enabled' => (int) $data->enabled,
        'usermodified' => (int) $USER->id,
        'timemodified' => time(),
    ];

    if (!empty($data->id)) {
        $record->id = $data->id;
        $DB->update_record('local_cchecker_pronounce', $record);
    } else {
        $DB->insert_record('local_cchecker_pronounce', $record);
    }

    // The dictionary is cached for the TTS hot path, so an edit that did not
    // invalidate it would appear to do nothing.
    pronunciation::invalidate();
    redirect($url, get_string('pronounce:saved', 'local_contentchecker'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managepronunciation', 'local_contentchecker'));

if (!tts_client::is_available()) {
    echo $OUTPUT->notification(get_string('pronounce:nottsconfigured', 'local_contentchecker'),
        \core\output\notification::NOTIFY_WARNING);
}

echo html_writer::tag('p', get_string('pronounce:intro', 'local_contentchecker'),
    ['class' => 'text-muted']);

if ($editing) {
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::div(
    html_writer::link(new moodle_url($url, ['action' => 'add']),
        get_string('pronounce:add', 'local_contentchecker'),
        ['class' => 'btn btn-primary']),
    'mb-3');

$entries = $DB->get_records('local_cchecker_pronounce', null, 'term');

if (!$entries) {
    echo $OUTPUT->notification(get_string('pronounce:none', 'local_contentchecker'),
        \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->head = [
        get_string('pronounce:term', 'local_contentchecker'),
        get_string('pronounce:replacement', 'local_contentchecker'),
        get_string('pronounce:matchtype', 'local_contentchecker'),
        get_string('pronounce:notes', 'local_contentchecker'),
        get_string('pronounce:enabled', 'local_contentchecker'),
        get_string('dashboard:actions', 'local_contentchecker'),
    ];
    $table->attributes['class'] = 'table generaltable';

    foreach ($entries as $row) {
        $table->data[] = [
            s($row->term),
            s($row->replacement),
            get_string('pronounce:matchtype:' . $row->matchtype, 'local_contentchecker'),
            s((string) $row->notes),
            $row->enabled ? get_string('yes') : get_string('no'),
            html_writer::link(new moodle_url($url, ['action' => 'edit', 'id' => $row->id]),
                get_string('edit'), ['class' => 'btn btn-sm btn-link']) .
            html_writer::link(new moodle_url($url,
                ['action' => 'delete', 'id' => $row->id, 'sesskey' => sesskey()]),
                get_string('delete'), ['class' => 'btn btn-sm btn-link text-danger']),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
