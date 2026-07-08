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
 * Matching card: pair items across two columns.
 *
 * The front lists the left items, each with a select of every right
 * option (sorted alphabetically so the presentation is deterministic
 * but unrelated to the authored pair order). Native selects keep the
 * attempt fully accessible and working without JavaScript; a Check
 * button (JS) marks each row. The back lists the correct pairs.
 *
 * Content JSON: {"prompt": string, "pairs": [{"left": string, "right": string}, ...]}
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matching extends card_type {

    /** @var int minimum number of complete pairs */
    const MIN_PAIRS = 2;

    #[\Override]
    public function get_identifier(): string {
        return 'matching';
    }

    #[\Override]
    public function add_form_fields(\moodleform $form, \MoodleQuickForm $mform, array $content): void {
        $mform->addElement('text', 'prompt', get_string('matchprompt', 'mod_flashdeck'), ['size' => 60]);
        $mform->setType('prompt', PARAM_TEXT);
        $mform->addRule('prompt', null, 'required', null, 'client');

        $repeatarray = [
            $mform->createElement('text', 'matchleft', get_string('matchleft', 'mod_flashdeck', '{no}'),
                ['size' => 25]),
            $mform->createElement('text', 'matchright', get_string('matchright', 'mod_flashdeck', '{no}'),
                ['size' => 25]),
        ];
        $repeatoptions = [
            'matchleft' => ['type' => PARAM_TEXT],
            'matchright' => ['type' => PARAM_TEXT],
        ];
        $repeats = max(count($content['pairs'] ?? []) + 1, 4);
        $form->repeat_elements($repeatarray, $repeats, $repeatoptions, 'paircount', 'addpairs', 2,
            get_string('addmorepairs', 'mod_flashdeck'), true);
    }

    #[\Override]
    public function form_defaults(array $content): array {
        $defaults = ['prompt' => $content['prompt'] ?? ''];
        foreach ($content['pairs'] ?? [] as $i => $pair) {
            $defaults['matchleft'][$i] = $pair['left'] ?? '';
            $defaults['matchright'][$i] = $pair['right'] ?? '';
        }
        return $defaults;
    }

    #[\Override]
    public function validate_form(array $data, array $files): array {
        return count($this->collect_pairs((object) $data)) < self::MIN_PAIRS
            ? ['matchleft[0]' => get_string('errminpairs', 'mod_flashdeck')] : [];
    }

    #[\Override]
    public function process_form(\stdClass $data): array {
        return [
            'prompt' => trim($data->prompt),
            'pairs' => $this->collect_pairs($data),
        ];
    }

    /**
     * Gather complete pairs from the repeated form elements.
     *
     * @param \stdClass $data submitted form data
     * @return array list of ['left' =>, 'right' =>]
     */
    protected function collect_pairs(\stdClass $data): array {
        $pairs = [];
        $lefts = (array) ($data->matchleft ?? []);
        $rights = (array) ($data->matchright ?? []);
        foreach ($lefts as $i => $left) {
            $left = trim((string) $left);
            $right = trim((string) ($rights[$i] ?? ''));
            if ($left !== '' && $right !== '') {
                $pairs[] = ['left' => $left, 'right' => $right];
            }
        }
        return $pairs;
    }

    #[\Override]
    public function validate_content(array $content): array {
        $problems = [];
        $pairs = $content['pairs'] ?? null;
        if (!is_array($pairs) || count($pairs) < self::MIN_PAIRS) {
            $problems[] = get_string('errminpairs', 'mod_flashdeck');
        } else {
            foreach ($pairs as $pair) {
                if (trim((string) ($pair['left'] ?? '')) === '' || trim((string) ($pair['right'] ?? '')) === '') {
                    $problems[] = get_string('errminpairs', 'mod_flashdeck');
                    break;
                }
            }
        }
        return $problems;
    }

    #[\Override]
    public function get_template(): string {
        return 'mod_flashdeck/card_matching';
    }

    #[\Override]
    public function export_for_template(\stdClass $card, \context $context): array {
        $content = self::decode($card);
        $pairs = array_values($content['pairs'] ?? []);

        // Deterministic presentation: options sorted alphabetically, so
        // the order carries no information about the answer.
        $options = [];
        foreach ($pairs as $index => $pair) {
            $options[] = ['value' => $index, 'label' => $pair['right'] ?? ''];
        }
        \core_collator::asort_array_of_arrays_by_key($options, 'label');
        $options = array_values($options);

        $rows = [];
        foreach ($pairs as $index => $pair) {
            $rows[] = [
                'left' => $pair['left'] ?? '',
                'answer' => $index,
                'options' => $options,
            ];
        }

        return [
            'prompt' => $content['prompt'] ?? '',
            'rows' => $rows,
            'pairs' => $pairs,
        ];
    }

    #[\Override]
    public function get_summary(\stdClass $card): string {
        $content = self::decode($card);
        return shorten_text($content['prompt'] ?? '', 80);
    }
}
