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

namespace mod_flashdeck\event;

/**
 * Event fired every time a learner self-grades a card.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class card_reviewed extends \core\event\base {

    #[\Override]
    protected function init() {
        $this->data['objecttable'] = 'flashdeck_review';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    #[\Override]
    public static function get_name() {
        return get_string('eventcardreviewed', 'mod_flashdeck');
    }

    #[\Override]
    public function get_description() {
        return "The user with id '{$this->relateduserid}' graded card '{$this->other['cardid']}' " .
            "with grade '{$this->other['grade']}' in the flashdeck with course module id '{$this->contextinstanceid}'.";
    }

    #[\Override]
    public function get_url() {
        return new \moodle_url('/mod/flashdeck/view.php', ['id' => $this->contextinstanceid]);
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        foreach (['cardid', 'grade', 'state', 'intervaldays'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '{$key}' value must be set in other.");
            }
        }
    }
}
