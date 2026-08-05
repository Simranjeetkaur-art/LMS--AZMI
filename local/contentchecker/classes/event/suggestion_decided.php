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
 * Fired when a human decides on an AI suggestion.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suggestion_decided extends \core\event\base {

    /**
     * Initialise the event.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'local_cchecker_suggestions';
    }

    /**
     * The event name.
     *
     * @return string Localised name.
     */
    public static function get_name(): string {
        return get_string('event:suggestiondecided', 'local_contentchecker');
    }

    /**
     * A human-readable description.
     *
     * @return string The description.
     */
    public function get_description(): string {
        $decision = $this->other['decision'] ?? 'unknown';
        $applied = !empty($this->other['applied']) ? 'and applied it to live content'
            : 'without changing live content';
        return "The user with id '{$this->userid}' recorded the decision '{$decision}' on "
            . "content checker suggestion with id '{$this->objectid}' {$applied}.";
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
