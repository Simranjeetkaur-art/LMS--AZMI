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
 * Library of Moodle interface functions for mod_flashdeck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Declare the features this module supports.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed true if supported, null if unknown
 */
function flashdeck_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            // Mastery-based grading arrives in Phase 4.
            return false;
        case FEATURE_BACKUP_MOODLE2:
            // Backup/restore arrives in Phase 5; not declared before it exists.
            return null;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Add a new flashdeck instance.
 *
 * @param stdClass $data form data from mod_form.php
 * @param mod_flashdeck_mod_form|null $mform the form instance
 * @return int the id of the new instance
 */
function flashdeck_add_instance(stdClass $data, ?mod_flashdeck_mod_form $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    $data->id = $DB->insert_record('flashdeck', $data);

    return $data->id;
}

/**
 * Update an existing flashdeck instance.
 *
 * @param stdClass $data form data from mod_form.php
 * @param mod_flashdeck_mod_form|null $mform the form instance
 * @return bool true on success
 */
function flashdeck_update_instance(stdClass $data, ?mod_flashdeck_mod_form $mform = null): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();

    return $DB->update_record('flashdeck', $data);
}

/**
 * Serve files from the flashdeck file areas.
 *
 * Areas: 'intro' (activity description) and 'cardimage' (one image per
 * card, itemid = card id). Everything is capability-checked; files are
 * never served outside pluginfile.php.
 *
 * @param stdClass $course the course
 * @param stdClass $cm the course module
 * @param context $context the module context
 * @param string $filearea the file area
 * @param array $args remaining path arguments
 * @param bool $forcedownload whether to force download
 * @param array $options additional send_file options
 * @return bool false when the file is not found (otherwise the file is sent)
 */
function flashdeck_pluginfile($course, $cm, $context, string $filearea, array $args,
        bool $forcedownload, array $options = []): bool {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/flashdeck:view', $context);

    $fs = get_file_storage();

    if ($filearea === 'intro') {
        $relativepath = implode('/', $args);
        $fullpath = "/{$context->id}/mod_flashdeck/intro/0/{$relativepath}";
        if (!$file = $fs->get_file_by_hash(sha1($fullpath))) {
            return false;
        }
        send_stored_file($file, null, 0, $forcedownload, $options);
    }

    if ($filearea === 'cardimage') {
        $cardid = (int) array_shift($args);
        // The card must belong to this activity instance.
        if (!$DB->record_exists('flashdeck_cards', ['id' => $cardid, 'deckid' => $cm->instance])) {
            return false;
        }
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
        $file = $fs->get_file($context->id, 'mod_flashdeck', 'cardimage', $cardid, $filepath, $filename);
        if (!$file || $file->is_directory()) {
            return false;
        }
        send_stored_file($file, 86400, 0, $forcedownload, $options);
    }

    return false;
}

/**
 * Delete a flashdeck instance and all its dependent data.
 *
 * @param int $id id of the flashdeck instance
 * @return bool true on success
 */
function flashdeck_delete_instance(int $id): bool {
    global $DB;

    if (!$deck = $DB->get_record('flashdeck', ['id' => $id])) {
        return false;
    }

    // Dependent data first, then the instance itself.
    $DB->delete_records('flashdeck_review', ['deckid' => $deck->id]);
    $DB->delete_records('flashdeck_cards', ['deckid' => $deck->id]);
    $DB->delete_records('flashdeck', ['id' => $deck->id]);

    return true;
}
