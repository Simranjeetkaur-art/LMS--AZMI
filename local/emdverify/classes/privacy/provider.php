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

namespace local_emdverify\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * The plugin stores reviewer decisions. Course content is sent to a
 * self-hosted AI server; no personal data leaves Moodle.
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe stored data.
     *
     * @param collection $collection The collection to add to.
     * @return collection The populated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_emdverify_review', [
            'userid' => 'privacy:metadata:local_emdverify_review:userid',
            'decision' => 'privacy:metadata:local_emdverify_review:decision',
            'notes' => 'privacy:metadata:local_emdverify_review:notes',
            'timecreated' => 'privacy:metadata:local_emdverify_review:timecreated',
        ], 'privacy:metadata:local_emdverify_review');

        $collection->add_external_location_link('aiserver', [
            'content' => 'privacy:metadata:aiserver:content',
        ], 'privacy:metadata:aiserver');

        return $collection;
    }

    /**
     * Contexts holding data for a user.
     *
     * @param int $userid The user id.
     * @return \core_privacy\local\request\contextlist The context list.
     */
    public static function get_contexts_for_userid(int $userid):
            \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {local_emdverify_review} rev
               JOIN {local_emdverify_claim} c ON c.id = rev.claimid
               JOIN {local_emdverify_run} run ON run.id = c.runid
               JOIN {context} ctx ON ctx.instanceid = run.courseid
                    AND ctx.contextlevel = :courselevel
              WHERE rev.userid = :userid",
            ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Users within a context.
     *
     * @param \core_privacy\local\request\userlist $userlist The user list.
     * @return void
     */
    public static function get_users_in_context(
            \core_privacy\local\request\userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT rev.userid
               FROM {local_emdverify_review} rev
               JOIN {local_emdverify_claim} c ON c.id = rev.claimid
               JOIN {local_emdverify_run} run ON run.id = c.runid
              WHERE run.courseid = :courseid",
            ['courseid' => $context->instanceid]);
    }

    /**
     * Export a user's decisions.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(
            \core_privacy\local\request\approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $rows = $DB->get_records_sql(
                "SELECT rev.id, rev.decision, rev.notes, rev.timecreated, c.claimtext
                   FROM {local_emdverify_review} rev
                   JOIN {local_emdverify_claim} c ON c.id = rev.claimid
                   JOIN {local_emdverify_run} run ON run.id = c.runid
                  WHERE run.courseid = :courseid AND rev.userid = :userid",
                ['courseid' => $context->instanceid, 'userid' => $userid]);

            if (!$rows) {
                continue;
            }
            $data = array_map(fn($r) => [
                'claim' => $r->claimtext,
                'decision' => $r->decision,
                'notes' => $r->notes,
                'timecreated' => \core_privacy\local\request\transform::datetime($r->timecreated),
            ], array_values($rows));

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_emdverify')], (object) ['reviews' => $data]);
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param \context $context The context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_course) {
            return;
        }
        $DB->delete_records_subquery('local_emdverify_review', 'claimid', 'id',
            "SELECT c.id
               FROM {local_emdverify_claim} c
               JOIN {local_emdverify_run} run ON run.id = c.runid
              WHERE run.courseid = :courseid",
            ['courseid' => $context->instanceid]);
    }

    /**
     * Delete a user's data.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(
            \core_privacy\local\request\approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            // delete_records_subquery() takes no extra WHERE clause, and PHP
            // silently drops surplus arguments -- passing a userid filter that
            // way would delete every reviewer's decisions in the course.
            $DB->delete_records_select('local_emdverify_review',
                "userid = :userid AND claimid IN (
                     SELECT c.id
                       FROM {local_emdverify_claim} c
                       JOIN {local_emdverify_run} run ON run.id = c.runid
                      WHERE run.courseid = :courseid)",
                ['userid' => $userid, 'courseid' => $context->instanceid]);
        }
    }

    /**
     * Delete data for a set of users.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(
            \core_privacy\local\request\approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $claimids = $DB->get_fieldset_sql(
            "SELECT c.id
               FROM {local_emdverify_claim} c
               JOIN {local_emdverify_run} run ON run.id = c.runid
              WHERE run.courseid = :courseid", ['courseid' => $context->instanceid]);
        if (!$claimids) {
            return;
        }
        [$claimsql, $claimparams] = $DB->get_in_or_equal($claimids, SQL_PARAMS_NAMED, 'cl');
        $DB->delete_records_select('local_emdverify_review',
            "userid $insql AND claimid $claimsql", $inparams + $claimparams);
    }
}
