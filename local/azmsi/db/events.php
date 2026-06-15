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
 * Event observers for local_azmsi — the write/react path (01_ARCHITECTURE.md §4).
 *
 * Observers must be cheap: anything heavy (aggregation, email, website cache
 * push) is queued as an adhoc task from inside the observer.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback'  => '\local_azmsi\observer::on_enrolment_created',
    ],
    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback'  => '\local_azmsi\observer::on_activity_completion',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback'  => '\local_azmsi\observer::on_quiz_submitted',
    ],
    [
        'eventname' => '\mod_assign\event\submission_graded',
        'callback'  => '\local_azmsi\observer::on_assignment_graded',
    ],
    [
        'eventname' => '\core\event\course_completed',
        'callback'  => '\local_azmsi\observer::on_course_completed',
    ],
];
