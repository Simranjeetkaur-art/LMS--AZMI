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

namespace local_contentchecker\local;

defined('MOODLE_INTERNAL') || die();

/**
 * The single gate every human decision on an AI suggestion passes through.
 *
 * Approve, reject and edit-and-approve all land here so that the capability
 * check, the audit record and the Moodle event can never be bypassed by adding
 * another entry point later. There is no method on this class that applies a
 * suggestion without a decision having been made by a named user.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class decision {

    /**
     * Record a decision, applying the edit when the decision is an approval.
     *
     * @param int $suggestionid The suggestion.
     * @param string $action approve|reject|edit.
     * @param string|null $edited Replacement text, required for edit.
     * @param string|null $notes Optional reviewer note.
     * @return array {ok, decision, applied, reason, suggestion}.
     */
    public static function record(int $suggestionid, string $action,
            ?string $edited = null, ?string $notes = null): array {
        global $DB, $USER;

        if (!in_array($action, ['approve', 'reject', 'edit'], true)) {
            throw new \coding_exception('Unknown decision action: ' . $action);
        }

        $suggestion = $DB->get_record('local_cchecker_suggestions',
            ['id' => $suggestionid], '*', MUST_EXIST);
        $check = $DB->get_record('local_cchecker_checks',
            ['id' => $suggestion->checkid], '*', MUST_EXIST);

        // Gated on the course the content actually lives in, not on whatever
        // course the caller claimed.
        $context = \context_course::instance((int) $check->courseid);
        require_capability('local/contentchecker:approve', $context);

        $applied = false;
        $reason = null;
        $before = null;
        $after = null;

        if ($action === 'reject') {
            $suggestion->decision = 'rejected';
            $suggestion->appliedtext = null;
        } else {
            $newtext = $action === 'edit'
                ? trim((string) $edited)
                : trim((string) $suggestion->suggestedtext);

            if ($newtext === '') {
                return [
                    'ok' => false,
                    'decision' => $suggestion->decision,
                    'applied' => false,
                    'reason' => 'apply:notext',
                    'suggestion' => $suggestion,
                ];
            }

            $result = applier::apply($suggestion, $newtext);
            $applied = $result['applied'];
            $reason = $result['reason'];
            $before = $result['before'];
            $after = $result['after'];

            $suggestion->decision = $action === 'edit' ? 'edited' : 'approved';
            $suggestion->appliedtext = $newtext;
            $suggestion->applied = $applied ? 1 : 0;
        }

        $suggestion->notes = $notes;
        $suggestion->decidedby = $USER->id;
        $suggestion->decidedat = time();
        $DB->update_record('local_cchecker_suggestions', $suggestion);

        // The audit record carries the before/after diff of the LIVE field when
        // the write went through, and the reason it did not when it failed.
        audit::log('suggestion', (int) $suggestion->id,
            $applied ? 'applied' : ($action === 'reject' ? 'rejected' : $suggestion->decision), [
                'courseid' => (int) $check->courseid,
                'cmid' => (int) $suggestion->cmid,
                'before' => $applied ? $before : $suggestion->currenttext,
                'after' => $applied ? $after : $suggestion->appliedtext,
                'detail' => [
                    'action' => $action,
                    'verdict' => $suggestion->verdict,
                    'applied' => $applied,
                    'reason' => $reason,
                    'notes' => $notes,
                ],
            ]);

        \local_contentchecker\event\suggestion_decided::create([
            'context' => $context,
            'objectid' => (int) $suggestion->id,
            'other' => [
                'decision' => $suggestion->decision,
                'applied' => $applied,
                'verdict' => $suggestion->verdict,
            ],
        ])->trigger();

        return [
            'ok' => true,
            'decision' => $suggestion->decision,
            'applied' => $applied,
            'reason' => $reason,
            'suggestion' => $suggestion,
        ];
    }
}
