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

use mod_flashdeck\cardtype\card_type;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Card editor form: shared fields plus the card type's own sub-form.
 *
 * Custom data:
 *  - cardtype: card_type instance for the card being edited
 *  - content: decoded content JSON (empty array for a new card)
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class card_form extends \moodleform {

    /**
     * Define the form fields.
     */
    protected function definition() {
        $mform = $this->_form;
        /** @var card_type $type */
        $type = $this->_customdata['cardtype'];
        $content = $this->_customdata['content'] ?? [];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'cardid');
        $mform->setType('cardid', PARAM_INT);
        $mform->addElement('hidden', 'type');
        $mform->setType('type', PARAM_ALPHANUMEXT);

        $mform->addElement('static', 'cardtypename', get_string('cardtype', 'mod_flashdeck'),
            $type->get_display_name());

        $type->add_form_fields($this, $mform, $content);

        $mform->addElement('text', 'tags', get_string('cardtags', 'mod_flashdeck'), ['size' => 40]);
        $mform->setType('tags', PARAM_TEXT);
        $mform->addHelpButton('tags', 'cardtags', 'mod_flashdeck');

        $this->add_action_buttons();
    }

    /**
     * Delegate validation to the card type.
     *
     * @param array $data submitted values
     * @param array $files submitted files
     * @return array field => error message
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        /** @var card_type $type */
        $type = $this->_customdata['cardtype'];
        return array_merge($errors, $type->validate_form($data, $files));
    }
}
