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

namespace local_contentchecker\event;

/**
 * Fired when a verification check finishes, whether it succeeded or failed.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_completed extends \core\event\base {

    /**
     * Initialise the event.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_cchecker_checks';
    }

    /**
     * The event name.
     *
     * @return string Localised name.
     */
    public static function get_name(): string {
        return get_string('event:checkcompleted', 'local_contentchecker');
    }

    /**
     * A human-readable description.
     *
     * @return string The description.
     */
    public function get_description(): string {
        $status = $this->other['status'] ?? 'unknown';
        $flagged = (int) ($this->other['numflagged'] ?? 0);
        return "Content checker check with id '{$this->objectid}' finished with status "
            . "'{$status}', flagging {$flagged} item(s) for review.";
    }

    /**
     * Where the event happened.
     *
     * @return \moodle_url The relevant URL.
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/contentchecker/index.php',
            ['courseid' => $this->courseid]);
    }
}
