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

namespace mod_flashdeck\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: per-user spaced-repetition state in flashdeck_review.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('flashdeck_review', [
            'userid' => 'privacy:metadata:flashdeck_review:userid',
            'cardid' => 'privacy:metadata:flashdeck_review:cardid',
            'easefactor' => 'privacy:metadata:flashdeck_review:easefactor',
            'intervaldays' => 'privacy:metadata:flashdeck_review:intervaldays',
            'duedate' => 'privacy:metadata:flashdeck_review:duedate',
            'repetitions' => 'privacy:metadata:flashdeck_review:repetitions',
            'lapses' => 'privacy:metadata:flashdeck_review:lapses',
            'state' => 'privacy:metadata:flashdeck_review:state',
            'lastgrade' => 'privacy:metadata:flashdeck_review:lastgrade',
            'lastreviewed' => 'privacy:metadata:flashdeck_review:lastreviewed',
        ], 'privacy:metadata:flashdeck_review');

        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :modlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'flashdeck'
                  JOIN {flashdeck} f ON f.id = cm.instance
                  JOIN {flashdeck_review} r ON r.deckid = f.id
                 WHERE r.userid = :userid";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['modlevel' => CONTEXT_MODULE, 'userid' => $userid]);

        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT r.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'flashdeck'
                  JOIN {flashdeck} f ON f.id = cm.instance
                  JOIN {flashdeck_review} r ON r.deckid = f.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            if (!$deckid = self::deckid_from_context($context)) {
                continue;
            }

            $sql = "SELECT r.*, c.cardtype, c.content
                      FROM {flashdeck_review} r
                      JOIN {flashdeck_cards} c ON c.id = r.cardid
                     WHERE r.deckid = :deckid AND r.userid = :userid
                  ORDER BY r.id";
            $reviews = [];
            foreach ($DB->get_records_sql($sql, ['deckid' => $deckid, 'userid' => $userid]) as $review) {
                $card = (object) ['content' => $review->content, 'cardtype' => $review->cardtype];
                $summary = \mod_flashdeck\cardtype\manager::exists($review->cardtype)
                    ? \mod_flashdeck\cardtype\manager::get($review->cardtype)->get_summary($card)
                    : '';
                $reviews[] = (object) [
                    'card' => $summary,
                    'easefactor' => $review->easefactor,
                    'intervaldays' => $review->intervaldays,
                    'duedate' => $review->duedate ? transform::datetime($review->duedate) : null,
                    'repetitions' => $review->repetitions,
                    'lapses' => $review->lapses,
                    'state' => $review->state,
                    'lastgrade' => $review->lastgrade,
                    'lastreviewed' => $review->lastreviewed ? transform::datetime($review->lastreviewed) : null,
                ];
            }

            if ($reviews) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:reviewspath', 'mod_flashdeck')],
                    (object) ['reviews' => $reviews]
                );
            }
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        if ($deckid = self::deckid_from_context($context)) {
            $DB->delete_records('flashdeck_review', ['deckid' => $deckid]);
        }
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            if ($deckid = self::deckid_from_context($context)) {
                $DB->delete_records('flashdeck_review', ['deckid' => $deckid, 'userid' => $userid]);
            }
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        if (!$deckid = self::deckid_from_context($context)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['deckid'] = $deckid;
        $DB->delete_records_select('flashdeck_review', "deckid = :deckid AND userid {$insql}", $params);
    }

    /**
     * Resolve the flashdeck instance id behind a module context.
     *
     * @param \context_module $context the module context
     * @return int|null the flashdeck id, or null if not a flashdeck
     */
    protected static function deckid_from_context(\context_module $context): ?int {
        $cm = get_coursemodule_from_id('flashdeck', $context->instanceid);
        return $cm ? (int) $cm->instance : null;
    }
}
