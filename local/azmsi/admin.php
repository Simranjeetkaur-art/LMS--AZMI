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
 * AZMSI admin console (S12).
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(is_file(__DIR__ . '/../../../config.php')
    ? __DIR__ . '/../../../config.php'
    : (getenv('MOODLE_ROOT') ? rtrim(getenv('MOODLE_ROOT'), '/') . '/config.php' : '/var/www/moodle/config.php'));

require_login();
$context = context_system::instance();
require_capability('local/azmsi:viewadminconsole', $context);

$pageurl = new moodle_url('/local/azmsi/admin.php');

// Pipeline stage advance (cap + sesskey checked; the write itself is audited).
if (optional_param('action', '', PARAM_ALPHA) === 'advance') {
    require_sesskey();
    $courseid = required_param('courseid', PARAM_INT);
    $stage = required_param('stage', PARAM_ALPHAEXT);
    $value = required_param('value', PARAM_ALPHA);
    require_capability('local/azmsi:managepipeline', context_course::instance($courseid));
    \local_azmsi\local\pipeline::set_stage($courseid, $stage, $value, (int) $USER->id);
    redirect(
        $pageurl,
        get_string('pipelineupdated', 'local_azmsi'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('adminconsole', 'local_azmsi'));
$PAGE->set_heading(get_string('adminconsole', 'local_azmsi'));
$PAGE->add_body_class('azmsi-admin');

$canmanage = has_capability('local/azmsi:managepipeline', $context);
$output = $PAGE->get_renderer('local_azmsi');
echo $output->header();
echo $output->render(new \local_azmsi\output\admin_console($canmanage));
echo $output->footer();
