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
 * Cloze / type-the-answer card.
 *
 * Authors write plain text with blanks marked as [[answer]] or
 * [[answer|alternative|alternative]]. The front renders each blank as
 * a text input (native inputs, so the attempt works without
 * JavaScript); a Check button (JS) marks each blank with light
 * normalisation — trim, collapse whitespace, case-insensitive unless
 * configured otherwise — so trivial variation is never punished. The
 * back shows the completed text with accepted alternatives.
 *
 * Content JSON: {"text": string with [[...]] markers, "casesensitive": bool}
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cloze extends card_type {

    #[\Override]
    public function get_identifier(): string {
        return 'cloze';
    }

    #[\Override]
    public function add_form_fields(\moodleform $form, \MoodleQuickForm $mform, array $content): void {
        $mform->addElement('textarea', 'clozetext', get_string('clozetext', 'mod_flashdeck'),
            ['rows' => 5, 'cols' => 70]);
        $mform->setType('clozetext', PARAM_RAW);
        $mform->addRule('clozetext', null, 'required', null, 'client');
        $mform->addHelpButton('clozetext', 'clozetext', 'mod_flashdeck');

        $mform->addElement('advcheckbox', 'casesensitive', get_string('casesensitive', 'mod_flashdeck'));
        $mform->setDefault('casesensitive', 0);
    }

    #[\Override]
    public function form_defaults(array $content): array {
        return [
            'clozetext' => $content['text'] ?? '',
            'casesensitive' => (int) ($content['casesensitive'] ?? 0),
        ];
    }

    #[\Override]
    public function validate_form(array $data, array $files): array {
        return $this->validate_text($data['clozetext'] ?? '') ? [] :
            ['clozetext' => get_string('errclozeblank', 'mod_flashdeck')];
    }

    #[\Override]
    public function process_form(\stdClass $data): array {
        return [
            'text' => trim($data->clozetext),
            'casesensitive' => (bool) $data->casesensitive,
        ];
    }

    #[\Override]
    public function validate_content(array $content): array {
        return $this->validate_text((string) ($content['text'] ?? '')) ? [] :
            [get_string('errclozeblank', 'mod_flashdeck')];
    }

    /**
     * Whether the marked-up text contains at least one non-empty blank.
     *
     * @param string $text the authored text
     * @return bool
     */
    protected function validate_text(string $text): bool {
        foreach (self::parse($text) as $segment) {
            if ($segment['blank'] && $segment['answers']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Split marked-up text into text and blank segments.
     *
     * @param string $text text with [[answer|alt]] markers
     * @return array list of ['blank' => bool, 'text' => string, 'answers' => string[]]
     */
    public static function parse(string $text): array {
        $segments = [];
        $parts = preg_split('/(\[\[.+?\]\])/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        foreach ($parts as $part) {
            if (preg_match('/^\[\[(.+)\]\]$/s', $part, $matches)) {
                $answers = array_values(array_filter(array_map('trim', explode('|', $matches[1])),
                    static fn($answer) => $answer !== ''));
                $segments[] = ['blank' => true, 'text' => '', 'answers' => $answers];
            } else {
                $segments[] = ['blank' => false, 'text' => $part, 'answers' => []];
            }
        }
        return $segments;
    }

    /**
     * Light normalisation: trim, collapse whitespace, optionally lowercase.
     *
     * @param string $value the value to normalise
     * @param bool $casesensitive whether case matters
     * @return string
     */
    public static function normalise(string $value, bool $casesensitive): string {
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        return $casesensitive ? $value : \core_text::strtolower($value);
    }

    /**
     * Whether a typed answer matches any accepted answer.
     *
     * @param string $given the learner's answer
     * @param string[] $accepted accepted answers
     * @param bool $casesensitive whether case matters
     * @return bool
     */
    public static function matches(string $given, array $accepted, bool $casesensitive): bool {
        $given = self::normalise($given, $casesensitive);
        foreach ($accepted as $answer) {
            if (self::normalise($answer, $casesensitive) === $given) {
                return true;
            }
        }
        return false;
    }

    #[\Override]
    public function get_template(): string {
        return 'mod_flashdeck/card_cloze';
    }

    #[\Override]
    public function export_for_template(\stdClass $card, \context $context): array {
        $content = self::decode($card);
        $casesensitive = !empty($content['casesensitive']);

        $front = [];
        $back = [];
        $blankno = 0;
        foreach (self::parse((string) ($content['text'] ?? '')) as $segment) {
            if (!$segment['blank']) {
                $front[] = ['isblank' => false, 'text' => $segment['text']];
                $back[] = ['isblank' => false, 'text' => $segment['text']];
                continue;
            }
            $blankno++;
            $primary = $segment['answers'][0] ?? '';
            $alternatives = array_slice($segment['answers'], 1);
            $front[] = [
                'isblank' => true,
                'blankno' => $blankno,
                'answersjson' => json_encode($segment['answers']),
                'size' => min(24, max(4, \core_text::strlen($primary) + 2)),
            ];
            $back[] = [
                'isblank' => true,
                'text' => $primary,
                'alternatives' => $alternatives ? implode(', ', $alternatives) : null,
            ];
        }

        return [
            'frontsegments' => $front,
            'backsegments' => $back,
            'casesensitive' => (int) $casesensitive,
        ];
    }

    #[\Override]
    public function get_summary(\stdClass $card): string {
        $content = self::decode($card);
        $plain = preg_replace('/\[\[(.+?)(\|.*?)?\]\]/s', '…', (string) ($content['text'] ?? ''));
        return shorten_text(trim($plain), 80);
    }

    #[\Override]
    public function get_ai_example(): ?array {
        return [
            'description' => get_string('aidesccloze', 'mod_flashdeck'),
            'content' => [
                'text' => 'The powerhouse of the cell is the [[mitochondrion|mitochondria]].',
                'casesensitive' => false,
            ],
        ];
    }
}
