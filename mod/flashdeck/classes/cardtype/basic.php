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
 * Basic two-sided card: front prompt, back answer.
 *
 * Content JSON: {"front": string, "frontformat": int, "back": string, "backformat": int}
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class basic extends card_type {

    #[\Override]
    public function get_identifier(): string {
        return 'basic';
    }

    #[\Override]
    public function add_form_fields(\moodleform $form, \MoodleQuickForm $mform, array $content): void {
        $editoroptions = ['maxfiles' => 0, 'enable_filemanagement' => false];

        $mform->addElement('editor', 'cardfront', get_string('cardfront', 'mod_flashdeck'), null, $editoroptions);
        $mform->setType('cardfront', PARAM_RAW);
        $mform->addHelpButton('cardfront', 'cardfront', 'mod_flashdeck');

        $mform->addElement('editor', 'cardback', get_string('cardback', 'mod_flashdeck'), null, $editoroptions);
        $mform->setType('cardback', PARAM_RAW);
    }

    #[\Override]
    public function form_defaults(array $content): array {
        return [
            'cardfront' => [
                'text' => $content['front'] ?? '',
                'format' => $content['frontformat'] ?? FORMAT_HTML,
            ],
            'cardback' => [
                'text' => $content['back'] ?? '',
                'format' => $content['backformat'] ?? FORMAT_HTML,
            ],
        ];
    }

    #[\Override]
    public function validate_form(array $data, array $files): array {
        $errors = [];
        if (trim(html_to_text($data['cardfront']['text'] ?? '', 0)) === '') {
            $errors['cardfront'] = get_string('errfacerequired', 'mod_flashdeck');
        }
        if (trim(html_to_text($data['cardback']['text'] ?? '', 0)) === '') {
            $errors['cardback'] = get_string('errfacerequired', 'mod_flashdeck');
        }
        return $errors;
    }

    #[\Override]
    public function process_form(\stdClass $data): array {
        return [
            'front' => $data->cardfront['text'],
            'frontformat' => (int) $data->cardfront['format'],
            'back' => $data->cardback['text'],
            'backformat' => (int) $data->cardback['format'],
        ];
    }

    #[\Override]
    public function validate_content(array $content): array {
        $problems = [];
        foreach (['front', 'back'] as $face) {
            if (!isset($content[$face]) || !is_string($content[$face]) || trim($content[$face]) === '') {
                $problems[] = get_string('errcontentface', 'mod_flashdeck', $face);
            }
        }
        return $problems;
    }

    #[\Override]
    public function get_template(): string {
        return 'mod_flashdeck/card_basic';
    }

    #[\Override]
    public function export_for_template(\stdClass $card, \context $context): array {
        $content = self::decode($card);
        $options = ['context' => $context, 'para' => false];

        return [
            'fronthtml' => format_text($content['front'] ?? '', $content['frontformat'] ?? FORMAT_HTML, $options),
            'backhtml' => format_text($content['back'] ?? '', $content['backformat'] ?? FORMAT_HTML, $options),
        ];
    }

    #[\Override]
    public function get_summary(\stdClass $card): string {
        $content = self::decode($card);
        return shorten_text(trim(html_to_text($content['front'] ?? '', 0)), 80);
    }
}
