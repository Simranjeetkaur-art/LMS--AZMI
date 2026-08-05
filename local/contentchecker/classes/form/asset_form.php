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
 * Register a 3D model, diagram or embeddable asset.
 *
 * Registering an asset is what makes it insertable, so any compatible
 * open-source asset can be added here without a code change.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class asset_form extends \moodleform {

    /**
     * Build the form.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name',
            get_string('asset:name', 'local_contentchecker'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('select', 'assettype',
            get_string('asset:assettype', 'local_contentchecker'), [
                'model3d' => get_string('asset:type:model3d', 'local_contentchecker'),
                'diagram' => get_string('asset:type:diagram', 'local_contentchecker'),
                'embed' => get_string('asset:type:embed', 'local_contentchecker'),
            ]);

        $mform->addElement('select', 'viewer',
            get_string('asset:viewer', 'local_contentchecker'), [
                'modelviewer' => get_string('asset:viewer:modelviewer', 'local_contentchecker'),
                'image' => get_string('asset:viewer:image', 'local_contentchecker'),
                'mermaid' => get_string('asset:viewer:mermaid', 'local_contentchecker'),
                'iframe' => get_string('asset:viewer:iframe', 'local_contentchecker'),
            ]);
        $mform->addHelpButton('viewer', 'asset:viewer', 'local_contentchecker');

        $mform->addElement('text', 'url',
            get_string('asset:url', 'local_contentchecker'), ['size' => 80]);
        $mform->setType('url', PARAM_RAW_TRIMMED);
        $mform->hideIf('url', 'viewer', 'eq', 'mermaid');

        $mform->addElement('text', 'posterurl',
            get_string('asset:posterurl', 'local_contentchecker'), ['size' => 80]);
        $mform->setType('posterurl', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('posterurl', 'asset:posterurl', 'local_contentchecker');
        $mform->hideIf('posterurl', 'viewer', 'neq', 'modelviewer');

        $mform->addElement('textarea', 'body',
            get_string('asset:body', 'local_contentchecker'), ['rows' => 8, 'cols' => 60]);
        $mform->setType('body', PARAM_RAW);
        $mform->hideIf('body', 'viewer', 'neq', 'mermaid');

        $mform->addElement('textarea', 'description',
            get_string('asset:description', 'local_contentchecker'),
            ['rows' => 3, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addHelpButton('description', 'asset:description', 'local_contentchecker');

        // Most open-source 3D and diagram assets are licensed on condition
        // that attribution is shown, and the renderer shows it whenever set.
        $mform->addElement('text', 'licence',
            get_string('asset:licence', 'local_contentchecker'), ['size' => 40]);
        $mform->setType('licence', PARAM_TEXT);

        $mform->addElement('text', 'attribution',
            get_string('asset:attribution', 'local_contentchecker'), ['size' => 80]);
        $mform->setType('attribution', PARAM_TEXT);

        $mform->addElement('text', 'sortorder',
            get_string('asset:sortorder', 'local_contentchecker'), ['size' => 5]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $mform->addElement('advcheckbox', 'enabled',
            get_string('asset:enabled', 'local_contentchecker'));
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

        $viewer = $data['viewer'] ?? '';

        if ($viewer === 'mermaid') {
            if (trim($data['body'] ?? '') === '') {
                $errors['body'] = get_string('required');
            }
        } else if (trim($data['url'] ?? '') === '') {
            $errors['url'] = get_string('required');
        }

        return $errors;
    }
}
