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
 * The audit trail for a course: who decided what, when, and what changed.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This plugin is symlinked into the Moodle tree, so __DIR__ resolves to the
// real path outside it and the conventional relative require misses config.php
// entirely. Fall back the same way the other AZMSI plugins do.
require(is_file(__DIR__ . '/../../../config.php')
    ? __DIR__ . '/../../../config.php'
    : (getenv('MOODLE_ROOT')
        ? rtrim(getenv('MOODLE_ROOT'), '/') . '/config.php'
        : '/var/www/moodle/config.php'));

use local_contentchecker\local\audit;

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('local/contentchecker:view', $context);

$PAGE->set_url(new moodle_url('/local/contentchecker/audit.php',
    ['courseid' => $course->id]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('audit:heading', 'local_contentchecker'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('coursedashboard', 'local_contentchecker'),
    new moodle_url('/local/contentchecker/index.php', ['courseid' => $course->id]));
$PAGE->navbar->add(get_string('audit:heading', 'local_contentchecker'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('audit:heading', 'local_contentchecker'));

$rows = audit::for_course((int) $course->id);

if (!$rows) {
    echo $OUTPUT->notification(get_string('audit:none', 'local_contentchecker'),
        \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->head = [
        get_string('audit:when', 'local_contentchecker'),
        get_string('audit:who', 'local_contentchecker'),
        get_string('audit:what', 'local_contentchecker'),
        get_string('audit:before', 'local_contentchecker'),
        get_string('audit:after', 'local_contentchecker'),
    ];
    $table->attributes['class'] = 'table generaltable cct-audit';

    foreach ($rows as $row) {
        $table->data[] = [
            userdate($row->timecreated),
            fullname($row),
            s($row->objecttype) . ': ' . s($row->action),
            shorten_text(s((string) $row->beforetext), 300),
            shorten_text(s((string) $row->aftertext), 300),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
