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

use mod_flashdeck\cardtype\card_type;

/**
 * The Match study mode: a timed pairing game.
 *
 * Pairs are derived from the deck's content: matching-card pairs,
 * term-dissection term/definition, and basic/Q&A cards whose two faces
 * are short enough to be tiles. Purely a practice game — nothing is
 * scheduled or persisted, and the timer counts up (no pressure clock).
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class match_page implements \renderable, \templatable {

    /** @var int maximum pairs on the board */
    const MAX_PAIRS = 6;

    /** @var int minimum pairs needed for a meaningful game */
    const MIN_PAIRS = 3;

    /** @var int longest text a tile can carry */
    const MAX_TILE_LENGTH = 60;

    /** @var \stdClass the flashdeck record */
    protected $deck;

    /** @var \cm_info the course module */
    protected $cm;

    /** @var \stdClass[] flashdeck_cards records */
    protected $cards;

    /**
     * Constructor.
     *
     * @param \stdClass $deck the flashdeck record
     * @param \cm_info $cm the course module
     * @param \stdClass[] $cards flashdeck_cards records
     */
    public function __construct(\stdClass $deck, \cm_info $cm, array $cards) {
        $this->deck = $deck;
        $this->cm = $cm;
        $this->cards = $cards;
    }

    /**
     * Extract short matchable pairs from a deck's cards.
     *
     * @param \stdClass[] $cards flashdeck_cards records
     * @return array list of [side a, side b]
     */
    public static function collect_pairs(array $cards): array {
        $pairs = [];
        foreach ($cards as $card) {
            $content = card_type::decode($card);
            switch ($card->cardtype) {
                case 'matching':
                    foreach ($content['pairs'] ?? [] as $pair) {
                        $pairs[] = [trim($pair['left'] ?? ''), trim($pair['right'] ?? '')];
                    }
                    break;
                case 'termdissection':
                    $pairs[] = [trim($content['term'] ?? ''), trim($content['definition'] ?? '')];
                    break;
                case 'basic':
                case 'qanda':
                    $pairs[] = [
                        trim(html_to_text($content['front'] ?? '', 0)),
                        trim(html_to_text($content['back'] ?? '', 0)),
                    ];
                    break;
            }
        }

        return array_values(array_filter($pairs, static function(array $pair): bool {
            return $pair[0] !== '' && $pair[1] !== ''
                && \core_text::strlen($pair[0]) <= self::MAX_TILE_LENGTH
                && \core_text::strlen($pair[1]) <= self::MAX_TILE_LENGTH;
        }));
    }

    #[\Override]
    public function export_for_template(\renderer_base $output) {
        $pairs = self::collect_pairs($this->cards);
        shuffle($pairs);
        $pairs = array_slice($pairs, 0, self::MAX_PAIRS);

        $tiles = [];
        foreach ($pairs as $index => $pair) {
            $tiles[] = ['pairid' => $index, 'text' => $pair[0]];
            $tiles[] = ['pairid' => $index, 'text' => $pair[1]];
        }
        shuffle($tiles);

        $viewurl = new \moodle_url('/mod/flashdeck/view.php', ['id' => $this->cm->id]);

        return [
            'uniqid' => \html_writer::random_id('flashdeck'),
            'playable' => count($pairs) >= self::MIN_PAIRS,
            'tiles' => $tiles,
            'paircount' => count($pairs),
            'learnurl' => $viewurl->out(false),
        ];
    }
}
