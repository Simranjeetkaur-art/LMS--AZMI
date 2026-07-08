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

namespace mod_flashdeck\output;

use mod_flashdeck\cardtype\manager;
use mod_flashdeck\cardtype\termdissection;

/**
 * Student study view of a deck.
 *
 * Phase 1: a sequential card browser with an accessible flip UI. The
 * spaced-repetition scheduler replaces the sequential order in Phase 2;
 * this renderable's contract (cards pre-rendered by their card type)
 * stays the same.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class study_page implements \renderable, \templatable {

    /** @var \stdClass the flashdeck record */
    protected $deck;

    /** @var \cm_info the course module */
    protected $cm;

    /** @var \stdClass[] flashdeck_cards records in position order */
    protected $cards;

    /** @var \context_module module context */
    protected $context;

    /** @var bool whether the viewer may manage cards */
    protected $canmanage;

    /**
     * Constructor.
     *
     * @param \stdClass $deck the flashdeck record
     * @param \cm_info $cm the course module
     * @param \stdClass[] $cards flashdeck_cards records in position order
     * @param \context_module $context module context
     * @param bool $canmanage whether the viewer may manage cards
     */
    public function __construct(\stdClass $deck, \cm_info $cm, array $cards,
            \context_module $context, bool $canmanage) {
        $this->deck = $deck;
        $this->cm = $cm;
        $this->cards = $cards;
        $this->context = $context;
        $this->canmanage = $canmanage;
    }

    #[\Override]
    public function export_for_template(\renderer_base $output) {
        $cardsout = [];
        $hasdissection = false;
        $index = 0;

        foreach ($this->cards as $card) {
            if (!manager::exists($card->cardtype)) {
                debugging("Skipping card {$card->id}: unknown card type '{$card->cardtype}'", DEBUG_DEVELOPER);
                continue;
            }
            $type = manager::get($card->cardtype);
            $index++;
            $cardsout[] = [
                'cardid' => $card->id,
                'cardtype' => $card->cardtype,
                'typename' => $type->get_display_name(),
                'index' => $index,
                'first' => $index === 1,
                'cardhtml' => $output->render_from_template($type->get_template(),
                    $type->export_for_template($card, $this->context)),
            ];
            $hasdissection = $hasdissection || ($card->cardtype === 'termdissection');
        }

        $legendroles = [];
        foreach (termdissection::ROLES as $role) {
            $legendroles[] = [
                'role' => $role,
                'rolename' => get_string('role' . $role, 'mod_flashdeck'),
            ];
        }

        return [
            'uniqid' => \html_writer::random_id('flashdeck'),
            'cmid' => $this->cm->id,
            'intro' => format_module_intro('flashdeck', $this->deck, $this->cm->id),
            'cards' => $cardsout,
            'cardcount' => count($cardsout),
            'hascards' => !empty($cardsout),
            'hasdissection' => $hasdissection,
            'legendroles' => $legendroles,
            'canmanage' => $this->canmanage,
            'manageurl' => (new \moodle_url('/mod/flashdeck/edit.php', ['id' => $this->cm->id]))->out(false),
        ];
    }
}
