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
 * Course-content completeness analysis (Phase 4, production pipeline).
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Symlink-safe config include (plugin is symlinked into public/).
require(is_file(__DIR__ . '/../../config.php')
    ? __DIR__ . '/../../config.php'
    : (getenv('MOODLE_ROOT') ? rtrim(getenv('MOODLE_ROOT'), '/') . '/config.php' : '/var/www/moodle/config.php'));

use local_azmsi\local\completeness;

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
require_login();
$context = context_system::instance();
require_capability('local/azmsi:viewadminconsole', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/azmsi/completeness.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('contentcompleteness', 'local_azmsi'));
$PAGE->set_heading(get_string('contentcompleteness', 'local_azmsi'));

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_azmsi/completeness', completeness::analyze($course));
echo $OUTPUT->footer();
