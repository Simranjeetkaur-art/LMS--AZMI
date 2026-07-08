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
 * Ordering / sequence card: arrange steps into the correct order.
 *
 * Steps are authored in the correct order and presented alphabetically
 * (deterministic, order-neutral). The learner assigns a position number
 * to each step with a native select — accessible and functional
 * without JavaScript; a Check button (JS) marks each. The back shows
 * the correct sequence as a numbered list.
 *
 * Content JSON: {"prompt": string, "items": [string, ...]} (correct order)
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ordering extends card_type {

    /** @var int minimum number of steps */
    const MIN_ITEMS = 2;

    #[\Override]
    public function get_identifier(): string {
        return 'ordering';
    }

    #[\Override]
    public function add_form_fields(\moodleform $form, \MoodleQuickForm $mform, array $content): void {
        $mform->addElement('text', 'prompt', get_string('orderprompt', 'mod_flashdeck'), ['size' => 60]);
        $mform->setType('prompt', PARAM_TEXT);
        $mform->addRule('prompt', null, 'required', null, 'client');

        $repeatarray = [
            $mform->createElement('text', 'orderitem', get_string('orderitem', 'mod_flashdeck', '{no}'),
                ['size' => 50]),
        ];
        $repeatoptions = ['orderitem' => ['type' => PARAM_TEXT]];
        $repeats = max(count($content['items'] ?? []) + 1, 4);
        $form->repeat_elements($repeatarray, $repeats, $repeatoptions, 'itemcount', 'additems', 2,
            get_string('addmoreitems', 'mod_flashdeck'), true);
    }

    #[\Override]
    public function form_defaults(array $content): array {
        $defaults = ['prompt' => $content['prompt'] ?? ''];
        foreach ($content['items'] ?? [] as $i => $item) {
            $defaults['orderitem'][$i] = $item;
        }
        return $defaults;
    }

    #[\Override]
    public function validate_form(array $data, array $files): array {
        return count($this->collect_items((object) $data)) < self::MIN_ITEMS
            ? ['orderitem[0]' => get_string('errminitems', 'mod_flashdeck')] : [];
    }

    #[\Override]
    public function process_form(\stdClass $data): array {
        return [
            'prompt' => trim($data->prompt),
            'items' => $this->collect_items($data),
        ];
    }

    /**
     * Gather non-empty steps in authored (correct) order.
     *
     * @param \stdClass $data submitted form data
     * @return string[]
     */
    protected function collect_items(\stdClass $data): array {
        $items = [];
        foreach ((array) ($data->orderitem ?? []) as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $items[] = $item;
            }
        }
        return $items;
    }

    #[\Override]
    public function validate_content(array $content): array {
        $items = $content['items'] ?? null;
        if (!is_array($items) || count(array_filter(array_map('trim', $items))) < self::MIN_ITEMS) {
            return [get_string('errminitems', 'mod_flashdeck')];
        }
        return [];
    }

    #[\Override]
    public function get_template(): string {
        return 'mod_flashdeck/card_ordering';
    }

    #[\Override]
    public function export_for_template(\stdClass $card, \context $context): array {
        $content = self::decode($card);
        $items = array_values($content['items'] ?? []);
        $count = count($items);

        $positions = [];
        for ($i = 1; $i <= $count; $i++) {
            $positions[] = ['value' => $i, 'label' => $i];
        }

        // Alphabetical presentation: deterministic and order-neutral.
        $scrambled = [];
        foreach ($items as $index => $item) {
            $scrambled[] = [
                'text' => $item,
                'answer' => $index + 1,
                'positions' => $positions,
            ];
        }
        \core_collator::asort_array_of_arrays_by_key($scrambled, 'text');

        return [
            'prompt' => $content['prompt'] ?? '',
            'scrambled' => array_values($scrambled),
            'ordered' => $items,
        ];
    }

    #[\Override]
    public function get_summary(\stdClass $card): string {
        $content = self::decode($card);
        return shorten_text($content['prompt'] ?? '', 80);
    }
}
