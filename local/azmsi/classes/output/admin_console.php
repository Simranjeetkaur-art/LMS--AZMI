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

namespace local_azmsi\output;

use core\output\named_templatable;
use renderable;
use renderer_base;
use local_azmsi\local\admin;
use local_azmsi\local\completeness;

/**
 * Admin console (S12) renderable — gold-accented manager home.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_console implements named_templatable, renderable {
    /** @var bool whether the viewer may advance pipeline stages. */
    protected $canmanage;

    /**
     * Constructor.
     *
     * @param bool $canmanage viewer holds local/azmsi:managepipeline
     */
    public function __construct(bool $canmanage) {
        $this->canmanage = $canmanage;
    }

    /**
     * Template name.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'local_azmsi/admin_console';
    }

    /** @var int Default seconds between in-browser live refreshes of the data widgets. */
    public const REFRESH_SECONDS = 60;

    /**
     * Build the context for the live (data-widget) region of the console.
     *
     * This is the single source of truth for the widgets that the JS poller and
     * the {@see \local_azmsi\external\get_admin_console} web service both render
     * via the local_azmsi/admin_console_live template. It is a pure transform of
     * the cached rollup dataset returned by {@see admin::console()} — no inline
     * aggregation, nothing hardcoded.
     *
     * @param array $data the dataset from admin::console()
     * @return array template context for local_azmsi/admin_console_live
     */
    public static function live_from(array $data): array {
        $opslabel = get_string(
            'showingxofy',
            'local_azmsi',
            ['shown' => $data['courseopsshown'], 'total' => $data['courseopstotal']]
        );
        $generatedonstr = $data['generatedon']
            ? userdate($data['generatedon'], get_string('strftimedatetimeshort', 'langconfig')) : '';

        // Announcements: items from the rollup; the "+ New" link is gated on the
        // viewer's capability to start a discussion in the site news forum.
        $announce = $data['announcements'];
        $canannounce = false;
        $announceurl = '';
        if (!empty($announce['forumid'])) {
            $announceurl = (new \moodle_url('/mod/forum/view.php', ['f' => $announce['forumid']]))->out(false);
            try {
                $cm = get_coursemodule_from_instance('forum', $announce['forumid'], SITEID);
                if ($cm) {
                    $canannounce = has_capability('mod/forum:addnews', \context_module::instance($cm->id));
                }
            } catch (\Throwable $e) {
                $canannounce = false;
            }
        }

        return [
            'coursesbystatus' => $data['coursesbystatus'],
            'funnel'          => $data['funnel'],
            'systemhealth'    => $data['systemhealth'],
            'operational'     => $data['operational'],
            'courseops'       => $data['courseops'],
            'courseopslabel'  => $opslabel,
            'facultyload'     => $data['facultyload'],
            'facultyactive'   => $data['facultyactive'],
            'usersbyrole'     => $data['usersbyrole'],
            'rolesaccess'     => $data['rolesaccess'],
            'announcements'   => $announce['items'],
            'hasannounce'     => !empty($announce['items']),
            'announceurl'     => $announceurl,
            'canannounce'     => $canannounce,
            'generatedon'     => (int) $data['generatedon'],
            'generatedonstr'  => $generatedonstr,
            'stale'           => $data['stale'],
        ];
    }

    /**
     * Compose the console context (full page = live region + interactive sections).
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = admin::console();

        // Decorate pipeline rows with content-readiness analysis + build-stage controls.
        $next = ['queued' => 'active', 'active' => 'done'];
        $pipeline = array_map(function ($course) use ($next) {
            $fullcourse = get_course($course['courseid']);
            $course['content'] = completeness::for_pipeline($fullcourse);
            $course['completenessurl'] = $course['content']['url'];
            $course['stages'] = array_map(function ($s) use ($next) {
                $s['isdone']    = $s['value'] === 'done';
                $s['isactive']  = $s['value'] === 'active';
                $s['isqueued']  = $s['value'] === 'queued';
                $s['cannext']   = isset($next[$s['value']]) && $this->canmanage;
                $s['nextvalue'] = $next[$s['value']] ?? '';
                return $s;
            }, $course['stages']);
            return $course;
        }, $data['pipeline']);

        // Per-course readiness status + a roll-up summary for the toolbar.
        $summary = ['total' => 0, 'complete' => 0, 'inprogress' => 0, 'notstarted' => 0, 'launched' => 0];
        foreach ($pipeline as &$course) {
            $summary['total']++;
            $content = $course['content'] ?? [];
            if (!empty($content['iscomplete'])) {
                $status = 'complete';
                $summary['complete']++;
            } else if (!empty($content['completeweeks'])) {
                $status = 'inprogress';
                $summary['inprogress']++;
            } else {
                $status = 'notstarted';
                $summary['notstarted']++;
            }
            if (!empty($course['launched'])) {
                $summary['launched']++;
            }
            $course['status'] = $status;
            $course['islaunched'] = !empty($course['launched']);
            $course['searchkey'] = \core_text::strtolower(($course['code'] ?? '') . ' ' . ($course['name'] ?? ''));
        }
        unset($course);

        return self::live_from($data) + [
            'pipeline'       => $pipeline,
            'pipelinesummary' => $summary,
            'haspipeline'    => !empty($pipeline),
            'reviewspending' => $data['reviewspending'] ?? 0,
            'hasreviews'     => !empty($data['reviewspending']),
            'reviewsurl'     => $data['reviewsurl'] ?? '',
            'canmanage'      => $this->canmanage,
            'sesskey'        => sesskey(),
            'refreshseconds' => self::REFRESH_SECONDS,
        ];
    }
}
