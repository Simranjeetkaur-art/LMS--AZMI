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

namespace format_emd;

use format_emd\local\master_template;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for the eMD course format.
 *
 * @package    format_emd
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * When a new course using the eMD format is created, stamp the master template
     * (Course Introduction + Week 1 + Final Exam) onto it.
     *
     * Guarded so it only runs for an empty, freshly-created eMD course — it never
     * touches a course that already has activities (e.g. one being restored), and a
     * failure is logged but never blocks course creation.
     *
     * @param \core\event\course_created $event
     * @return void
     */
    public static function course_created(\core\event\course_created $event): void {
        global $DB;

        try {
            $course = get_course($event->objectid);

            if ($course->format !== 'emd') {
                return;
            }
            // Only populate a brand-new, empty course (skip restores/imports/copies).
            if ($DB->record_exists('course_modules', ['course' => $course->id])) {
                return;
            }

            master_template::apply_to_course($course);
        } catch (\Throwable $e) {
            debugging('format_emd: master template apply failed for course '
                . $event->objectid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
