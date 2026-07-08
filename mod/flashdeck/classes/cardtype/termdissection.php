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
 * Term dissection card: a medical term broken into colour-coded word parts.
 *
 * The front shows the whole term; the back reveals the dissection —
 * each part colour-coded by role (prefix, root, suffix, combining-vowel
 * link) with a caption naming the role and its meaning — plus the full
 * definition. Colours are theme CSS variables, never hardcoded.
 *
 * Content JSON:
 * {"term": string, "definition": string,
 *  "parts": [{"text": string, "role": "prefix|root|link|suffix", "meaning": string}, ...]}
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class termdissection extends card_type {

    /** @var string[] valid word-part roles, in legend order */
    const ROLES = ['prefix', 'root', 'link', 'suffix'];

    /** @var int maximum number of word parts a term can be split into */
    const MAXPARTS = 12;

    #[\Override]
    public function get_identifier(): string {
        return 'termdissection';
    }

    #[\Override]
    public function add_form_fields(\moodleform $form, \MoodleQuickForm $mform, array $content): void {
        $mform->addElement('text', 'term', get_string('term', 'mod_flashdeck'), ['size' => 40]);
        $mform->setType('term', PARAM_TEXT);
        $mform->addRule('term', null, 'required', null, 'client');
        $mform->addHelpButton('term', 'term', 'mod_flashdeck');

        $mform->addElement('textarea', 'definition', get_string('definition', 'mod_flashdeck'),
            ['rows' => 3, 'cols' => 60]);
        $mform->setType('definition', PARAM_TEXT);
        $mform->addRule('definition', null, 'required', null, 'client');

        $roleoptions = [];
        foreach (self::ROLES as $role) {
            $roleoptions[$role] = get_string('role' . $role, 'mod_flashdeck');
        }

        $repeatarray = [
            $mform->createElement('text', 'parttext', get_string('parttext', 'mod_flashdeck'), ['size' => 12]),
            $mform->createElement('select', 'partrole', get_string('partrole', 'mod_flashdeck'), $roleoptions),
            $mform->createElement('text', 'partmeaning', get_string('partmeaning', 'mod_flashdeck'), ['size' => 30]),
        ];
        $repeatoptions = [
            'parttext' => ['type' => PARAM_TEXT],
            'partrole' => ['type' => PARAM_ALPHA, 'default' => 'root'],
            'partmeaning' => ['type' => PARAM_TEXT],
        ];

        $repeats = max(count($content['parts'] ?? []) + 1, 4);
        $form->repeat_elements($repeatarray, $repeats, $repeatoptions, 'partcount', 'addparts', 2,
            get_string('addmoreparts', 'mod_flashdeck'), true);
    }

    #[\Override]
    public function form_defaults(array $content): array {
        $defaults = [
            'term' => $content['term'] ?? '',
            'definition' => $content['definition'] ?? '',
        ];
        foreach ($content['parts'] ?? [] as $i => $part) {
            $defaults['parttext'][$i] = $part['text'] ?? '';
            $defaults['partrole'][$i] = $part['role'] ?? 'root';
            $defaults['partmeaning'][$i] = $part['meaning'] ?? '';
        }
        return $defaults;
    }

    #[\Override]
    public function validate_form(array $data, array $files): array {
        $errors = [];
        $parts = $this->collect_parts((object) $data);

        if (count($parts) < 2) {
            $errors['parttext[0]'] = get_string('errminparts', 'mod_flashdeck');
        }
        foreach ($parts as $i => $part) {
            // A meaning caption is required for every real word part; the
            // combining-vowel link gets a standard caption automatically.
            if ($part['role'] !== 'link' && $part['meaning'] === '') {
                $errors["partmeaning[{$part['formindex']}]"] = get_string('errmeaningrequired', 'mod_flashdeck');
            }
        }
        return $errors;
    }

    #[\Override]
    public function process_form(\stdClass $data): array {
        $parts = [];
        foreach ($this->collect_parts($data) as $part) {
            unset($part['formindex']);
            if ($part['role'] === 'link' && $part['meaning'] === '') {
                $part['meaning'] = get_string('linkmeaningdefault', 'mod_flashdeck');
            }
            $parts[] = $part;
        }
        return [
            'term' => trim($data->term),
            'definition' => trim($data->definition),
            'parts' => $parts,
        ];
    }

    /**
     * Gather non-empty word parts from repeated form elements.
     *
     * @param \stdClass $data submitted form data
     * @return array list of ['text' =>, 'role' =>, 'meaning' =>, 'formindex' =>]
     */
    protected function collect_parts(\stdClass $data): array {
        $parts = [];
        $texts = (array) ($data->parttext ?? []);
        $roles = (array) ($data->partrole ?? []);
        $meanings = (array) ($data->partmeaning ?? []);
        foreach ($texts as $i => $text) {
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }
            $role = $roles[$i] ?? 'root';
            $parts[] = [
                'text' => $text,
                'role' => in_array($role, self::ROLES, true) ? $role : 'root',
                'meaning' => trim((string) ($meanings[$i] ?? '')),
                'formindex' => $i,
            ];
        }
        return $parts;
    }

    #[\Override]
    public function validate_content(array $content): array {
        $problems = [];
        if (!isset($content['term']) || trim((string) $content['term']) === '') {
            $problems[] = get_string('errtermrequired', 'mod_flashdeck');
        }
        if (!isset($content['definition']) || trim((string) $content['definition']) === '') {
            $problems[] = get_string('errdefinitionrequired', 'mod_flashdeck');
        }
        $parts = $content['parts'] ?? null;
        if (!is_array($parts) || count($parts) < 2) {
            $problems[] = get_string('errminparts', 'mod_flashdeck');
        } else if (count($parts) > self::MAXPARTS) {
            $problems[] = get_string('errmaxparts', 'mod_flashdeck', self::MAXPARTS);
        } else {
            foreach ($parts as $part) {
                if (!is_array($part) || trim((string) ($part['text'] ?? '')) === ''
                        || !in_array($part['role'] ?? '', self::ROLES, true)) {
                    $problems[] = get_string('errbadpart', 'mod_flashdeck');
                    break;
                }
            }
        }
        return $problems;
    }

    #[\Override]
    public function get_template(): string {
        return 'mod_flashdeck/card_termdissection';
    }

    #[\Override]
    public function export_for_template(\stdClass $card, \context $context): array {
        $content = self::decode($card);

        $parts = [];
        foreach ($content['parts'] ?? [] as $part) {
            $role = in_array($part['role'] ?? '', self::ROLES, true) ? $part['role'] : 'root';
            $parts[] = [
                'text' => $part['text'] ?? '',
                'role' => $role,
                'rolename' => get_string('role' . $role, 'mod_flashdeck'),
                'meaning' => $part['meaning'] ?? '',
            ];
        }

        return [
            'term' => $content['term'] ?? '',
            'definition' => $content['definition'] ?? '',
            'parts' => $parts,
        ];
    }

    #[\Override]
    public function get_summary(\stdClass $card): string {
        $content = self::decode($card);
        return shorten_text($content['term'] ?? '', 80);
    }

    #[\Override]
    public function get_ai_example(): ?array {
        return [
            'description' => get_string('aidesctermdissection', 'mod_flashdeck'),
            'content' => [
                'term' => 'cardiology',
                'definition' => 'The medical specialty devoted to the study of the heart.',
                'parts' => [
                    ['text' => 'cardi', 'role' => 'root', 'meaning' => 'heart'],
                    ['text' => 'o', 'role' => 'link', 'meaning' => 'combining vowel'],
                    ['text' => 'logy', 'role' => 'suffix', 'meaning' => 'study of'],
                ],
            ],
        ];
    }
}
