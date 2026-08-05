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

namespace local_contentchecker\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_contentchecker\local\job_manager;

/**
 * Queues a background whole-course or multi-course verification job.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_job extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course id')),
        ]);
    }

    /**
     * Queue the job.
     *
     * @param array $courseids Courses to verify.
     * @return array The job id and how much work it covers.
     */
    public static function execute(array $courseids): array {
        ['courseids' => $courseids] = self::validate_parameters(self::execute_parameters(),
            ['courseids' => $courseids]);

        if (!$courseids) {
            throw new \moodle_exception('error:nothingtocheck', 'local_contentchecker');
        }

        // Every course is validated and capability-checked here; job_manager
        // checks again per course as it fans out, so a course the caller cannot
        // edit is skipped rather than silently included.
        foreach ($courseids as $courseid) {
            $context = \context_course::instance($courseid);
            self::validate_context($context);
            require_capability('local/contentchecker:runbackground', $context);
        }

        $job = job_manager::queue($courseids);

        return [
            'jobid' => (int) $job->id,
            'status' => $job->status,
            'numchecks' => (int) $job->numchecks,
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure The return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'jobid' => new external_value(PARAM_INT, 'The new job id'),
            'status' => new external_value(PARAM_ALPHA, 'queued|running|complete|failed'),
            'numchecks' => new external_value(PARAM_INT, 'How many week checks were queued'),
        ]);
    }
}
