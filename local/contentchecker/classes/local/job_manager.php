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

use local_contentchecker\task\verify_course_adhoc;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates and tracks background verification jobs.
 *
 * A whole course is ten weeks and several hundred model calls; that cannot run
 * inside a page request and it should not run as one enormous adhoc task
 * either. A job therefore fans out into one task per week, so cron makes
 * progress in bounded steps, a single bad week cannot lose the whole job, and
 * the editor sees the count climb.
 *
 * The last task to finish is the one that marks the job complete and sends the
 * notification.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_manager {

    /**
     * Queue a background job over one or more courses.
     *
     * @param array $courseids Courses to verify.
     * @param int|null $userid Who to notify; defaults to the current user.
     * @return \stdClass The job row.
     */
    public static function queue(array $courseids, ?int $userid = null): \stdClass {
        global $DB, $USER;

        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        if (!$courseids) {
            throw new \coding_exception('A job needs at least one course.');
        }

        $userid = $userid ?? (int) $USER->id;
        $now = time();

        $job = (object) [
            'scope' => count($courseids) > 1 ? 'multicourse' : 'course',
            'courseids' => json_encode($courseids),
            'status' => 'queued',
            'numchecks' => 0,
            'numdone' => 0,
            'numflagged' => 0,
            'notified' => 0,
            'usermodified' => $userid,
            'timequeued' => $now,
        ];
        $job->id = $DB->insert_record('local_cchecker_jobs', $job);

        $queued = 0;
        foreach ($courseids as $courseid) {
            // Capability is re-checked per course: a job spanning courses must
            // not let a user reach one they cannot edit.
            $context = \context_course::instance($courseid, IGNORE_MISSING);
            if (!$context || !has_capability('local/contentchecker:runbackground',
                    $context, $userid)) {
                continue;
            }

            foreach (self::sections_with_content($courseid) as $sectionnum) {
                $checkid = $DB->insert_record('local_cchecker_checks', (object) [
                    'courseid' => $courseid,
                    'sectionnum' => $sectionnum,
                    'cmid' => 0,
                    'jobid' => $job->id,
                    'runmode' => 'background',
                    'status' => 'queued',
                    'usermodified' => $userid,
                    'timequeued' => $now,
                ]);

                $task = new verify_course_adhoc();
                $task->set_custom_data(['checkid' => $checkid, 'jobid' => $job->id]);
                $task->set_userid($userid);
                \core\task\manager::queue_adhoc_task($task);
                $queued++;
            }
        }

        $job->numchecks = $queued;
        if ($queued === 0) {
            $job->status = 'failed';
            $job->errormsg = get_string('error:nothingtocheck', 'local_contentchecker');
            $job->timefinished = $now;
        }
        $DB->update_record('local_cchecker_jobs', $job);

        return $job;
    }

    /**
     * Section numbers in a course that actually hold checkable content.
     *
     * Empty sections are skipped so a job's progress count reflects real work
     * rather than being padded with instant no-ops.
     *
     * @param int $courseid Course id.
     * @return array List of section numbers.
     */
    protected static function sections_with_content(int $courseid): array {
        $sections = [];
        foreach (content_source::for_course($courseid) as $item) {
            $sections[$item->sectionnum] = true;
        }
        unset($sections[0]);
        $keys = array_keys($sections);
        sort($keys);
        return $keys;
    }

    /**
     * Record that one of a job's checks has finished.
     *
     * @param int $jobid The job.
     * @return void
     */
    public static function check_finished(int $jobid): void {
        global $DB;

        if (!$jobid) {
            return;
        }

        $job = $DB->get_record('local_cchecker_jobs', ['id' => $jobid]);
        if (!$job || in_array($job->status, ['complete', 'cancelled'], true)) {
            return;
        }

        // Counted from the check rows rather than incremented, so a task that
        // runs twice after a cron retry cannot overshoot the total.
        $job->numdone = $DB->count_records_select('local_cchecker_checks',
            'jobid = :jobid AND status IN (:c, :f)',
            ['jobid' => $jobid, 'c' => 'complete', 'f' => 'failed']);
        $job->numflagged = (int) $DB->get_field_sql(
            'SELECT COALESCE(SUM(numflagged), 0) FROM {local_cchecker_checks} WHERE jobid = :jobid',
            ['jobid' => $jobid]);
        $job->status = 'running';
        if ($job->timestarted === null) {
            $job->timestarted = time();
        }

        if ($job->numdone >= $job->numchecks) {
            $job->status = 'complete';
            $job->timefinished = time();
        }
        $DB->update_record('local_cchecker_jobs', $job);

        if ($job->status === 'complete' && !$job->notified) {
            self::notify($job);
        }
    }

    /**
     * Tell the editor who queued the job that it has finished.
     *
     * @param \stdClass $job The completed job row.
     * @return void
     */
    protected static function notify(\stdClass $job): void {
        global $DB;

        $courseids = json_decode($job->courseids, true) ?: [];
        $first = reset($courseids);

        $url = count($courseids) === 1
            ? new \moodle_url('/local/contentchecker/index.php', ['courseid' => $first])
            : new \moodle_url('/local/contentchecker/admin.php');

        $a = (object) [
            'courses' => count($courseids),
            'checks' => $job->numchecks,
            'flagged' => $job->numflagged,
            'url' => $url->out(false),
        ];

        $message = new \core\message\message();
        $message->component = 'local_contentchecker';
        $message->name = 'jobcomplete';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $job->usermodified;
        $message->subject = get_string('notify:jobcomplete:subject', 'local_contentchecker');
        $message->fullmessage = get_string('notify:jobcomplete:body', 'local_contentchecker', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = get_string('notify:jobcomplete:bodyhtml',
            'local_contentchecker', $a);
        $message->smallmessage = get_string('notify:jobcomplete:small',
            'local_contentchecker', $a);
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = get_string('pluginname', 'local_contentchecker');

        if (message_send($message)) {
            $DB->set_field('local_cchecker_jobs', 'notified', 1, ['id' => $job->id]);
        }
    }
}
