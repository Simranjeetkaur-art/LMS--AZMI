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
 * Registry of embeddable 3D models and diagrams.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This plugin is symlinked into the Moodle tree, so __DIR__ resolves to the
// real path outside it and the conventional relative require misses config.php
// entirely. Fall back the same way the other AZMSI plugins do.
require(is_file(__DIR__ . '/../../../config.php')
    ? __DIR__ . '/../../../config.php'
    : (getenv('MOODLE_ROOT')
        ? rtrim(getenv('MOODLE_ROOT'), '/') . '/config.php'
        : '/var/www/moodle/config.php'));
require_once($CFG->libdir . '/adminlib.php');

use local_contentchecker\form\asset_form;
use local_contentchecker\local\audit;
use local_contentchecker\local\enrichment;

admin_externalpage_setup('local_contentchecker_assets');

require_capability('local/contentchecker:manageconfig', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$url = new moodle_url('/local/contentchecker/assets.php');

if ($action === 'delete' && $id && confirm_sesskey()) {
    $asset = $DB->get_record('local_cchecker_assets', ['id' => $id], '*', MUST_EXIST);
    audit::log('asset', $id, 'deleted', ['before' => $asset->name]);
    $DB->delete_records('local_cchecker_assets', ['id' => $id]);
    redirect($url, get_string('asset:deleted', 'local_contentchecker'));
}

$editing = $action === 'edit' || $action === 'add';
$asset = $id ? $DB->get_record('local_cchecker_assets', ['id' => $id], '*', MUST_EXIST) : null;

$form = new asset_form(new moodle_url($url, ['action' => $action, 'id' => $id]));
if ($asset) {
    $form->set_data($asset);
}

if ($form->is_cancelled()) {
    redirect($url);
} else if ($data = $form->get_data()) {
    $now = time();
    $record = (object) [
        'name' => $data->name,
        'assettype' => $data->assettype,
        'viewer' => $data->viewer,
        'url' => (string) ($data->url ?? ''),
        'posterurl' => (string) ($data->posterurl ?? ''),
        'body' => (string) ($data->body ?? ''),
        'description' => (string) ($data->description ?? ''),
        'licence' => (string) ($data->licence ?? ''),
        'attribution' => (string) ($data->attribution ?? ''),
        'sortorder' => (int) $data->sortorder,
        'enabled' => (int) $data->enabled,
        'usermodified' => (int) $USER->id,
        'timemodified' => $now,
    ];

    if (!empty($data->id)) {
        $record->id = $data->id;
        $DB->update_record('local_cchecker_assets', $record);
        audit::log('asset', (int) $record->id, 'updated', ['after' => $record->name]);
    } else {
        $record->timecreated = $now;
        $record->id = $DB->insert_record('local_cchecker_assets', $record);
        audit::log('asset', (int) $record->id, 'created', ['after' => $record->name]);
    }

    redirect($url, get_string('asset:saved', 'local_contentchecker'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageassets', 'local_contentchecker'));
echo html_writer::tag('p', get_string('asset:intro', 'local_contentchecker'),
    ['class' => 'text-muted']);

if ($editing) {
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::div(
    html_writer::link(new moodle_url($url, ['action' => 'add']),
        get_string('asset:add', 'local_contentchecker'),
        ['class' => 'btn btn-primary']),
    'mb-3');

$assets = $DB->get_records('local_cchecker_assets', null, 'sortorder, name');

if (!$assets) {
    echo $OUTPUT->notification(get_string('asset:none', 'local_contentchecker'),
        \core\output\notification::NOTIFY_INFO);
} else {
    foreach ($assets as $row) {
        $actions =
            html_writer::link(new moodle_url($url, ['action' => 'edit', 'id' => $row->id]),
                get_string('edit'), ['class' => 'btn btn-sm btn-link']) .
            html_writer::link(new moodle_url($url,
                ['action' => 'delete', 'id' => $row->id, 'sesskey' => sesskey()]),
                get_string('delete'), ['class' => 'btn btn-sm btn-link text-danger']);

        echo html_writer::start_div('card mb-3');
        echo html_writer::div(
            html_writer::span(s($row->name), 'font-weight-bold mr-2') .
            html_writer::span(get_string('asset:type:' . $row->assettype,
                'local_contentchecker'), 'badge badge-light mr-2') .
            ($row->enabled ? '' : html_writer::span(
                get_string('asset:disabled', 'local_contentchecker'),
                'badge badge-secondary mr-2')) .
            $actions,
            'card-header d-flex justify-content-between align-items-center flex-wrap');
        echo html_writer::div(enrichment::render_asset($row), 'card-body');
        echo html_writer::end_div();
    }
}

echo $OUTPUT->footer();
