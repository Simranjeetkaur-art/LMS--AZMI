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
 * Scheduled tasks for local_contentchecker.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        // Re-embeds reference sources whose content may have moved on. Weekly,
        // off-peak: every ingest is a run of embedding calls on the same single
        // GPU the live checks use.
        'classname' => 'local_contentchecker\task\refresh_corpus',
        'blocking' => 0,
        'minute' => '20',
        'hour' => '3',
        'day' => '*',
        'dayofweek' => '0',
        'month' => '*',
        'disabled' => 0,
    ],
    [
        // Releases queue slots abandoned by a PHP process that died mid-request.
        'classname' => 'local_contentchecker\task\reap_queue',
        'blocking' => 0,
        'minute' => '*/5',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
        'disabled' => 0,
    ],
];
