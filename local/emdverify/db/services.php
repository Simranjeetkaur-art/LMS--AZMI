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
 * External service definitions for local_emdverify.
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_emdverify_save_review' => [
        'classname' => 'local_emdverify\external\save_review',
        'description' => 'Record a reviewer decision on a verification finding.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/emdverify:adjudicate',
    ],
    'local_emdverify_get_run_status' => [
        'classname' => 'local_emdverify\external\get_run_status',
        'description' => 'Poll the progress of a verification run.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/emdverify:view',
    ],
];
