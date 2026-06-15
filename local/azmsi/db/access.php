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
    'local/azmsi:mentor' => [
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

    // TODO(AGENT_03/AGENT_08): add the following capabilities when their
    // features land. Each will need a matching string in lang/en/local_azmsi.php.
    //   - local/azmsi:reviewapplications  Admissions reviewer — gates the AGENT_08
    //                                     applicant review UI (manager archetype).
    //   - local/azmsi:ws_readcatalog      WS read cap backing local_azmsi_get_program_catalog.
    //   - local/azmsi:ws_readoverview     WS read cap backing local_azmsi_get_student_overview.
    //   - local/azmsi:ws_readresearch     WS read cap backing local_azmsi_get_research_tracker.
    //   - local/azmsi:ws_readadmin        WS read cap backing local_azmsi_get_admin_kpis.
    //   (the :ws_* read caps gate the azmsi_ws external functions per AGENT_03;
    //    finalise the exact set against AGENT_03's WS function list.)
];
