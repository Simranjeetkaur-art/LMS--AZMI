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

namespace local_contentchecker\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_contentchecker.
 *
 * The personal data here is entirely about staff actions: who ran a check, who
 * approved or rejected a suggestion, who approved a question. No learner data
 * is stored at all -- the follow-up questions are marked in the browser and
 * deliberately record nothing.
 *
 * Deletion is handled with care around the audit trail. An approval that
 * changed clinical course content is an accreditation record, so the record of
 * the change is kept and the user is unlinked from it rather than the row being
 * destroyed. That keeps "this sentence was changed on this date" true while
 * removing the personal identifier, which is what the data protection
 * requirement actually asks for.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Describe the data this plugin stores.
     *
     * @param collection $collection The collection to add to.
     * @return collection The populated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_cchecker_checks', [
            'usermodified' => 'privacy:metadata:local_cchecker_checks:usermodified',
            'timequeued' => 'privacy:metadata:local_cchecker_checks:timequeued',
        ], 'privacy:metadata:local_cchecker_checks');

        $collection->add_database_table('local_cchecker_suggestions', [
            'decidedby' => 'privacy:metadata:local_cchecker_suggestions:decidedby',
            'decidedat' => 'privacy:metadata:local_cchecker_suggestions:decidedat',
            'notes' => 'privacy:metadata:local_cchecker_suggestions:notes',
        ], 'privacy:metadata:local_cchecker_suggestions');

        $collection->add_database_table('local_cchecker_audit', [
            'userid' => 'privacy:metadata:local_cchecker_audit:userid',
            'action' => 'privacy:metadata:local_cchecker_audit:action',
            'timecreated' => 'privacy:metadata:local_cchecker_audit:timecreated',
        ], 'privacy:metadata:local_cchecker_audit');

        $collection->add_database_table('local_cchecker_jobs', [
            'usermodified' => 'privacy:metadata:local_cchecker_jobs:usermodified',
            'timequeued' => 'privacy:metadata:local_cchecker_jobs:timequeued',
        ], 'privacy:metadata:local_cchecker_jobs');

        $collection->add_external_location_link('gpu', [
            'content' => 'privacy:metadata:gpu:content',
        ], 'privacy:metadata:gpu');

        return $collection;
    }

    /**
     * Contexts holding data for a user.
     *
     * @param int $userid The user.
     * @return contextlist The contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {local_cchecker_checks} ch
               JOIN {context} ctx ON ctx.instanceid = ch.courseid
                                 AND ctx.contextlevel = :courselevel
              WHERE ch.usermodified = :userid",
            ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]);

        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {local_cchecker_suggestions} s
               JOIN {local_cchecker_checks} ch ON ch.id = s.checkid
               JOIN {context} ctx ON ctx.instanceid = ch.courseid
                                 AND ctx.contextlevel = :courselevel
              WHERE s.decidedby = :userid",
            ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]);

        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {local_cchecker_audit} a
               JOIN {context} ctx ON ctx.instanceid = a.courseid
                                 AND ctx.contextlevel = :courselevel
              WHERE a.userid = :userid AND a.courseid > 0",
            ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]);

        // Site-level registries and background jobs.
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
              WHERE ctx.contextlevel = :systemlevel
                AND (EXISTS (SELECT 1 FROM {local_cchecker_jobs} j
                              WHERE j.usermodified = :userid1)
                  OR EXISTS (SELECT 1 FROM {local_cchecker_assets} ass
                              WHERE ass.usermodified = :userid2)
                  OR EXISTS (SELECT 1 FROM {local_cchecker_pronounce} p
                              WHERE p.usermodified = :userid3))",
            ['systemlevel' => CONTEXT_SYSTEM, 'userid1' => $userid,
             'userid2' => $userid, 'userid3' => $userid]);

        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist The userlist to populate.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context instanceof \context_course) {
            $userlist->add_from_sql('usermodified',
                'SELECT usermodified FROM {local_cchecker_checks} WHERE courseid = ?',
                [$context->instanceid]);

            $userlist->add_from_sql('decidedby',
                "SELECT s.decidedby
                   FROM {local_cchecker_suggestions} s
                   JOIN {local_cchecker_checks} ch ON ch.id = s.checkid
                  WHERE ch.courseid = ? AND s.decidedby IS NOT NULL",
                [$context->instanceid]);

            $userlist->add_from_sql('userid',
                'SELECT userid FROM {local_cchecker_audit} WHERE courseid = ?',
                [$context->instanceid]);
            return;
        }

        if ($context instanceof \context_system) {
            $userlist->add_from_sql('usermodified',
                'SELECT usermodified FROM {local_cchecker_jobs}', []);
            $userlist->add_from_sql('usermodified',
                'SELECT usermodified FROM {local_cchecker_assets}', []);
            $userlist->add_from_sql('usermodified',
                'SELECT usermodified FROM {local_cchecker_pronounce}', []);
        }
    }

    /**
     * Export a user's data.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof \context_course)) {
                continue;
            }

            $decisions = $DB->get_records_sql(
                "SELECT s.id, s.claim, s.verdict, s.decision, s.decidedat,
                        s.appliedtext, s.notes, s.itemname
                   FROM {local_cchecker_suggestions} s
                   JOIN {local_cchecker_checks} ch ON ch.id = s.checkid
                  WHERE ch.courseid = :courseid AND s.decidedby = :userid",
                ['courseid' => $context->instanceid, 'userid' => $userid]);

            if ($decisions) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_contentchecker'),
                     get_string('review:heading', 'local_contentchecker')],
                    (object) ['decisions' => array_values(array_map(fn($d) => [
                        'activity' => $d->itemname,
                        'claim' => $d->claim,
                        'verdict' => $d->verdict,
                        'decision' => $d->decision,
                        'applied_text' => $d->appliedtext,
                        'notes' => $d->notes,
                        'decided_at' => $d->decidedat ? transform::datetime($d->decidedat) : null,
                    ], $decisions))]);
            }

            $checks = $DB->get_records('local_cchecker_checks',
                ['courseid' => $context->instanceid, 'usermodified' => $userid],
                'timequeued', 'id, sectionnum, status, runmode, timequeued, numflagged');

            if ($checks) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_contentchecker'),
                     get_string('dashboard:checks', 'local_contentchecker')],
                    (object) ['checks' => array_values(array_map(fn($c) => [
                        'section' => $c->sectionnum,
                        'status' => $c->status,
                        'mode' => $c->runmode,
                        'flagged' => $c->numflagged,
                        'started' => transform::datetime($c->timequeued),
                    ], $checks))]);
            }
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

        if (!($context instanceof \context_course)) {
            return;
        }

        // The audit trail is unlinked rather than deleted: the record that a
        // clinical sentence changed on a given date must survive, even when the
        // identity of who approved it must not.
        $DB->set_field('local_cchecker_audit', 'userid', 0,
            ['courseid' => $context->instanceid]);

        $DB->execute(
            "UPDATE {local_cchecker_suggestions}
                SET decidedby = NULL, notes = NULL
              WHERE checkid IN (SELECT id FROM {local_cchecker_checks} WHERE courseid = ?)",
            [$context->instanceid]);

        $DB->set_field('local_cchecker_checks', 'usermodified', 0,
            ['courseid' => $context->instanceid]);
    }

    /**
     * Delete a user's data across their approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $DB->set_field('local_cchecker_jobs', 'usermodified', 0,
                    ['usermodified' => $userid]);
                $DB->set_field('local_cchecker_assets', 'usermodified', 0,
                    ['usermodified' => $userid]);
                $DB->set_field('local_cchecker_pronounce', 'usermodified', 0,
                    ['usermodified' => $userid]);
                continue;
            }
            if (!($context instanceof \context_course)) {
                continue;
            }

            $DB->set_field('local_cchecker_audit', 'userid', 0,
                ['courseid' => $context->instanceid, 'userid' => $userid]);

            $DB->execute(
                "UPDATE {local_cchecker_suggestions}
                    SET decidedby = NULL, notes = NULL
                  WHERE decidedby = ?
                    AND checkid IN (SELECT id FROM {local_cchecker_checks} WHERE courseid = ?)",
                [$userid, $context->instanceid]);

            $DB->set_field('local_cchecker_checks', 'usermodified', 0,
                ['courseid' => $context->instanceid, 'usermodified' => $userid]);
        }
    }

    /**
     * Delete data for several users in one context.
     *
     * @param approved_userlist $userlist The approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');

        if ($context instanceof \context_system) {
            foreach (['local_cchecker_jobs', 'local_cchecker_assets',
                      'local_cchecker_pronounce'] as $table) {
                $DB->execute("UPDATE {{$table}} SET usermodified = 0
                               WHERE usermodified {$insql}", $params);
            }
            return;
        }

        if (!($context instanceof \context_course)) {
            return;
        }

        $params['courseid'] = $context->instanceid;

        $DB->execute("UPDATE {local_cchecker_audit} SET userid = 0
                       WHERE courseid = :courseid AND userid {$insql}", $params);

        $DB->execute("UPDATE {local_cchecker_suggestions}
                         SET decidedby = NULL, notes = NULL
                       WHERE decidedby {$insql}
                         AND checkid IN (SELECT id FROM {local_cchecker_checks}
                                          WHERE courseid = :courseid)", $params);

        $DB->execute("UPDATE {local_cchecker_checks} SET usermodified = 0
                       WHERE courseid = :courseid AND usermodified {$insql}", $params);
    }
}
