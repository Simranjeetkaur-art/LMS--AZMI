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
 * Image / label card for anatomy-style material.
 *
 * Two variants share one target region (an ellipse in percentage
 * coordinates so it scales with the image):
 *  - identify: the front shows the image with a marker on the region;
 *    the learner recalls the structure's name.
 *  - hotspot: the front asks the learner to click where the named
 *    structure is; JavaScript gives hit/miss feedback. Without
 *    JavaScript the card degrades to a static view whose reveal shows
 *    the marked region.
 *
 * The image lives in the 'cardimage' file area (itemid = card id),
 * served only through pluginfile.php with capability checks.
 *
 * Content JSON:
 * {"variant": "identify|hotspot", "label": string, "question": string,
 *  "alttext": string, "description": string,
 *  "region": {"cx": float, "cy": float, "r": float}}
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class imagelabel extends card_type {

    /** @var string[] the two interaction variants */
    const VARIANTS = ['identify', 'hotspot'];

    /** @var array filemanager options for the card image */
    const FILEOPTIONS = ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['web_image']];

    #[\Override]
    public function get_identifier(): string {
        return 'imagelabel';
    }

    #[\Override]
    public function add_form_fields(\moodleform $form, \MoodleQuickForm $mform, array $content): void {
        $variants = [];
        foreach (self::VARIANTS as $variant) {
            $variants[$variant] = get_string('variant' . $variant, 'mod_flashdeck');
        }
        $mform->addElement('select', 'variant', get_string('imagevariant', 'mod_flashdeck'), $variants);
        $mform->setDefault('variant', 'identify');

        $mform->addElement('filemanager', 'cardimage', get_string('imagefile', 'mod_flashdeck'),
            null, self::FILEOPTIONS);

        $mform->addElement('text', 'labeltext', get_string('labeltext', 'mod_flashdeck'), ['size' => 40]);
        $mform->setType('labeltext', PARAM_TEXT);
        $mform->addRule('labeltext', null, 'required', null, 'client');

        $mform->addElement('text', 'alttext', get_string('imagealttext', 'mod_flashdeck'), ['size' => 60]);
        $mform->setType('alttext', PARAM_TEXT);
        $mform->addHelpButton('alttext', 'imagealttext', 'mod_flashdeck');

        $mform->addElement('text', 'question', get_string('imagequestion', 'mod_flashdeck'), ['size' => 60]);
        $mform->setType('question', PARAM_TEXT);

        $region = [
            $mform->createElement('float', 'regioncx', get_string('regioncx', 'mod_flashdeck'), ['size' => 5]),
            $mform->createElement('float', 'regioncy', get_string('regioncy', 'mod_flashdeck'), ['size' => 5]),
            $mform->createElement('float', 'regionr', get_string('regionradius', 'mod_flashdeck'), ['size' => 5]),
        ];
        $mform->addGroup($region, 'regiongroup', get_string('regiongroup', 'mod_flashdeck'), ' ', false);
        $mform->addHelpButton('regiongroup', 'regiongroup', 'mod_flashdeck');
        $mform->setDefault('regioncx', 50);
        $mform->setDefault('regioncy', 50);
        $mform->setDefault('regionr', 8);

        $mform->addElement('textarea', 'description', get_string('imagedescription', 'mod_flashdeck'),
            ['rows' => 2, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);
    }

    #[\Override]
    public function form_defaults(array $content): array {
        $region = $content['region'] ?? [];
        return [
            'variant' => $content['variant'] ?? 'identify',
            'labeltext' => $content['label'] ?? '',
            'alttext' => $content['alttext'] ?? '',
            'question' => $content['question'] ?? '',
            'regioncx' => $region['cx'] ?? 50,
            'regioncy' => $region['cy'] ?? 50,
            'regionr' => $region['r'] ?? 8,
            'description' => $content['description'] ?? '',
        ];
    }

    #[\Override]
    public function validate_form(array $data, array $files): array {
        $errors = [];
        if (trim($data['labeltext'] ?? '') === '') {
            $errors['labeltext'] = get_string('errlabelrequired', 'mod_flashdeck');
        }
        if (trim($data['alttext'] ?? '') === '') {
            $errors['alttext'] = get_string('erralttextrequired', 'mod_flashdeck');
        }
        if (empty($data['cardimage']) || !file_get_all_files_in_draftarea((int) $data['cardimage'])) {
            $errors['cardimage'] = get_string('errimagerequired', 'mod_flashdeck');
        }
        $cx = (float) ($data['regioncx'] ?? -1);
        $cy = (float) ($data['regioncy'] ?? -1);
        $r = (float) ($data['regionr'] ?? -1);
        if ($cx < 0 || $cx > 100 || $cy < 0 || $cy > 100 || $r < 1 || $r > 50) {
            $errors['regiongroup'] = get_string('errregionrange', 'mod_flashdeck');
        }
        return $errors;
    }

    #[\Override]
    public function process_form(\stdClass $data): array {
        $variant = in_array($data->variant ?? '', self::VARIANTS, true) ? $data->variant : 'identify';
        return [
            'variant' => $variant,
            'label' => trim($data->labeltext),
            'alttext' => trim($data->alttext),
            'question' => trim($data->question ?? ''),
            'description' => trim($data->description ?? ''),
            'region' => [
                'cx' => round((float) $data->regioncx, 2),
                'cy' => round((float) $data->regioncy, 2),
                'r' => round((float) $data->regionr, 2),
            ],
        ];
    }

    #[\Override]
    public function file_defaults(?\stdClass $card, \context_module $context): array {
        $draftitemid = file_get_submitted_draft_itemid('cardimage');
        file_prepare_draft_area($draftitemid, $context->id, 'mod_flashdeck', 'cardimage',
            $card->id ?? null, self::FILEOPTIONS);
        return ['cardimage' => $draftitemid];
    }

    #[\Override]
    public function process_files(\stdClass $data, \stdClass $card, \context_module $context): void {
        file_save_draft_area_files($data->cardimage, $context->id, 'mod_flashdeck', 'cardimage',
            $card->id, self::FILEOPTIONS);
    }

    #[\Override]
    public function validate_content(array $content): array {
        $problems = [];
        if (trim((string) ($content['label'] ?? '')) === '') {
            $problems[] = get_string('errlabelrequired', 'mod_flashdeck');
        }
        if (trim((string) ($content['alttext'] ?? '')) === '') {
            $problems[] = get_string('erralttextrequired', 'mod_flashdeck');
        }
        $region = $content['region'] ?? null;
        if (!is_array($region)
                || ($region['cx'] ?? -1) < 0 || ($region['cx'] ?? -1) > 100
                || ($region['cy'] ?? -1) < 0 || ($region['cy'] ?? -1) > 100
                || ($region['r'] ?? -1) < 1 || ($region['r'] ?? -1) > 50) {
            $problems[] = get_string('errregionrange', 'mod_flashdeck');
        }
        return $problems;
    }

    #[\Override]
    public function get_template(): string {
        return 'mod_flashdeck/card_imagelabel';
    }

    #[\Override]
    public function export_for_template(\stdClass $card, \context $context): array {
        $content = self::decode($card);
        $ishotspot = ($content['variant'] ?? 'identify') === 'hotspot';
        $label = $content['label'] ?? '';

        $imageurl = null;
        if ($context instanceof \context_module) {
            $files = get_file_storage()->get_area_files($context->id, 'mod_flashdeck', 'cardimage',
                $card->id, 'itemid, filepath, filename', false);
            if ($file = reset($files)) {
                $imageurl = \moodle_url::make_pluginfile_url($context->id, 'mod_flashdeck', 'cardimage',
                    $card->id, $file->get_filepath(), $file->get_filename())->out(false);
            }
        }

        $question = trim((string) ($content['question'] ?? ''));
        if ($question === '') {
            $question = $ishotspot
                ? get_string('hotspotprompt', 'mod_flashdeck', $label)
                : get_string('identifyprompt', 'mod_flashdeck');
        }

        $region = $content['region'] ?? ['cx' => 50, 'cy' => 50, 'r' => 8];

        return [
            'ishotspot' => $ishotspot,
            'hasimage' => $imageurl !== null,
            'imageurl' => $imageurl,
            'alttext' => $content['alttext'] ?? '',
            'question' => $question,
            'label' => $label,
            'description' => $content['description'] ?? '',
            'cx' => $region['cx'],
            'cy' => $region['cy'],
            'r' => $region['r'],
            'd' => 2 * (float) $region['r'],
        ];
    }

    #[\Override]
    public function get_summary(\stdClass $card): string {
        $content = self::decode($card);
        return shorten_text($content['label'] ?? '', 80);
    }
}
