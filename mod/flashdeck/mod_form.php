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
 * Activity settings form for mod_flashdeck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Deck-level settings form.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_flashdeck_mod_form extends moodleform_mod {

    /**
     * Define the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('deckname', 'mod_flashdeck'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'studysettings', get_string('studysettings', 'mod_flashdeck'));

        $schedulers = [
            'sm2' => get_string('schedulersm2', 'mod_flashdeck'),
            'leitner' => get_string('schedulerleitner', 'mod_flashdeck'),
        ];
        $mform->addElement('select', 'scheduler', get_string('scheduler', 'mod_flashdeck'), $schedulers);
        $mform->setDefault('scheduler', 'sm2');
        $mform->addHelpButton('scheduler', 'scheduler', 'mod_flashdeck');

        $mform->addElement('text', 'newperday', get_string('newperday', 'mod_flashdeck'), ['size' => '4']);
        $mform->setType('newperday', PARAM_INT);
        $mform->setDefault('newperday', 20);
        $mform->addHelpButton('newperday', 'newperday', 'mod_flashdeck');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Server-side validation of deck settings.
     *
     * @param array $data submitted values
     * @param array $files submitted files
     * @return array field => error message
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (isset($data['newperday']) && ($data['newperday'] < 1 || $data['newperday'] > 500)) {
            $errors['newperday'] = get_string('errnewperdayrange', 'mod_flashdeck');
        }

        return $errors;
    }
}
