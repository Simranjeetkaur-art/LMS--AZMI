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
 * Site-wide content checker oversight.
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
require_once($CFG->libdir . '/adminlib.php');

use local_contentchecker\local\dashboard;

admin_externalpage_setup('local_contentchecker_overview');

// admin_externalpage_setup already enforces the page's declared capability;
// this states the requirement locally so it survives the page being reached
// any other way.
require_capability('local/contentchecker:viewsitereports', context_system::instance());

$renderer = $PAGE->get_renderer('local_contentchecker');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('siteoverview', 'local_contentchecker'));

$jobs = $DB->get_records('local_cchecker_jobs', null, 'timequeued DESC', '*', 0, 10);
if ($jobs) {
    echo $OUTPUT->heading(get_string('jobs:recent', 'local_contentchecker'), 3);

    $table = new html_table();
    $table->head = [
        get_string('jobs:scope', 'local_contentchecker'),
        get_string('jobs:status', 'local_contentchecker'),
        get_string('jobs:progress', 'local_contentchecker'),
        get_string('jobs:flagged', 'local_contentchecker'),
        get_string('jobs:queued', 'local_contentchecker'),
    ];
    $table->attributes['class'] = 'table generaltable';

    foreach ($jobs as $job) {
        $courseids = json_decode($job->courseids, true) ?: [];
        $table->data[] = [
            get_string('jobs:scope:' . $job->scope, 'local_contentchecker',
                count($courseids)),
            $job->status,
            $job->numdone . ' / ' . $job->numchecks,
            $job->numflagged,
            userdate($job->timequeued),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->heading(get_string('dashboard:courses', 'local_contentchecker'), 3);
echo $renderer->site_overview(dashboard::site_overview());

echo $OUTPUT->footer();
