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
 * Review moderation (Admin console → Reviews). Approve/reject learner ratings.
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

require_login();
$context = context_system::instance();
require_capability('local/azmsi:moderatereviews', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/azmsi/reviews.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('reviews', 'local_azmsi'));
$PAGE->set_heading(get_string('reviews', 'local_azmsi'));

// Handle an approve/reject action.
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
if ($action && $id && confirm_sesskey()) {
    $status = ($action === 'approve') ? reviews::APPROVED : reviews::REJECTED;
    reviews::set_status($id, $status, $USER->id);
    redirect($PAGE->url, get_string('reviewmoderated', 'local_azmsi'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

/**
 * Render a 1-5 star value as filled/empty stars.
 *
 * @param int $n
 * @return string
 */
$stars = function (int $n): string {
    $n = max(0, min(5, $n));
    return $n ? str_repeat("\u{2605}", $n) . str_repeat("\u{2606}", 5 - $n) : '';
};

$items = [];
foreach (reviews::pending() as $r) {
    $items[] = [
        'id'                  => $r->id,
        'ratername'           => $r->ratername,
        'coursename'          => format_string($r->coursename),
        'hascourse'           => $r->coursestars > 0,
        'coursestars'         => $stars((int) $r->coursestars),
        'coursereview'        => format_text($r->coursereview ?? '', FORMAT_PLAIN),
        'hasinstructor'       => $r->instructorstars > 0,
        'instructorstars'     => $stars((int) $r->instructorstars),
        'instructorreview'    => format_text($r->instructorreview ?? '', FORMAT_PLAIN),
        'date'                => userdate($r->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
        'approveurl'          => (new moodle_url('/local/azmsi/reviews.php',
            ['action' => 'approve', 'id' => $r->id, 'sesskey' => sesskey()]))->out(false),
        'rejecturl'           => (new moodle_url('/local/azmsi/reviews.php',
            ['action' => 'reject', 'id' => $r->id, 'sesskey' => sesskey()]))->out(false),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_azmsi/reviews_page', [
    'count'      => count($items),
    'haspending' => !empty($items),
    'pending'    => $items,
]);
echo $OUTPUT->footer();
