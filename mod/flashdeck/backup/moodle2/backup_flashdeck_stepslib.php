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
 * Backup structure step for mod_flashdeck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the full flashdeck XML structure for backup.
 *
 * Cards always travel with the activity; per-user review state and
 * study-day aggregates only when user info is included.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_flashdeck_activity_structure_step extends backup_activity_structure_step {

    #[\Override]
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $flashdeck = new backup_nested_element('flashdeck', ['id'], [
            'name', 'intro', 'introformat', 'scheduler', 'newperday', 'grade',
            'completionstudied', 'completionmastery', 'modecram', 'modetest', 'modematch',
            'timecreated', 'timemodified',
        ]);

        $cards = new backup_nested_element('cards');
        $card = new backup_nested_element('card', ['id'], [
            'cardtype', 'position', 'tags', 'content', 'usermodified', 'timecreated', 'timemodified',
        ]);

        $reviews = new backup_nested_element('reviews');
        $review = new backup_nested_element('review', ['id'], [
            'cardid', 'userid', 'easefactor', 'intervaldays', 'duedate', 'repetitions',
            'lapses', 'state', 'lastgrade', 'lastreviewed', 'timecreated', 'timemodified',
        ]);

        $sessions = new backup_nested_element('sessions');
        $session = new backup_nested_element('session', ['id'], [
            'userid', 'daystart', 'reviews', 'correct', 'points', 'firstreview', 'lastreview',
        ]);

        $flashdeck->add_child($cards);
        $cards->add_child($card);
        $flashdeck->add_child($reviews);
        $reviews->add_child($review);
        $flashdeck->add_child($sessions);
        $sessions->add_child($session);

        $flashdeck->set_source_table('flashdeck', ['id' => backup::VAR_ACTIVITYID]);
        $card->set_source_table('flashdeck_cards', ['deckid' => backup::VAR_PARENTID], 'position ASC');
        if ($userinfo) {
            $review->set_source_table('flashdeck_review', ['deckid' => backup::VAR_PARENTID]);
            $session->set_source_table('flashdeck_session', ['deckid' => backup::VAR_PARENTID]);
        }

        $card->annotate_ids('user', 'usermodified');
        $review->annotate_ids('user', 'userid');
        $session->annotate_ids('user', 'userid');

        $flashdeck->annotate_files('mod_flashdeck', 'intro', null);
        $card->annotate_files('mod_flashdeck', 'cardimage', 'id');

        return $this->prepare_activity_structure($flashdeck);
    }
}
