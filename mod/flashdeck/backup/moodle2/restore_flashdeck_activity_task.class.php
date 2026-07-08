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
 * Restore task for mod_flashdeck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/mod/flashdeck/backup/moodle2/restore_flashdeck_stepslib.php');

/**
 * The flashdeck restore task.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_flashdeck_activity_task extends restore_activity_task {

    #[\Override]
    protected function define_my_settings() {
        // No activity-specific settings.
    }

    #[\Override]
    protected function define_my_steps() {
        $this->add_step(new restore_flashdeck_activity_structure_step('flashdeck_structure', 'flashdeck.xml'));
    }

    /**
     * Content areas whose encoded links must be decoded on restore.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('flashdeck', ['intro'], 'flashdeck'),
        ];
    }

    /**
     * Rules translating the encoded links back into real URLs.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('FLASHDECKVIEWBYID', '/mod/flashdeck/view.php?id=$1', 'course_module'),
            new restore_decode_rule('FLASHDECKINDEX', '/mod/flashdeck/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Log restore mappings for this module's events.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('flashdeck', 'view', 'view.php?id={course_module}', '{flashdeck}'),
        ];
    }
}
