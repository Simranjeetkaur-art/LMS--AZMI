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

namespace local_emdverify\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Queries over stored runs, claims and reviews.
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ledger {

    /** @var array Verdicts that a human should look at. */
    const FLAGGED = ['contradicted', 'contested', 'partially_supported', 'outdated'];

    /**
     * The most recent run for a week, if any.
     *
     * @param int $courseid Course id.
     * @param int $sectionnum Section number.
     * @return \stdClass|null The run row.
     */
    public static function latest_run(int $courseid, int $sectionnum): ?\stdClass {
        global $DB;
        $rows = $DB->get_records('local_emdverify_run',
            ['courseid' => $courseid, 'sectionnum' => $sectionnum],
            'timequeued DESC', '*', 0, 1);
        return $rows ? reset($rows) : null;
    }

    /**
     * Claims for a run, with evidence and this viewer's review attached.
     *
     * @param int $runid Run id.
     * @param int $userid Viewer.
     * @param string $filter all|flagged|unreviewed
     * @return array List of claim rows.
     */
    public static function claims(int $runid, int $userid, string $filter = 'all'): array {
        global $DB;

        $params = ['runid' => $runid, 'userid' => $userid];
        $where = 'c.runid = :runid';

        if ($filter === 'flagged') {
            [$insql, $inparams] = $DB->get_in_or_equal(self::FLAGGED, SQL_PARAMS_NAMED, 'v');
            $where .= " AND c.verdict $insql";
            $params += $inparams;
        } else if ($filter === 'unreviewed') {
            $where .= ' AND r.id IS NULL';
        }

        $sql = "SELECT c.*, r.decision, r.notes AS reviewnotes, r.timemodified AS reviewtime
                  FROM {local_emdverify_claim} c
             LEFT JOIN {local_emdverify_review} r
                    ON r.claimid = c.id AND r.userid = :userid
                 WHERE $where
              ORDER BY CASE c.verdict
                         WHEN 'contradicted' THEN 1
                         WHEN 'contested' THEN 2
                         WHEN 'partially_supported' THEN 3
                         WHEN 'outdated' THEN 4
                         WHEN 'needs_source' THEN 5
                         WHEN 'unsupported' THEN 6
                         ELSE 7 END, c.id";

        $claims = $DB->get_records_sql($sql, $params);
        if (!$claims) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($claims), SQL_PARAMS_NAMED);
        $evidence = $DB->get_records_select('local_emdverify_evidence',
            "claimid $insql", $inparams, 'score DESC');
        foreach ($claims as $claim) {
            $claim->evidence = array_values(array_filter($evidence,
                fn($e) => (int) $e->claimid === (int) $claim->id));
        }
        return array_values($claims);
    }

    /**
     * Record or update a reviewer's decision.
     *
     * @param int $claimid Claim id.
     * @param int $userid Reviewer.
     * @param string $decision agree|disagree|unsure
     * @param string $notes Optional notes.
     * @return void
     */
    public static function save_review(int $claimid, int $userid, string $decision,
            string $notes = ''): void {
        global $DB;

        $now = time();
        $existing = $DB->get_record('local_emdverify_review',
            ['claimid' => $claimid, 'userid' => $userid]);

        if ($existing) {
            $existing->decision = $decision;
            $existing->notes = $notes;
            $existing->timemodified = $now;
            $DB->update_record('local_emdverify_review', $existing);
            return;
        }

        $DB->insert_record('local_emdverify_review', (object) [
            'claimid' => $claimid,
            'userid' => $userid,
            'decision' => $decision,
            'notes' => $notes,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Agreement rate per machine verdict across a course.
     *
     * This is the number that decides whether the verifier is trustworthy. If
     * agreement on `contradicted` is low, the tool is red-flagging correct
     * material and should be treated as advisory only.
     *
     * @param int $courseid Course id.
     * @return array Rows of [verdict, agree, total, pct].
     */
    public static function accuracy(int $courseid): array {
        global $DB;

        $sql = "SELECT c.verdict,
                       SUM(CASE WHEN r.decision = 'agree' THEN 1 ELSE 0 END) AS agree,
                       COUNT(r.id) AS total
                  FROM {local_emdverify_claim} c
                  JOIN {local_emdverify_run} run ON run.id = c.runid
                  JOIN {local_emdverify_review} r
                    ON r.claimid = c.id AND r.decision <> 'unsure'
                 WHERE run.courseid = :courseid
              GROUP BY c.verdict
              ORDER BY c.verdict";

        $out = [];
        foreach ($DB->get_records_sql($sql, ['courseid' => $courseid]) as $row) {
            $out[] = [
                'verdict' => get_string('verdict:' . $row->verdict, 'local_emdverify'),
                'agree' => (int) $row->agree,
                'total' => (int) $row->total,
                'pct' => $row->total ? (int) round(100 * $row->agree / $row->total) : 0,
            ];
        }
        return $out;
    }

    /**
     * Per-week summary for a course.
     *
     * @param int $courseid Course id.
     * @return array Rows keyed by section number.
     */
    public static function course_summary(int $courseid): array {
        global $DB;

        $sql = "SELECT r.sectionnum, r.id AS runid, r.status, r.progress, r.numclaims,
                       r.numflagged, r.timefinished
                  FROM {local_emdverify_run} r
                  JOIN (SELECT sectionnum, MAX(timequeued) AS t
                          FROM {local_emdverify_run}
                         WHERE courseid = :c1
                      GROUP BY sectionnum) latest
                    ON latest.sectionnum = r.sectionnum AND latest.t = r.timequeued
                 WHERE r.courseid = :c2
              ORDER BY r.sectionnum";

        return $DB->get_records_sql($sql, ['c1' => $courseid, 'c2' => $courseid]);
    }
}
