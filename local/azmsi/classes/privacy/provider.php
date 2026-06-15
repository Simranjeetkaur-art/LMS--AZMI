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

namespace local_azmsi\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core\context\system as context_system;

/**
 * Privacy provider for local_azmsi.
 *
 * Personal data lives in four system-context, userid-keyed tables: research
 * records (+ their milestones and documents) and admissions applications.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_azmsi_research', [
            'userid' => 'privacy:metadata:local_azmsi_research:userid',
            'title'  => 'privacy:metadata:local_azmsi_research:title',
            'track'  => 'privacy:metadata:local_azmsi_research:track',
        ], 'privacy:metadata:local_azmsi_research');

        $collection->add_database_table('local_azmsi_milestones', [
            'name'   => 'privacy:metadata:local_azmsi_milestones:name',
            'status' => 'privacy:metadata:local_azmsi_milestones:status',
        ], 'privacy:metadata:local_azmsi_milestones');

        $collection->add_database_table('local_azmsi_documents', [
            'title'          => 'privacy:metadata:local_azmsi_documents:title',
            'turnitinstatus' => 'privacy:metadata:local_azmsi_documents:turnitinstatus',
        ], 'privacy:metadata:local_azmsi_documents');

        $collection->add_database_table('local_azmsi_application', [
            'userid'  => 'privacy:metadata:local_azmsi_application:userid',
            'program' => 'privacy:metadata:local_azmsi_application:program',
            'stage'   => 'privacy:metadata:local_azmsi_application:stage',
        ], 'privacy:metadata:local_azmsi_application');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the given user.
     *
     * All AZMSI personal data is stored at system context.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();

        $hasdata = $DB->record_exists('local_azmsi_research', ['userid' => $userid])
            || $DB->record_exists('local_azmsi_application', ['userid' => $userid]);
        if ($hasdata) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \core\context\system) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_azmsi_research}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_azmsi_application}', []);
    }

    /**
     * Export all user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!self::approved_system_context($contextlist)) {
            return;
        }
        $context = context_system::instance();
        $userid = $contextlist->get_user()->id;

        // Research records, each with its milestones and documents.
        $records = $DB->get_records('local_azmsi_research', ['userid' => $userid]);
        $researchout = [];
        foreach ($records as $r) {
            $milestones = $DB->get_records('local_azmsi_milestones', ['researchid' => $r->id]);
            $documents = $DB->get_records('local_azmsi_documents', ['researchid' => $r->id]);
            $researchout[] = [
                'title'        => $r->title,
                'track'        => $r->track,
                'status'       => $r->status,
                'progress'     => $r->progress,
                'timecreated'  => $r->timecreated ? \core_privacy\local\request\transform::datetime($r->timecreated) : null,
                'milestones'   => array_values(array_map(static fn($m) => [
                    'name'          => $m->name,
                    'status'        => $m->status,
                    'duedate'       => $m->duedate ? \core_privacy\local\request\transform::datetime($m->duedate) : null,
                    'completeddate' => $m->completeddate
                        ? \core_privacy\local\request\transform::datetime($m->completeddate) : null,
                ], $milestones)),
                'documents'    => array_values(array_map(static fn($d) => [
                    'title'          => $d->title,
                    'status'         => $d->status,
                    'turnitinstatus' => $d->turnitinstatus,
                ], $documents)),
            ];
        }
        if ($researchout) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_azmsi'), 'research'],
                (object) ['records' => $researchout]
            );
        }

        // Admissions applications.
        $apps = $DB->get_records('local_azmsi_application', ['userid' => $userid]);
        if ($apps) {
            $appout = array_values(array_map(static fn($a) => [
                'program'     => $a->program,
                'stage'       => $a->stage,
                'status'      => $a->status,
                'aqe_slot'    => $a->aqe_slot ? \core_privacy\local\request\transform::datetime($a->aqe_slot) : null,
                'submittedon' => $a->submittedon ? \core_privacy\local\request\transform::datetime($a->submittedon) : null,
            ], $apps));
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_azmsi'), 'application'],
                (object) ['records' => $appout]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \core\context\system) {
            return;
        }
        $DB->delete_records('local_azmsi_milestones', []);
        $DB->delete_records('local_azmsi_documents', []);
        $DB->delete_records('local_azmsi_research', []);
        $DB->delete_records('local_azmsi_application', []);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (!self::approved_system_context($contextlist)) {
            return;
        }
        self::delete_for_userids([$contextlist->get_user()->id]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof \core\context\system) {
            return;
        }
        self::delete_for_userids($userlist->get_userids());
    }

    /**
     * Delete research (+children) and applications for a set of users.
     *
     * @param int[] $userids
     */
    protected static function delete_for_userids(array $userids): void {
        global $DB;
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Remove milestones + documents of the users' research, then the research.
        $researchids = $DB->get_fieldset_select('local_azmsi_research', 'id', "userid $insql", $inparams);
        if ($researchids) {
            [$rsql, $rparams] = $DB->get_in_or_equal($researchids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_azmsi_milestones', "researchid $rsql", $rparams);
            $DB->delete_records_select('local_azmsi_documents', "researchid $rsql", $rparams);
        }
        $DB->delete_records_select('local_azmsi_research', "userid $insql", $inparams);
        $DB->delete_records_select('local_azmsi_application', "userid $insql", $inparams);
    }

    /**
     * Whether the approved contextlist includes the system context.
     *
     * @param approved_contextlist $contextlist
     * @return bool
     */
    protected static function approved_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \core\context\system) {
                return true;
            }
        }
        return false;
    }
}
