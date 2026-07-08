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

use mod_flashdeck\local\api;

/**
 * Moodle App view: deck description, progress counts, browser hand-off.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Render the course-module view for the app.
     *
     * @param array $args cmid, courseid etc. from the app
     * @return array templates/javascript/otherdata structure
     */
    public static function mobile_course_view(array $args): array {
        global $DB, $USER, $OUTPUT, $CFG;

        $cmid = (int) $args['cmid'];
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'flashdeck');
        $context = \context_module::instance($cm->id);

        require_login($course, false, $cm);
        require_capability('mod/flashdeck:view', $context);

        $deck = $DB->get_record('flashdeck', ['id' => $cm->instance], '*', MUST_EXIST);
        $counts = api::get_counts($deck, $USER->id);
        [, , $mastery] = api::get_mastery($deck, $USER->id);

        $data = [
            'deck' => $deck,
            'cmid' => $cm->id,
            'description' => format_module_intro('flashdeck', $deck, $cm->id),
            'duenow' => $counts['duenow'],
            'learning' => $counts['learning'],
            'newremaining' => $counts['newremaining'],
            'total' => $counts['total'],
            'mastery' => $mastery,
            'streak' => api::get_streak($deck, $USER->id),
            'points' => api::get_points($deck, $USER->id),
            'viewurl' => $CFG->wwwroot . '/mod/flashdeck/view.php?id=' . $cm->id,
        ];

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_flashdeck/mobile_view', $data),
                ],
            ],
            'javascript' => '',
            'otherdata' => [],
            'files' => [],
        ];
    }
}
