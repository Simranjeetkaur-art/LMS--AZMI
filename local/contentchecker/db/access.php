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
 * Capabilities for local_contentchecker.
 *
 * Archetypes here set the DEFAULT role mapping only. No role is ever named in
 * plugin code -- every page, AJAX endpoint and web service gates on these
 * capabilities, so a site admin can re-map them freely through the normal role
 * management UI. db/install.php additionally grants :manage and :view to any
 * existing custom role that already holds moodle/course:manageactivities, which
 * is how a site-defined "Faculty" role with no matching archetype is picked up.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Read the content-checker dashboard, ledgers and diffs for a course.
    'local/contentchecker:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // The primary editor capability: run checks, use enrichment tooling, pick
    // publish templates, manage follow-up questions. Mirrors the roles that
    // already hold moodle/course:manageactivities and moodle/course:update.
    'local/contentchecker:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'riskbitmask' => RISK_XSS,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Decide on an AI suggestion (approve / reject / edit-and-approve).
    // Approving WRITES to live medical course content, so this is deliberately
    // separable from :manage -- a site can grant the dashboard without granting
    // the authority to change what students read.
    'local/contentchecker:approve' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'riskbitmask' => RISK_XSS | RISK_DATALOSS,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Queue a whole-course or multi-course background run. This consumes a
    // shared single GPU for a long time, so it is not implied by :manage.
    'local/contentchecker:runbackground' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Site-wide oversight: status across every course.
    'local/contentchecker:viewsitereports' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Manage the evidence corpus, the enrichment asset registry and the TTS
    // pronunciation dictionary. All site-level configuration.
    'local/contentchecker:manageconfig' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'riskbitmask' => RISK_CONFIG,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
