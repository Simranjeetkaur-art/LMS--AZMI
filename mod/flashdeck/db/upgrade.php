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
 * Upgrade steps for mod_flashdeck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute mod_flashdeck upgrade steps between the current and the target version.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool always true
 */
function xmldb_flashdeck_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026070803) {
        // Phase 4: grading, completion rules and study-mode settings.
        $table = new xmldb_table('flashdeck');
        $fields = [
            new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'newperday'),
            new xmldb_field('completionstudied', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'grade'),
            new xmldb_field('completionmastery', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0',
                'completionstudied'),
            new xmldb_field('modecram', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'completionmastery'),
            new xmldb_field('modetest', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'modecram'),
            new xmldb_field('modematch', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'modetest'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Phase 4: per-day study aggregates for streaks, points and analytics.
        $table = new xmldb_table('flashdeck_session');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('deckid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('daystart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reviews', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('correct', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('points', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('firstreview', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastreview', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('deckid', XMLDB_KEY_FOREIGN, ['deckid'], 'flashdeck', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('deckid-userid-daystart', XMLDB_INDEX_UNIQUE, ['deckid', 'userid', 'daystart']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026070803, 'flashdeck');
    }

    return true;
}
