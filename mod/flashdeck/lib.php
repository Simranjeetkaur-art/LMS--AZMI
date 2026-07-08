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
