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

namespace local_azmsi\local;

use core\task\manager;
use local_azmsi\task\revalidate_website;

/**
 * Course production pipeline (local_azmsi_pipeline): the 6-stage build workflow.
 *
 * Single source for reading the pipeline (admin console) and writing a stage
 * (WS update_pipeline_stage + the console POST handler). Callers MUST enforce the
 * capability + sesskey before calling {@see self::set_stage()}; this class only
 * persists the audited write and fires the catalog refresh on launch.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pipeline {
    /** @var string[] The six ordered stage columns. */
    public const STAGES = ['stage_sme', 'stage_id', 'stage_video', 'stage_build', 'stage_qa', 'stage_launch'];

    /** @var string[] Valid stage values. */
    public const VALUES = ['queued', 'active', 'done'];

    /**
     * Whether a stage column name is valid.
     *
     * @param string $stage
     * @return bool
     */
    public static function is_stage(string $stage): bool {
        return in_array($stage, self::STAGES, true);
    }

    /**
     * Whether a stage value is valid.
     *
     * @param string $value
     * @return bool
     */
    public static function is_value(string $value): bool {
        return in_array($value, self::VALUES, true);
    }

    /**
     * Get (or create) the pipeline row for a course.
     *
     * @param int $courseid
     * @return \stdClass
     */
    public static function ensure_row(int $courseid): \stdClass {
        global $DB;
        $row = $DB->get_record('local_azmsi_pipeline', ['courseid' => $courseid]);
        if ($row) {
            return $row;
        }
        $row = (object) ['courseid' => $courseid, 'timemodified' => time()];
        foreach (self::STAGES as $s) {
            $row->{$s} = 'queued';
        }
        $row->id = $DB->insert_record('local_azmsi_pipeline', $row);
        return $row;
    }

    /**
     * Persist a single stage value (audited). The caller must have already
     * checked require_capability('local/azmsi:managepipeline') + sesskey.
     *
     * Setting stage_launch=done flips the course `status` custom field to
     * in_progress and queues the website revalidation so the public "courses
     * built" count + curriculum badges refresh with no code edit.
     *
     * @param int $courseid
     * @param string $stage one of self::STAGES
     * @param string $value one of self::VALUES
     * @param int $userid the acting user (audit)
     * @return \stdClass the updated row
     */
    public static function set_stage(int $courseid, string $stage, string $value, int $userid): \stdClass {
        global $DB;
        if (!self::is_stage($stage) || !self::is_value($value)) {
            throw new \invalid_parameter_exception('Invalid pipeline stage or value.');
        }

        $row = self::ensure_row($courseid);
        $row->{$stage} = $value;
        $row->updatedby = $userid;
        $row->timemodified = time();
        $DB->update_record('local_azmsi_pipeline', $row);

        if ($stage === 'stage_launch' && $value === 'done') {
            self::on_launch($courseid);
        }
        return $row;
    }

    /**
     * Launch hook: flip the course status custom field live + revalidate the site.
     *
     * @param int $courseid
     */
    protected static function on_launch(int $courseid): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $course = $DB->get_record('course', ['id' => $courseid]);
        if ($course) {
            // Flip the catalog status custom field (read live by get_program_catalog).
            $course->customfield_status = 'in_progress';
            update_course($course);
        }
        // The catalog changed → push the public site to revalidate (ISR webhook).
        manager::queue_adhoc_task(new revalidate_website(), true);
    }

    /**
     * Read all pipeline rows for the AZMSI catalog courses (admin console).
     *
     * @return array list of ['courseid','code','name','stages'=>[['key','label','value']],'launched'=>bool]
     */
    public static function get_all(): array {
        global $DB;
        $out = [];
        $courses = $DB->get_records_select(
            'course',
            $DB->sql_like('idnumber', ':code'),
            ['code' => 'EMD-%'],
            'sortorder ASC'
        );
        foreach ($courses as $course) {
            $row = $DB->get_record('local_azmsi_pipeline', ['courseid' => $course->id]);
            $stages = [];
            foreach (self::STAGES as $s) {
                $value = $row ? ($row->{$s} ?? 'queued') : 'queued';
                $stages[] = [
                    'key'   => $s,
                    'label' => get_string('pipeline_' . $s, 'local_azmsi'),
                    'value' => $value,
                ];
            }
            $out[] = [
                'courseid' => (int) $course->id,
                'code'     => (string) $course->idnumber,
                'name'     => format_string($course->fullname),
                'stages'   => $stages,
                'launched' => $row ? ($row->stage_launch ?? 'queued') === 'done' : false,
            ];
        }
        return $out;
    }
}
