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
 * Web service definitions for local_azmsi.
 *
 * Functions use the single-class form: 'classname' points at a class under
 * classes/external/ extending core_external\external_api with execute(),
 * execute_parameters() and execute_returns() (AGENT_00a §3).
 *
 * Only functions whose classes already exist are declared here so the plugin
 * installs cleanly. Add the rest as each class is implemented (AGENT_03):
 *   get_course_home, get_week_module, get_faculty_overview, get_admin_kpis,
 *   get_research_tracker, submit_application.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Only functions whose classes exist are declared (AGENT_03 §5). Remaining
// functions are added as each class lands: get_course_home, get_week_module,
// get_research_tracker, get_application_status, submit_application, schedule_aqe,
// update_pipeline_stage.
$functions = [
    'local_azmsi_get_program_catalog' => [
        'classname'    => 'local_azmsi\external\get_program_catalog',
        'description'  => 'Full Year -> Quarter -> Course tree with live/planned status and credits.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/azmsi:ws_catalog',
        'services'     => ['azmsi_ws'],
    ],
    'local_azmsi_get_student_overview' => [
        'classname'    => 'local_azmsi\external\get_student_overview',
        'description'  => 'Student dashboard rollup: per-course %, average, course list.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/azmsi:ws_student',
        'services'     => ['azmsi_ws'],
    ],
    'local_azmsi_get_admin_kpis' => [
        'classname'    => 'local_azmsi\external\get_admin_kpis',
        'description'  => 'Admin console KPI rollup (active students, applications, faculty, courses built).',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/azmsi:ws_admin',
        'services'     => ['azmsi_ws'],
    ],
    'local_azmsi_get_faculty_overview' => [
        'classname'    => 'local_azmsi\external\get_faculty_overview',
        'description'  => 'Faculty dashboard rollup: courses taught, student counts, grading queue, class health.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/azmsi:ws_faculty',
        'services'     => ['azmsi_ws'],
    ],
];

// Pre-built external service consumed by the website + applicant portal
// (server-side, dedicated token per consumer — see AGENT_01 / 01_ARCHITECTURE.md §3).
// Shipped DISABLED; AGENT_01 enables it + mints scoped tokens on staging.
$services = [
    'azmsi_ws' => [
        'functions'       => [
            'local_azmsi_get_program_catalog',
            'local_azmsi_get_student_overview',
            'local_azmsi_get_admin_kpis',
            'local_azmsi_get_faculty_overview',
        ],
        'restrictedusers' => 1,
        'enabled'         => 0,
        'shortname'       => 'azmsi_ws',
        'downloadfiles'   => 0,
        'uploadfiles'     => 0,
    ],
];
