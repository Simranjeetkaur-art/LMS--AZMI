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
 * Add or edit an allowlisted reference source.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_form extends \moodleform {

    /**
     * Build the form.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'title',
            get_string('source:title', 'local_contentchecker'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('select', 'sourcetype',
            get_string('source:type', 'local_contentchecker'), [
                'wikipedia' => get_string('source:type:wikipedia', 'local_contentchecker'),
                'url' => get_string('source:type:url', 'local_contentchecker'),
                'youtube' => get_string('source:type:youtube', 'local_contentchecker'),
                'upload' => get_string('source:type:upload', 'local_contentchecker'),
            ]);

        $mform->addElement('text', 'ref',
            get_string('source:ref', 'local_contentchecker'), ['size' => 80]);
        $mform->setType('ref', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('ref', 'source:ref', 'local_contentchecker');
        $mform->hideIf('ref', 'sourcetype', 'eq', 'upload');

        $mform->addElement('filemanager', 'sourcefiles',
            get_string('source:files', 'local_contentchecker'), null,
            ['subdirs' => 0, 'maxfiles' => 5, 'accepted_types' => ['.txt', '.html', '.htm']]);
        $mform->hideIf('sourcefiles', 'sourcetype', 'neq', 'upload');

        // The tier is what stops a blog post being weighed like a textbook, so
        // it is a required, explicit choice rather than a silent default.
        $mform->addElement('select', 'tier',
            get_string('source:tier', 'local_contentchecker'), [
                1 => get_string('source:tier1', 'local_contentchecker'),
                2 => get_string('source:tier2', 'local_contentchecker'),
                3 => get_string('source:tier3', 'local_contentchecker'),
            ]);
        $mform->setDefault('tier', 3);
        $mform->addHelpButton('tier', 'source:tier', 'local_contentchecker');

        $mform->addElement('advcheckbox', 'enabled',
            get_string('source:enabled', 'local_contentchecker'));
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
        $errors = parent::validation($data, $files);

        if (($data['sourcetype'] ?? '') !== 'upload' && trim($data['ref'] ?? '') === '') {
            $errors['ref'] = get_string('required');
        }

        return $errors;
    }
}
