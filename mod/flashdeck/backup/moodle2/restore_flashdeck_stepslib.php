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
 * Restore structure step for mod_flashdeck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Rebuilds a flashdeck (cards, and per-user data when included) from XML.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_flashdeck_activity_structure_step extends restore_activity_structure_step {

    #[\Override]
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('flashdeck', '/activity/flashdeck');
        $paths[] = new restore_path_element('flashdeck_card', '/activity/flashdeck/cards/card');
        if ($userinfo) {
            $paths[] = new restore_path_element('flashdeck_review', '/activity/flashdeck/reviews/review');
            $paths[] = new restore_path_element('flashdeck_session', '/activity/flashdeck/sessions/session');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the activity instance.
     *
     * @param array $data the flashdeck record data
     */
    protected function process_flashdeck($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        $newid = $DB->insert_record('flashdeck', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restore a card, mapping the author and remembering the id for files.
     *
     * @param array $data the card record data
     */
    protected function process_flashdeck_card($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->deckid = $this->get_new_parentid('flashdeck');
        $data->usermodified = $this->get_mappingid('user', $data->usermodified) ?: 0;

        $newid = $DB->insert_record('flashdeck_cards', $data);
        // The true flag lets add_related_files() remap cardimage itemids.
        $this->set_mapping('flashdeck_card', $oldid, $newid, true);
    }

    /**
     * Restore a per-user review row (userinfo only).
     *
     * @param array $data the review record data
     */
    protected function process_flashdeck_review($data) {
        global $DB;

        $data = (object) $data;
        $data->deckid = $this->get_new_parentid('flashdeck');
        $data->cardid = $this->get_mappingid('flashdeck_card', $data->cardid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        if ($data->cardid && $data->userid) {
            $DB->insert_record('flashdeck_review', $data);
        }
    }

    /**
     * Restore a per-user study-day aggregate (userinfo only).
     *
     * @param array $data the session record data
     */
    protected function process_flashdeck_session($data) {
        global $DB;

        $data = (object) $data;
        $data->deckid = $this->get_new_parentid('flashdeck');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if ($data->userid) {
            $DB->insert_record('flashdeck_session', $data);
        }
    }

    #[\Override]
    protected function after_execute() {
        $this->add_related_files('mod_flashdeck', 'intro', null);
        $this->add_related_files('mod_flashdeck', 'cardimage', 'flashdeck_card');
    }
}
