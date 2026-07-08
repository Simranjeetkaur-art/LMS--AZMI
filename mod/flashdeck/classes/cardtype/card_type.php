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
 * Base class every flashdeck card type extends.
 *
 * A card type owns three things only: how a card's content JSON is
 * authored (form fields), how it is validated, and how its two faces
 * render. The study loop, scheduler and progress tracking never look
 * inside a card, which is what keeps the engine content-agnostic.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class card_type {

    /**
     * The registry identifier, e.g. 'basic'. Must match the cardtype column value.
     *
     * @return string
     */
    abstract public function get_identifier(): string;

    /**
     * Human-readable type name for pickers and lists.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('cardtype' . $this->get_identifier(), 'mod_flashdeck');
    }

    /**
     * Add this type's authoring fields to the card editor form.
     *
     * @param \moodleform $form the wrapping form (needed for repeat_elements)
     * @param \MoodleQuickForm $mform the quickform to add elements to
     * @param array $content existing content JSON (empty array for a new card)
     */
    abstract public function add_form_fields(\moodleform $form, \MoodleQuickForm $mform, array $content): void;

    /**
     * Map existing content JSON onto form element defaults.
     *
     * @param array $content decoded content JSON
     * @return array element name => default value
     */
    abstract public function form_defaults(array $content): array;

    /**
     * Validate submitted editor form data.
     *
     * @param array $data submitted values
     * @param array $files submitted files
     * @return array element name => error message
     */
    abstract public function validate_form(array $data, array $files): array;

    /**
     * Turn validated form data into the content array stored as JSON.
     *
     * @param \stdClass $data submitted form data
     * @return array content payload
     */
    abstract public function process_form(\stdClass $data): array;

    /**
     * Structurally validate a content payload (used by import and the seeder,
     * where data does not come through the editor form).
     *
     * @param array $content decoded content JSON
     * @return array list of human-readable problems; empty means valid
     */
    abstract public function validate_content(array $content): array;

    /**
     * Mustache template that renders both faces of this card type.
     *
     * @return string template name, e.g. 'mod_flashdeck/card_basic'
     */
    abstract public function get_template(): string;

    /**
     * Build the context for {@see get_template()}.
     *
     * @param \stdClass $card the flashdeck_cards record
     * @param \context $context for output formatting
     * @return array template context
     */
    abstract public function export_for_template(\stdClass $card, \context $context): array;

    /**
     * One-line plain-text summary of the card front, for teacher lists.
     *
     * @param \stdClass $card the flashdeck_cards record
     * @return string
     */
    abstract public function get_summary(\stdClass $card): string;

    /**
     * Decode a card record's content JSON defensively.
     *
     * @param \stdClass $card the flashdeck_cards record
     * @return array decoded payload, empty array if malformed
     */
    public static function decode(\stdClass $card): array {
        $content = json_decode($card->content ?? '', true);
        return is_array($content) ? $content : [];
    }
}
