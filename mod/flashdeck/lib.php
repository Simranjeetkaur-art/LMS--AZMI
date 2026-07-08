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
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
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

    flashdeck_grade_item_update($data);

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

    $result = $DB->update_record('flashdeck', $data);

    // Grade settings may have changed; rebuild the item and regrade.
    $deck = $DB->get_record('flashdeck', ['id' => $data->id], '*', MUST_EXIST);
    flashdeck_grade_item_update($deck);
    flashdeck_update_grades($deck);

    return $result;
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
    $DB->delete_records('flashdeck_session', ['deckid' => $deck->id]);
    $DB->delete_records('flashdeck_review', ['deckid' => $deck->id]);
    $DB->delete_records('flashdeck_cards', ['deckid' => $deck->id]);
    $DB->delete_records('flashdeck', ['id' => $deck->id]);

    flashdeck_grade_item_delete($deck);

    return true;
}

/**
 * Provide course-module info, including custom completion rule values.
 *
 * @param stdClass $coursemodule the course module record
 * @return cached_cm_info|false
 */
function flashdeck_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, completionstudied, completionmastery';
    if (!$deck = $DB->get_record('flashdeck', ['id' => $coursemodule->instance], $fields)) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $deck->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('flashdeck', $deck, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionstudied'] = $deck->completionstudied;
        $info->customdata['customcompletionrules']['completionmastery'] = $deck->completionmastery;
    }

    return $info;
}

/**
 * Compute mastery-based gradebook grades for one or all users.
 *
 * Mastery = graduated cards (review state 'review') / total cards; the
 * raw grade is that fraction of the deck's maximum grade.
 *
 * @param stdClass $deck the flashdeck record
 * @param int $userid a specific user, or 0 for all users with review data
 * @return array userid => grade object with userid and rawgrade
 */
function flashdeck_get_user_grades(stdClass $deck, int $userid = 0): array {
    global $DB;

    $total = $DB->count_records('flashdeck_cards', ['deckid' => $deck->id]);
    if (!$total || !$deck->grade) {
        return [];
    }

    $params = ['deckid' => $deck->id];
    $usersql = '';
    if ($userid) {
        $usersql = ' AND userid = :userid';
        $params['userid'] = $userid;
    }

    $sql = "SELECT userid, COUNT(id) AS graduated
              FROM {flashdeck_review}
             WHERE deckid = :deckid AND state = 'review'{$usersql}
          GROUP BY userid";

    $grades = [];
    foreach ($DB->get_records_sql($sql, $params) as $row) {
        $grades[$row->userid] = (object) [
            'userid' => $row->userid,
            'rawgrade' => $deck->grade * min(1, $row->graduated / $total),
        ];
    }
    return $grades;
}

/**
 * Create, update or reset the gradebook item for a deck.
 *
 * @param stdClass $deck the flashdeck record (needs id, course, name, grade)
 * @param mixed $grades grades to push, null for none, 'reset' to wipe
 * @return int GRADE_UPDATE_OK or a failure code
 */
function flashdeck_grade_item_update(stdClass $deck, $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = ['itemname' => clean_param($deck->name, PARAM_NOTAGS)];
    if (empty($deck->grade)) {
        $params['gradetype'] = GRADE_TYPE_NONE;
    } else {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $deck->grade;
        $params['grademin'] = 0;
    }
    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/flashdeck', $deck->course, 'mod', 'flashdeck', $deck->id, 0, $grades, $params);
}

/**
 * Delete the gradebook item for a deck.
 *
 * @param stdClass $deck the flashdeck record
 * @return int GRADE_UPDATE_OK or a failure code
 */
function flashdeck_grade_item_delete(stdClass $deck): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/flashdeck', $deck->course, 'mod', 'flashdeck', $deck->id, 0, null,
        ['deleted' => 1]);
}

/**
 * Push current mastery grades to the gradebook.
 *
 * @param stdClass $deck the flashdeck record
 * @param int $userid a specific user, or 0 for all
 * @param bool $nullifnone insert a null grade when the user has none
 */
function flashdeck_update_grades(stdClass $deck, int $userid = 0, bool $nullifnone = true): void {
    if (empty($deck->grade)) {
        flashdeck_grade_item_update($deck);
        return;
    }
    if ($grades = flashdeck_get_user_grades($deck, $userid)) {
        flashdeck_grade_item_update($deck, $grades);
    } else if ($userid && $nullifnone) {
        $grade = (object) ['userid' => $userid, 'rawgrade' => null];
        flashdeck_grade_item_update($deck, [$userid => $grade]);
    } else {
        flashdeck_grade_item_update($deck);
    }
}
