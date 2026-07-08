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
 * Compare / contrast card for models and frameworks.
 *
 * The front shows the prompt, the two column titles and the aspect
 * labels — the learner recalls each cell. The back reveals the full
 * structured two-column comparison. Built for health-systems material
 * (e.g. Beveridge vs Bismarck) but content-agnostic.
 *
 * Content JSON:
 * {"prompt": string, "columna": string, "columnb": string,
 *  "rows": [{"aspect": string, "a": string, "b": string}, ...]}
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comparecontrast extends card_type {

    /** @var int minimum number of complete rows */
    const MIN_ROWS = 1;

    #[\Override]
    public function get_identifier(): string {
        return 'comparecontrast';
    }

    #[\Override]
    public function add_form_fields(\moodleform $form, \MoodleQuickForm $mform, array $content): void {
        $mform->addElement('text', 'prompt', get_string('compareprompt', 'mod_flashdeck'), ['size' => 60]);
        $mform->setType('prompt', PARAM_TEXT);
        $mform->addRule('prompt', null, 'required', null, 'client');

        $mform->addElement('text', 'columna', get_string('comparecolumna', 'mod_flashdeck'), ['size' => 30]);
        $mform->setType('columna', PARAM_TEXT);
        $mform->addRule('columna', null, 'required', null, 'client');

        $mform->addElement('text', 'columnb', get_string('comparecolumnb', 'mod_flashdeck'), ['size' => 30]);
        $mform->setType('columnb', PARAM_TEXT);
        $mform->addRule('columnb', null, 'required', null, 'client');

        $repeatarray = [
            $mform->createElement('text', 'compareaspect', get_string('compareaspect', 'mod_flashdeck', '{no}'),
                ['size' => 20]),
            $mform->createElement('text', 'comparea', get_string('comparea', 'mod_flashdeck', '{no}'),
                ['size' => 30]),
            $mform->createElement('text', 'compareb', get_string('compareb', 'mod_flashdeck', '{no}'),
                ['size' => 30]),
        ];
        $repeatoptions = [
            'compareaspect' => ['type' => PARAM_TEXT],
            'comparea' => ['type' => PARAM_TEXT],
            'compareb' => ['type' => PARAM_TEXT],
        ];
        $repeats = max(count($content['rows'] ?? []) + 1, 3);
        $form->repeat_elements($repeatarray, $repeats, $repeatoptions, 'rowcount', 'addrows', 2,
            get_string('addmorerows', 'mod_flashdeck'), true);
    }

    #[\Override]
    public function form_defaults(array $content): array {
        $defaults = [
            'prompt' => $content['prompt'] ?? '',
            'columna' => $content['columna'] ?? '',
            'columnb' => $content['columnb'] ?? '',
        ];
        foreach ($content['rows'] ?? [] as $i => $row) {
            $defaults['compareaspect'][$i] = $row['aspect'] ?? '';
            $defaults['comparea'][$i] = $row['a'] ?? '';
            $defaults['compareb'][$i] = $row['b'] ?? '';
        }
        return $defaults;
    }

    #[\Override]
    public function validate_form(array $data, array $files): array {
        return count($this->collect_rows((object) $data)) < self::MIN_ROWS
            ? ['compareaspect[0]' => get_string('errminrows', 'mod_flashdeck')] : [];
    }

    #[\Override]
    public function process_form(\stdClass $data): array {
        return [
            'prompt' => trim($data->prompt),
            'columna' => trim($data->columna),
            'columnb' => trim($data->columnb),
            'rows' => $this->collect_rows($data),
        ];
    }

    /**
     * Gather complete rows from the repeated form elements.
     *
     * @param \stdClass $data submitted form data
     * @return array list of ['aspect' =>, 'a' =>, 'b' =>]
     */
    protected function collect_rows(\stdClass $data): array {
        $rows = [];
        $aspects = (array) ($data->compareaspect ?? []);
        $avalues = (array) ($data->comparea ?? []);
        $bvalues = (array) ($data->compareb ?? []);
        foreach ($aspects as $i => $aspect) {
            $aspect = trim((string) $aspect);
            $a = trim((string) ($avalues[$i] ?? ''));
            $b = trim((string) ($bvalues[$i] ?? ''));
            if ($aspect !== '' && ($a !== '' || $b !== '')) {
                $rows[] = ['aspect' => $aspect, 'a' => $a, 'b' => $b];
            }
        }
        return $rows;
    }

    #[\Override]
    public function validate_content(array $content): array {
        $problems = [];
        foreach (['prompt', 'columna', 'columnb'] as $field) {
            if (trim((string) ($content[$field] ?? '')) === '') {
                $problems[] = get_string('errcomparefields', 'mod_flashdeck');
                break;
            }
        }
        $rows = $content['rows'] ?? null;
        if (!is_array($rows) || count($rows) < self::MIN_ROWS) {
            $problems[] = get_string('errminrows', 'mod_flashdeck');
        }
        return $problems;
    }

    #[\Override]
    public function get_template(): string {
        return 'mod_flashdeck/card_comparecontrast';
    }

    #[\Override]
    public function export_for_template(\stdClass $card, \context $context): array {
        $content = self::decode($card);
        return [
            'prompt' => $content['prompt'] ?? '',
            'columna' => $content['columna'] ?? '',
            'columnb' => $content['columnb'] ?? '',
            'rows' => array_values($content['rows'] ?? []),
        ];
    }

    #[\Override]
    public function get_summary(\stdClass $card): string {
        $content = self::decode($card);
        return shorten_text($content['prompt'] ?? '', 80);
    }
}
