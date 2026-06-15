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
 * Upgrade steps for local_azmsi.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run the local_azmsi upgrade steps.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool
 */
function xmldb_local_azmsi_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // TODO(AGENT_08): create table local_azmsi_application as an upgrade step
    // (applicant + AQE stage; fields mirrored from the TODO in db/install.xml).
    // Guard with a version bump, e.g.:
    //   if ($oldversion < 20260801XX) {
    //       $table = new xmldb_table('local_azmsi_application');
    //       // ... add fields/keys ... then:
    //       if (!$dbman->table_exists($table)) {
    //           $dbman->create_table($table);
    //       }
    //       upgrade_plugin_savepoint(true, 20260801XX, 'local', 'azmsi');
    //   }

    return true;
}
