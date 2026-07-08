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

/**
 * Backup task for mod_flashdeck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/mod/flashdeck/backup/moodle2/backup_flashdeck_stepslib.php');

/**
 * The flashdeck backup task.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_flashdeck_activity_task extends backup_activity_task {

    #[\Override]
    protected function define_my_settings() {
        // No activity-specific settings.
    }

    #[\Override]
    protected function define_my_steps() {
        $this->add_step(new backup_flashdeck_activity_structure_step('flashdeck_structure', 'flashdeck.xml'));
    }

    /**
     * Encode links to this module's scripts so restore can rewrite them.
     *
     * @param string $content some HTML text that eventually contains URLs
     * @return string the content with the URLs encoded
     */
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');

        $search = "/({$base}\/mod\/flashdeck\/index\.php\?id=)([0-9]+)/";
        $content = preg_replace($search, '$@FLASHDECKINDEX*$2@$', $content);

        $search = "/({$base}\/mod\/flashdeck\/view\.php\?id=)([0-9]+)/";
        $content = preg_replace($search, '$@FLASHDECKVIEWBYID*$2@$', $content);

        return $content;
    }
}
