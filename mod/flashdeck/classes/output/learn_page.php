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

use mod_flashdeck\cardtype\termdissection;
use mod_flashdeck\local\api;

/**
 * The spaced-repetition study session (Learn mode, the default view).
 *
 * The first due card is rendered server-side; the four-button grade bar
 * is a real form posting to view.php, so studying works end-to-end
 * without JavaScript. The learn AMD module intercepts the form and runs
 * the same loop over AJAX.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learn_page implements \renderable, \templatable {

    /** @var \stdClass the flashdeck record */
    protected $deck;

    /** @var \cm_info the course module */
    protected $cm;

    /** @var \context_module module context */
    protected $context;

    /** @var int the learner */
    protected $userid;

    /**
     * Constructor.
     *
     * @param \stdClass $deck the flashdeck record
     * @param \cm_info $cm the course module
     * @param \context_module $context module context
     * @param int $userid the learner
     */
    public function __construct(\stdClass $deck, \cm_info $cm, \context_module $context, int $userid) {
        $this->deck = $deck;
        $this->cm = $cm;
        $this->context = $context;
        $this->userid = $userid;
    }

    #[\Override]
    public function export_for_template(\renderer_base $output) {
        global $DB;

        $payload = api::export_next_card($this->deck, $this->context, $this->userid, $output);

        $legendroles = [];
        foreach (termdissection::ROLES as $role) {
            $legendroles[] = [
                'role' => $role,
                'rolename' => get_string('role' . $role, 'mod_flashdeck'),
            ];
        }

        $viewurl = new \moodle_url('/mod/flashdeck/view.php', ['id' => $this->cm->id]);

        $modelinks = [];
        foreach (['cram', 'test', 'match'] as $mode) {
            if (!empty($this->deck->{'mode' . $mode})) {
                $modelinks[] = [
                    'url' => (new \moodle_url($viewurl, ['mode' => $mode]))->out(false),
                    'name' => get_string('mode' . $mode, 'mod_flashdeck'),
                ];
            }
        }

        return $payload + [
            'modelinks' => $modelinks,
            'canviewreports' => has_capability('mod/flashdeck:viewreports', $this->context),
            'reporturl' => (new \moodle_url('/mod/flashdeck/report.php', ['id' => $this->cm->id]))->out(false),
            'uniqid' => \html_writer::random_id('flashdeck'),
            'cmid' => $this->cm->id,
            'flashdeckid' => $this->deck->id,
            'intro' => format_module_intro('flashdeck', $this->deck, $this->cm->id),
            'hascards' => $payload['counts']['total'] > 0,
            'hasdissection' => $DB->record_exists('flashdeck_cards',
                ['deckid' => $this->deck->id, 'cardtype' => 'termdissection']),
            'legendroles' => $legendroles,
            'actionurl' => $viewurl->out(false),
            'sesskey' => sesskey(),
            'browseurl' => (new \moodle_url($viewurl, ['mode' => 'browse']))->out(false),
            'canmanage' => has_capability('mod/flashdeck:managecards', $this->context),
            'manageurl' => (new \moodle_url('/mod/flashdeck/edit.php', ['id' => $this->cm->id]))->out(false),
        ];
    }
}
