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
 * Capabilities for local_azmsi — gate the faculty/admin/research portals.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // See the faculty portal (faculty dashboard, instructor course pages).
    'local/azmsi:viewfacultyportal' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // See the admin console (KPIs, admissions funnel, production pipeline).
    'local/azmsi:viewadminconsole' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Act as a research/dissertation mentor (review milestones & documents).
    'local/azmsi:mentorresearch' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Manage the course production pipeline rows.
    'local/azmsi:managepipeline' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'riskbitmask'  => RISK_CONFIG,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Review admissions applications + AQE (admissions reviewer, AGENT_08).
    'local/azmsi:reviewapplications' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Web service read capabilities. Granted only to the ws_consumer role /
    // service accounts (AGENT_01), never to ordinary roles; each backs the
    // matching azmsi_ws external function.

    // Read the program catalog (website + applicant portal token).
    'local/azmsi:ws_catalog' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],

    // Read a student's dashboard overview.
    'local/azmsi:ws_student' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],

    // Read faculty overview rollups.
    'local/azmsi:ws_faculty' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],

    // Read admin KPIs / pipeline rollups.
    'local/azmsi:ws_admin' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],

    // Read/write the applicant portal (apply token).
    'local/azmsi:ws_apply' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
