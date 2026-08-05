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
 * Manage the allowlisted reference sources the verifier cites from.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_contentchecker\form\source_form;
use local_contentchecker\reference\corpus;

admin_externalpage_setup('local_contentchecker_sources');

$syscontext = context_system::instance();
require_capability('local/contentchecker:manageconfig', $syscontext);

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$url = new moodle_url('/local/contentchecker/sources.php');

// --- Destructive and long-running actions, all sesskey-protected -----------

if ($action === 'delete' && $id && confirm_sesskey()) {
    $DB->delete_records('local_cchecker_chunks', ['sourceid' => $id]);
    $DB->delete_records('local_cchecker_sources', ['id' => $id]);
    redirect($url, get_string('source:deleted', 'local_contentchecker'));
}

if ($action === 'ingest' && $id && confirm_sesskey()) {
    $source = $DB->get_record('local_cchecker_sources', ['id' => $id], '*', MUST_EXIST);
    try {
        \core_php_time_limit::raise(600);
        $chunks = (new corpus())->ingest($source);
        redirect($url, get_string('source:ingested', 'local_contentchecker', $chunks),
            null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

// --- Add / edit -----------------------------------------------------------

$editing = $action === 'edit' || $action === 'add';
$source = $id ? $DB->get_record('local_cchecker_sources', ['id' => $id], '*', MUST_EXIST) : null;

$form = new source_form(new moodle_url($url, ['action' => $action, 'id' => $id]));

if ($source) {
    $draftitemid = file_get_submitted_draft_itemid('sourcefiles');
    file_prepare_draft_area($draftitemid, $syscontext->id, 'local_contentchecker',
        'source', $source->id, ['subdirs' => 0, 'maxfiles' => 5]);
    $source->sourcefiles = $draftitemid;
    $form->set_data($source);
}

if ($form->is_cancelled()) {
    redirect($url);
} else if ($data = $form->get_data()) {
    $record = (object) [
        'sourcetype' => $data->sourcetype,
        'ref' => (string) ($data->ref ?? ''),
        'title' => $data->title,
        'tier' => (int) $data->tier,
        'enabled' => (int) $data->enabled,
        'timemodified' => time(),
    ];

    if (!empty($data->id)) {
        $record->id = $data->id;
        $DB->update_record('local_cchecker_sources', $record);
    } else {
        $record->numchunks = 0;
        $record->id = $DB->insert_record('local_cchecker_sources', $record);
    }

    if (isset($data->sourcefiles)) {
        file_save_draft_area_files($data->sourcefiles, $syscontext->id,
            'local_contentchecker', 'source', $record->id, ['subdirs' => 0, 'maxfiles' => 5]);
    }

    redirect($url, get_string('source:saved', 'local_contentchecker'));
}

// --- Render ---------------------------------------------------------------

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managesources', 'local_contentchecker'));

echo $OUTPUT->notification(get_string('source:why', 'local_contentchecker'),
    \core\output\notification::NOTIFY_INFO);

if ($editing) {
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::div(
    html_writer::link(new moodle_url($url, ['action' => 'add']),
        get_string('source:add', 'local_contentchecker'),
        ['class' => 'btn btn-primary']),
    'mb-3');

$sources = $DB->get_records('local_cchecker_sources', null, 'tier, title');

if (!$sources) {
    echo $OUTPUT->notification(get_string('source:none', 'local_contentchecker'),
        \core\output\notification::NOTIFY_WARNING);
} else {
    $table = new html_table();
    $table->head = [
        get_string('source:title', 'local_contentchecker'),
        get_string('source:type', 'local_contentchecker'),
        get_string('source:tier', 'local_contentchecker'),
        get_string('source:chunks', 'local_contentchecker'),
        get_string('source:fetched', 'local_contentchecker'),
        get_string('source:enabled', 'local_contentchecker'),
        get_string('dashboard:actions', 'local_contentchecker'),
    ];
    $table->attributes['class'] = 'table generaltable';

    foreach ($sources as $row) {
        $actions =
            html_writer::link(new moodle_url($url, ['action' => 'edit', 'id' => $row->id]),
                get_string('edit'), ['class' => 'btn btn-sm btn-link']) .
            html_writer::link(new moodle_url($url,
                ['action' => 'ingest', 'id' => $row->id, 'sesskey' => sesskey()]),
                get_string('source:ingest', 'local_contentchecker'),
                ['class' => 'btn btn-sm btn-link']) .
            html_writer::link(new moodle_url($url,
                ['action' => 'delete', 'id' => $row->id, 'sesskey' => sesskey()]),
                get_string('delete'), ['class' => 'btn btn-sm btn-link text-danger']);

        $table->data[] = [
            s($row->title) . ($row->lasterror
                ? html_writer::div(s($row->lasterror), 'small text-danger') : ''),
            s($row->sourcetype),
            get_string('source:tier' . $row->tier, 'local_contentchecker'),
            $row->numchunks,
            $row->timefetched ? userdate($row->timefetched) : get_string('never', 'moodle'),
            $row->enabled ? get_string('yes') : get_string('no'),
            $actions,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
