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
 * Submit a course + instructor rating/review (Phase 3).
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Symlink-safe config include (plugin is symlinked into public/).
require(is_file(__DIR__ . '/../../config.php')
    ? __DIR__ . '/../../config.php'
    : (getenv('MOODLE_ROOT') ? rtrim(getenv('MOODLE_ROOT'), '/') . '/config.php' : '/var/www/moodle/config.php'));

use local_azmsi\local\reviews;

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
require_login($course);
require_sesskey();

$context = context_course::instance($course->id);
require_capability('local/azmsi:submitreview', $context);

$coursestars       = optional_param('coursestars', 0, PARAM_INT);
$coursereview      = optional_param('coursereview', '', PARAM_TEXT);
$instructorstars   = optional_param('instructorstars', 0, PARAM_INT);
$instructorreview  = optional_param('instructorreview', '', PARAM_TEXT);

// Resolve the instructor server-side — never trust a client-supplied id.
$instructorid = 0;
$teacherroles = array_keys(get_archetype_roles('editingteacher'));
if ($teacherroles) {
    $teachers = get_role_users($teacherroles, $context, false, 'u.id');
    if ($teachers) {
        $instructorid = (int) reset($teachers)->id;
    }
}

reviews::save($course->id, $USER->id, $instructorid,
    $coursestars, $coursereview, $instructorstars, $instructorreview);

redirect(
    course_get_url($course),
    get_string('reviewsubmitted', 'local_azmsi'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
