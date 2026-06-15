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

$functions = [
    'local_azmsi_get_program_catalog' => [
        'classname'   => 'local_azmsi\external\get_program_catalog',
        'description' => 'Full Year -> Quarter -> Course tree with live/planned status and credits.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
        'services'    => ['azmsi_ws'],
    ],
    'local_azmsi_get_student_overview' => [
        'classname'   => 'local_azmsi\external\get_student_overview',
        'description' => 'Student dashboard rollup: continue-card, per-course %, due-this-week, program map.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
        'services'    => ['azmsi_ws'],
    ],
];

// Pre-built external service consumed by the website + applicant portal
// (server-side, dedicated token per consumer — see AGENT_01 / 01_ARCHITECTURE.md §3).
$services = [
    'azmsi_ws' => [
        'functions'       => [
            'local_azmsi_get_program_catalog',
            'local_azmsi_get_student_overview',
        ],
        'restrictedusers' => 1,
        'enabled'         => 0, // Enable + mint tokens in AGENT_01, not on install.
        'shortname'       => 'azmsi_ws',
        'downloadfiles'   => 0,
        'uploadfiles'     => 0,
    ],
];
