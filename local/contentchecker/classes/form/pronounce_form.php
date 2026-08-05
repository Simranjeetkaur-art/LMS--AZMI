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

namespace local_contentchecker\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Add or edit a pronunciation dictionary entry.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pronounce_form extends \moodleform {

    /**
     * Build the form.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'term',
            get_string('pronounce:term', 'local_contentchecker'), ['size' => 40]);
        $mform->setType('term', PARAM_TEXT);
        $mform->addRule('term', null, 'required', null, 'client');
        $mform->addHelpButton('term', 'pronounce:term', 'local_contentchecker');

        $mform->addElement('text', 'replacement',
            get_string('pronounce:replacement', 'local_contentchecker'), ['size' => 40]);
        $mform->setType('replacement', PARAM_TEXT);
        $mform->addRule('replacement', null, 'required', null, 'client');
        $mform->addHelpButton('replacement', 'pronounce:replacement', 'local_contentchecker');

        // Word matching is the default because a substring rule for a short
        // term silently corrupts longer words that contain it.
        $mform->addElement('select', 'matchtype',
            get_string('pronounce:matchtype', 'local_contentchecker'), [
                'word' => get_string('pronounce:matchtype:word', 'local_contentchecker'),
                'substring' => get_string('pronounce:matchtype:substring', 'local_contentchecker'),
            ]);
        $mform->setDefault('matchtype', 'word');

        $mform->addElement('text', 'notes',
            get_string('pronounce:notes', 'local_contentchecker'), ['size' => 60]);
        $mform->setType('notes', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'enabled',
            get_string('pronounce:enabled', 'local_contentchecker'));
        $mform->setDefault('enabled', 1);

        $this->add_action_buttons();
    }

    /**
     * Validate the submission.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files): array {
        global $DB;

        $errors = parent::validation($data, $files);

        $term = trim($data['term'] ?? '');
        if ($term !== '') {
            $existing = $DB->get_record('local_cchecker_pronounce', ['term' => $term]);
            if ($existing && (int) $existing->id !== (int) ($data['id'] ?? 0)) {
                $errors['term'] = get_string('pronounce:duplicate', 'local_contentchecker');
            }
        }

        return $errors;
    }
}
