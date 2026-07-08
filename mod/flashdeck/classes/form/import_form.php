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
 * Bulk import form: format choice plus a file or pasted text.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends \moodleform {

    /**
     * Define the form fields.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $formats = [
            'json' => get_string('formatjson', 'mod_flashdeck'),
            'csv' => get_string('formatcsv', 'mod_flashdeck'),
            'gift' => get_string('formatgift', 'mod_flashdeck'),
        ];
        $mform->addElement('select', 'format', get_string('importformat', 'mod_flashdeck'), $formats);
        $mform->addHelpButton('format', 'importformat', 'mod_flashdeck');

        $mform->addElement('filepicker', 'importfile', get_string('importfile', 'mod_flashdeck'),
            null, ['accepted_types' => ['.json', '.csv', '.txt', '.gift']]);

        $mform->addElement('textarea', 'importtext', get_string('importtext', 'mod_flashdeck'),
            ['rows' => 10, 'cols' => 70]);
        $mform->setType('importtext', PARAM_RAW);

        $this->add_action_buttons(true, get_string('import', 'mod_flashdeck'));
    }

    /**
     * A file or pasted text must be supplied.
     *
     * @param array $data submitted values
     * @param array $files submitted files
     * @return array field => error message
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim($data['importtext'] ?? '') === '' && !$this->get_file_content('importfile')) {
            $errors['importtext'] = get_string('errimportnothing', 'mod_flashdeck');
        }
        return $errors;
    }

    /**
     * The content to import: the uploaded file wins over pasted text.
     *
     * @return string
     */
    public function get_import_content(): string {
        $filecontent = $this->get_file_content('importfile');
        if ($filecontent !== false && $filecontent !== '') {
            return $filecontent;
        }
        return (string) $this->get_data()->importtext;
    }
}
