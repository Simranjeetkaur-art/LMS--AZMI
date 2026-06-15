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

    if ($oldversion < 2026061500) {
        // Add research.track.
        $table = new xmldb_table('local_azmsi_research');
        $field = new xmldb_field('track', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'title');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add pipeline.updatedby.
        $table = new xmldb_table('local_azmsi_pipeline');
        $field = new xmldb_field('updatedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'stage_launch');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Create local_azmsi_application (mirrors db/install.xml).
        $table = new xmldb_table('local_azmsi_application');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('program', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'eMD');
            $table->add_field('stage', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'applied');
            $table->add_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'open');
            $table->add_field('aqe_quizid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('aqe_slot', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('decision', XMLDB_TYPE_CHAR, '32', null, null, null, null);
            $table->add_field('decisioneta', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('submittedon', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('aqe_quizid', XMLDB_KEY_FOREIGN, ['aqe_quizid'], 'quiz', ['id']);
            $table->add_index('stage', XMLDB_INDEX_NOTUNIQUE, ['stage']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026061500, 'local', 'azmsi');
    }

    if ($oldversion < 2026061600) {
        // Align the research child tables to the spec names + fields (AGENT_03 §3):
        // local_azmsi_milestones -> local_azmsi_research_milestone,
        // local_azmsi_documents  -> local_azmsi_research_doc.
        // These are empty scaffolding (the research tracker is AGENT_09), so a
        // drop-and-recreate is safe and avoids a fragile field-by-field migration.
        foreach (['local_azmsi_milestones', 'local_azmsi_documents'] as $oldname) {
            $old = new xmldb_table($oldname);
            if ($dbman->table_exists($old)) {
                $dbman->drop_table($old);
            }
        }

        $table = new xmldb_table('local_azmsi_research_milestone');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('researchid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('seq', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('descr', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('due', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('completedon', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('researchid', XMLDB_KEY_FOREIGN, ['researchid'], 'local_azmsi_research', ['id']);
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_azmsi_research_doc');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('researchid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('type', XMLDB_TYPE_CHAR, '32', null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'draft');
            $table->add_field('turnitin_status', XMLDB_TYPE_CHAR, '32', null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('researchid', XMLDB_KEY_FOREIGN, ['researchid'], 'local_azmsi_research', ['id']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026061600, 'local', 'azmsi');
    }

    return true;
}
