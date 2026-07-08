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

use mod_flashdeck\local\ai_generator;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * AI generation request: source material, card types, count.
 *
 * Custom data: 'maxcards' => int upper bound from admin settings.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_form extends \moodleform {

    /**
     * Define the form fields.
     */
    protected function definition() {
        $mform = $this->_form;
        $maxcards = (int) ($this->_customdata['maxcards'] ?? 20);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('textarea', 'source', get_string('aisource', 'mod_flashdeck'),
            ['rows' => 12, 'cols' => 70]);
        $mform->setType('source', PARAM_RAW);
        $mform->addRule('source', null, 'required', null, 'client');
        $mform->addHelpButton('source', 'aisource', 'mod_flashdeck');

        $typeboxes = [];
        $identifiers = array_keys(ai_generator::get_generatable_types());
        foreach ($identifiers as $identifier) {
            $typeboxes[] = $mform->createElement('advcheckbox', "types[{$identifier}]", '',
                get_string('cardtype' . $identifier, 'mod_flashdeck'));
        }
        $mform->addGroup($typeboxes, 'typesgroup', get_string('aigeneratetypes', 'mod_flashdeck'),
            '<br>', false);
        foreach ($identifiers as $identifier) {
            $mform->setDefault("types[{$identifier}]", 1);
        }

        $mform->addElement('text', 'count', get_string('aigeneratecount', 'mod_flashdeck'), ['size' => 3]);
        $mform->setType('count', PARAM_INT);
        $mform->setDefault('count', min(10, $maxcards));

        $this->add_action_buttons(true, get_string('aigenerate', 'mod_flashdeck'));
    }

    /**
     * Validate the request.
     *
     * @param array $data submitted values
     * @param array $files submitted files
     * @return array field => error message
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $maxcards = (int) ($this->_customdata['maxcards'] ?? 20);

        if (trim($data['source'] ?? '') === '') {
            $errors['source'] = get_string('required');
        }
        if (($data['count'] ?? 0) < 1 || $data['count'] > $maxcards) {
            $errors['count'] = get_string('erraicountrange', 'mod_flashdeck', $maxcards);
        }
        if (empty(array_filter($data['types'] ?? []))) {
            $errors['typesgroup'] = get_string('errainotypes', 'mod_flashdeck');
        }
        return $errors;
    }
}
