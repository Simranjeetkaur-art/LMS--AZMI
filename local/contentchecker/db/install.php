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
 * Install-time hook for local_contentchecker.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Grant the editor capabilities to roles that already edit course content.
 *
 * db/access.php archetypes cover the shipped roles, but a site-defined role --
 * "Faculty", say -- created from scratch has no archetype, so archetypes alone
 * would silently miss it and an admin would have to notice and fix it by hand.
 * Rather than naming any role, this looks up whichever roles genuinely hold
 * moodle/course:manageactivities and moodle/course:update at course level and
 * mirrors the grant onto them.
 *
 * Only roles with no explicit setting are touched, so an admin who has already
 * made a decision is never overridden. Everything remains adjustable afterwards
 * through the normal role management UI.
 *
 * @return bool Always true.
 */
function xmldb_local_contentchecker_install(): bool {
    global $CFG, $DB;

    require_once($CFG->libdir . '/accesslib.php');

    // db/install.php runs BEFORE upgrade_component_updated() loads this
    // plugin's capabilities into the database, so assign_capability() below
    // would fail with "capability not found". Defining them here first fixes
    // that; Moodle repeats the call moments later and it is idempotent.
    update_capabilities('local_contentchecker');

    $syscontext = context_system::instance();

    // Roles that can both add/edit activities and edit course settings.
    $editors = array_intersect(
        array_keys(get_roles_with_capability('moodle/course:manageactivities',
            CAP_ALLOW, $syscontext)),
        array_keys(get_roles_with_capability('moodle/course:update',
            CAP_ALLOW, $syscontext))
    );

    $grants = [
        'local/contentchecker:view',
        'local/contentchecker:manage',
        'local/contentchecker:approve',
        'local/contentchecker:runbackground',
    ];

    foreach ($editors as $roleid) {
        foreach ($grants as $capability) {
            $existing = $DB->get_record('role_capabilities', [
                'roleid' => $roleid,
                'capability' => $capability,
                'contextid' => $syscontext->id,
            ]);
            if ($existing) {
                continue;
            }
            assign_capability($capability, CAP_ALLOW, $roleid, $syscontext->id);
        }
    }

    return true;
}
