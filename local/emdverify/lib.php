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
 * Library hooks for local_emdverify.
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add a "Content verification" item to the course secondary navigation.
 *
 * Teachers, faculty and managers reach the ledger from the course itself; no
 * CLI and no code access is involved anywhere in the workflow.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course.
 * @param context_course $context The course context.
 * @return void
 */
function local_emdverify_extend_navigation_course(navigation_node $navigation,
        stdClass $course, context_course $context): void {
    if (!has_capability('local/emdverify:view', $context)) {
        return;
    }

    $navigation->add(
        get_string('ledger', 'local_emdverify'),
        new moodle_url('/local/emdverify/index.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'emdverify',
        new pix_icon('i/checkpermissions', '')
    );
}
