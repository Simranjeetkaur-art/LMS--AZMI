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

/**
 * Teacher card-management view: card list, ordering, add buttons.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_page implements \renderable, \templatable {

    /** @var \stdClass the flashdeck record */
    protected $deck;

    /** @var \cm_info the course module */
    protected $cm;

    /** @var \stdClass[] flashdeck_cards records in position order */
    protected $cards;

    /**
     * Constructor.
     *
     * @param \stdClass $deck the flashdeck record
     * @param \cm_info $cm the course module
     * @param \stdClass[] $cards flashdeck_cards records in position order
     */
    public function __construct(\stdClass $deck, \cm_info $cm, array $cards) {
        $this->deck = $deck;
        $this->cm = $cm;
        $this->cards = $cards;
    }

    #[\Override]
    public function export_for_template(\renderer_base $output) {
        $baseurl = new \moodle_url('/mod/flashdeck/edit.php', ['id' => $this->cm->id, 'sesskey' => sesskey()]);

        $rows = [];
        $cards = array_values($this->cards);
        $total = count($cards);
        foreach ($cards as $i => $card) {
            $known = manager::exists($card->cardtype);
            $type = $known ? manager::get($card->cardtype) : null;
            $rows[] = [
                'cardid' => $card->id,
                'position' => $i + 1,
                'typename' => $known ? $type->get_display_name() : $card->cardtype,
                'summary' => $known ? $type->get_summary($card) : get_string('errunknowncardtype', 'mod_flashdeck', $card->cardtype),
                'tags' => $card->tags,
                'editurl' => (new \moodle_url('/mod/flashdeck/editcard.php',
                    ['id' => $this->cm->id, 'cardid' => $card->id]))->out(false),
                'deleteurl' => (new \moodle_url($baseurl, ['action' => 'delete', 'cardid' => $card->id]))->out(false),
                'upurl' => (new \moodle_url($baseurl, ['action' => 'moveup', 'cardid' => $card->id]))->out(false),
                'downurl' => (new \moodle_url($baseurl, ['action' => 'movedown', 'cardid' => $card->id]))->out(false),
                'isfirst' => $i === 0,
                'islast' => $i === $total - 1,
            ];
        }

        $addbuttons = [];
        foreach (manager::get_types() as $identifier => $type) {
            $addbuttons[] = [
                'url' => (new \moodle_url('/mod/flashdeck/editcard.php',
                    ['id' => $this->cm->id, 'type' => $identifier]))->out(false),
                'name' => $type->get_display_name(),
            ];
        }

        return [
            'cards' => $rows,
            'hascards' => !empty($rows),
            'cardcount' => $total,
            'addbuttons' => $addbuttons,
            'seedurl' => (new \moodle_url($baseurl, ['action' => 'seed']))->out(false),
            'copyurl' => (new \moodle_url('/mod/flashdeck/edit.php',
                ['id' => $this->cm->id, 'action' => 'copyfrom']))->out(false),
            'importurl' => (new \moodle_url('/mod/flashdeck/import.php', ['id' => $this->cm->id]))->out(false),
            'exportjsonurl' => (new \moodle_url('/mod/flashdeck/export.php',
                ['id' => $this->cm->id, 'format' => 'json']))->out(false),
            'exportcsvurl' => (new \moodle_url('/mod/flashdeck/export.php',
                ['id' => $this->cm->id, 'format' => 'csv']))->out(false),
            'viewurl' => (new \moodle_url('/mod/flashdeck/view.php', ['id' => $this->cm->id]))->out(false),
        ];
    }
}
