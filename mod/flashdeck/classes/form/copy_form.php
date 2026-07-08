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

namespace mod_flashdeck\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Copy cards from another deck the teacher can manage, with an
 * optional tag filter so topic pools (e.g. "cardiology") can be reused.
 *
 * Custom data: 'decks' => array of deckid => label.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class copy_form extends \moodleform {

    /**
     * Define the form fields.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'copyfrom');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('select', 'sourcedeck', get_string('copysource', 'mod_flashdeck'),
            $this->_customdata['decks']);
        $mform->addRule('sourcedeck', null, 'required', null, 'client');

        $mform->addElement('text', 'tagfilter', get_string('copytagfilter', 'mod_flashdeck'), ['size' => 30]);
        $mform->setType('tagfilter', PARAM_TEXT);
        $mform->addHelpButton('tagfilter', 'copytagfilter', 'mod_flashdeck');

        $this->add_action_buttons(true, get_string('copycards', 'mod_flashdeck'));
    }
}
