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

        $modes = [];
        $modes[] = $mform->createElement('advcheckbox', 'modecram', '', get_string('modecram', 'mod_flashdeck'));
        $modes[] = $mform->createElement('advcheckbox', 'modetest', '', get_string('modetest', 'mod_flashdeck'));
        $modes[] = $mform->createElement('advcheckbox', 'modematch', '', get_string('modematch', 'mod_flashdeck'));
        $mform->addGroup($modes, 'modesgroup', get_string('studymodes', 'mod_flashdeck'), '<br>', false);
        $mform->addHelpButton('modesgroup', 'studymodes', 'mod_flashdeck');
        $mform->setDefault('modecram', 1);
        $mform->setDefault('modetest', 1);
        $mform->setDefault('modematch', 1);

        $mform->addElement('header', 'gradeheading', get_string('gradenoun'));

        $mform->addElement('text', 'grade', get_string('grademax', 'mod_flashdeck'), ['size' => '4']);
        $mform->setType('grade', PARAM_INT);
        $mform->setDefault('grade', 0);
        $mform->addHelpButton('grade', 'grademax', 'mod_flashdeck');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Add the deck's automatic completion rules.
     *
     * @return string[] the group element names added
     */
    protected function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->get_suffix();

        $studiedgroup = [
            $mform->createElement('checkbox', 'completionstudiedenabled' . $suffix, '',
                get_string('completionstudied', 'mod_flashdeck')),
            $mform->createElement('text', 'completionstudied' . $suffix, '', ['size' => 3]),
        ];
        $mform->setType('completionstudied' . $suffix, PARAM_INT);
        $mform->addGroup($studiedgroup, 'completionstudiedgroup' . $suffix,
            get_string('completionstudiedgroup', 'mod_flashdeck'), [' '], false);
        $mform->hideIf('completionstudied' . $suffix, 'completionstudiedenabled' . $suffix, 'notchecked');

        $masterygroup = [
            $mform->createElement('checkbox', 'completionmasteryenabled' . $suffix, '',
                get_string('completionmastery', 'mod_flashdeck')),
            $mform->createElement('text', 'completionmastery' . $suffix, '', ['size' => 3]),
        ];
        $mform->setType('completionmastery' . $suffix, PARAM_INT);
        $mform->addGroup($masterygroup, 'completionmasterygroup' . $suffix,
            get_string('completionmasterygroup', 'mod_flashdeck'), [' '], false);
        $mform->hideIf('completionmastery' . $suffix, 'completionmasteryenabled' . $suffix, 'notchecked');

        return ['completionstudiedgroup' . $suffix, 'completionmasterygroup' . $suffix];
    }

    /**
     * Whether at least one automatic completion rule is enabled.
     *
     * @param array $data form data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return (!empty($data['completionstudiedenabled' . $suffix]) && $data['completionstudied' . $suffix] > 0)
            || (!empty($data['completionmasteryenabled' . $suffix]) && $data['completionmastery' . $suffix] > 0);
    }

    /**
     * Map stored rule values onto the enabled checkboxes.
     *
     * @param array $defaultvalues passed by reference
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        $suffix = $this->get_suffix();

        $defaultvalues['completionstudiedenabled' . $suffix] =
            !empty($defaultvalues['completionstudied' . $suffix]) ? 1 : 0;
        if (empty($defaultvalues['completionstudied' . $suffix])) {
            $defaultvalues['completionstudied' . $suffix] = 10;
        }
        $defaultvalues['completionmasteryenabled' . $suffix] =
            !empty($defaultvalues['completionmastery' . $suffix]) ? 1 : 0;
        if (empty($defaultvalues['completionmastery' . $suffix])) {
            $defaultvalues['completionmastery' . $suffix] = 80;
        }
    }

    /**
     * Zero out disabled rules so they are stored as off.
     *
     * @param stdClass $data submitted form data
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            $suffix = $this->get_suffix();
            $autocompletion = !empty($data->{'completion' . $suffix})
                && $data->{'completion' . $suffix} == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->{'completionstudiedenabled' . $suffix}) || !$autocompletion) {
                $data->{'completionstudied' . $suffix} = 0;
            }
            if (empty($data->{'completionmasteryenabled' . $suffix}) || !$autocompletion) {
                $data->{'completionmastery' . $suffix} = 0;
            }
        }
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
        if (isset($data['grade']) && ($data['grade'] < 0 || $data['grade'] > 100)) {
            $errors['grade'] = get_string('errgraderange', 'mod_flashdeck');
        }
        $suffix = $this->get_suffix();
        if (!empty($data['completionmasteryenabled' . $suffix])
                && ($data['completionmastery' . $suffix] < 1 || $data['completionmastery' . $suffix] > 100)) {
            $errors['completionmasterygroup' . $suffix] = get_string('errmasteryrange', 'mod_flashdeck');
        }

        return $errors;
    }
}
