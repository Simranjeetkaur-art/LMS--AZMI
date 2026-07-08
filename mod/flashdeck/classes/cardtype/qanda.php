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

namespace mod_flashdeck\cardtype;

/**
 * Q&A (open recall) card: free-form prompt, model answer, self-grade.
 *
 * Structurally a basic card plus optional marking guidance ("a strong
 * answer mentions …") shown alongside the model answer to support an
 * honest self-grade.
 *
 * Content JSON: basic fields plus {"guidance": string}
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qanda extends basic {

    #[\Override]
    public function get_identifier(): string {
        return 'qanda';
    }

    #[\Override]
    public function add_form_fields(\moodleform $form, \MoodleQuickForm $mform, array $content): void {
        parent::add_form_fields($form, $mform, $content);

        $mform->addElement('textarea', 'guidance', get_string('guidance', 'mod_flashdeck'),
            ['rows' => 3, 'cols' => 60]);
        $mform->setType('guidance', PARAM_TEXT);
        $mform->addHelpButton('guidance', 'guidance', 'mod_flashdeck');
    }

    #[\Override]
    public function form_defaults(array $content): array {
        return parent::form_defaults($content) + ['guidance' => $content['guidance'] ?? ''];
    }

    #[\Override]
    public function process_form(\stdClass $data): array {
        return parent::process_form($data) + ['guidance' => trim($data->guidance ?? '')];
    }

    #[\Override]
    public function get_template(): string {
        return 'mod_flashdeck/card_qanda';
    }

    #[\Override]
    public function export_for_template(\stdClass $card, \context $context): array {
        $content = self::decode($card);
        return parent::export_for_template($card, $context) + [
            'guidance' => $content['guidance'] ?? '',
        ];
    }
}
