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
 * Bulk card export (JSON round-trips everything; CSV covers the
 * text-friendly types).
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use mod_flashdeck\local\porter;

$id = required_param('id', PARAM_INT);                    // Course module id.
$format = optional_param('format', 'json', PARAM_ALPHA);  // Export format.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'flashdeck');
$deck = $DB->get_record('flashdeck', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/flashdeck:managecards', $context);

$cards = $DB->get_records('flashdeck_cards', ['deckid' => $deck->id], 'position ASC, id ASC');

$basename = clean_filename($deck->name !== '' ? $deck->name : 'flashdeck');
if ($format === 'csv') {
    send_file(porter::export_csv($deck, $cards), $basename . '.csv', 0, 0, true, true, 'text/csv');
} else {
    send_file(porter::export_json($deck, $cards), $basename . '.json', 0, 0, true, true, 'application/json');
}
