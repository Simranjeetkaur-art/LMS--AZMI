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

namespace local_contentchecker\output;

use local_contentchecker\local\dashboard;
use local_contentchecker\local\pipeline;
use local_contentchecker\reference\corpus;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer for local_contentchecker.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Badge markup for a section status.
     *
     * @param string $status One of the dashboard constants.
     * @return string HTML.
     */
    public function status_badge(string $status): string {
        $map = [
            dashboard::NEVER => ['secondary', 'status:never'],
            dashboard::OK => ['success', 'status:ok'],
            dashboard::NEEDS_REVIEW => ['warning', 'status:needsreview'],
            dashboard::FAILED => ['danger', 'status:failed'],
            dashboard::RUNNING => ['info', 'status:running'],
            dashboard::EMPTY_RESULT => ['warning', 'status:empty'],
        ];
        [$colour, $key] = $map[$status] ?? $map[dashboard::NEVER];

        return \html_writer::span(get_string($key, 'local_contentchecker'),
            'badge badge-' . $colour, ['data-cct-badge' => $status]);
    }

    /**
     * The course dashboard.
     *
     * @param \stdClass $course The course.
     * @param array $sections Section status rows.
     * @param bool $canrun Whether the viewer may start checks.
     * @param bool $canjob Whether the viewer may queue background jobs.
     * @return string HTML.
     */
    public function course_dashboard(\stdClass $course, array $sections,
            bool $canrun, bool $canjob): string {
        $out = $this->warnings();

        if ($canjob) {
            $out .= \html_writer::div(
                \html_writer::tag('button',
                    get_string('dashboard:verifycourse', 'local_contentchecker'),
                    ['type' => 'button', 'class' => 'btn btn-primary',
                     'data-cct-queue-job' => '1']),
                'mb-3');
        }

        $table = new \html_table();
        $table->head = [
            get_string('dashboard:week', 'local_contentchecker'),
            get_string('dashboard:items', 'local_contentchecker'),
            get_string('dashboard:status', 'local_contentchecker'),
            get_string('dashboard:pending', 'local_contentchecker'),
            get_string('dashboard:lastchecked', 'local_contentchecker'),
            get_string('dashboard:actions', 'local_contentchecker'),
        ];
        $table->attributes['class'] = 'table generaltable';

        foreach ($sections as $section) {
            $actions = [];

            if ($canrun && $section->numitems > 0) {
                $actions[] = \html_writer::tag('button',
                    get_string('dashboard:verifyweek', 'local_contentchecker'), [
                        'type' => 'button',
                        'class' => 'btn btn-sm btn-secondary',
                        'data-cct-verify' => '1',
                        'data-cct-section' => $section->sectionnum,
                    ]);
            }

            $actions[] = \html_writer::link(
                new \moodle_url('/local/contentchecker/week.php', [
                    'courseid' => $course->id,
                    'sectionnum' => $section->sectionnum,
                ]),
                get_string('dashboard:review', 'local_contentchecker'),
                ['class' => 'btn btn-sm btn-link']);

            $actions[] = \html_writer::link(
                new \moodle_url('/local/contentchecker/publish.php', [
                    'courseid' => $course->id,
                    'sectionnum' => $section->sectionnum,
                ]),
                get_string('dashboard:publish', 'local_contentchecker'),
                ['class' => 'btn btn-sm btn-link']);

            // The week name is the way into its activities, which is what an
            // editor actually wants to browse.
            $weeklink = \html_writer::link(
                new \moodle_url('/local/contentchecker/week.php', [
                    'courseid' => $course->id,
                    'sectionnum' => $section->sectionnum,
                ]),
                s($section->name));

            $row = new \html_table_row([
                $weeklink,
                $section->numitems,
                $this->status_badge($section->status)
                    . \html_writer::span('', 'ml-1 small', ['data-cct-progress' => '1']),
                $section->pending ?: '-',
                $section->lastchecked ?: get_string('never', 'moodle'),
                implode(' ', $actions),
            ]);
            $row->attributes['data-cct-row'] = $section->sectionnum;
            $table->data[] = $row;
        }

        return $out . \html_writer::table($table);
    }

    /**
     * Standing warnings about the state of the checker itself.
     *
     * These are shown rather than hidden because a reviewer needs to know how
     * much weight a finding can carry before they act on it.
     *
     * @return string HTML.
     */
    public function warnings(): string {
        $out = '';

        if (!\local_contentchecker\api\gpu_client::is_configured()) {
            $out .= $this->output->notification(
                get_string('warning:notconfigured', 'local_contentchecker'),
                \core\output\notification::NOTIFY_ERROR);
        }

        $mode = get_config('local_contentchecker', 'fetcher') ?: 'corpus';
        if ($mode === 'corpus') {
            if (corpus::chunk_count() === 0) {
                $out .= $this->output->notification(
                    get_string('warning:emptycorpus', 'local_contentchecker'),
                    \core\output\notification::NOTIFY_WARNING);
            } else if (!corpus::has_authoritative_source()) {
                // A tier-3-only corpus can demonstrate that the pipeline
                // behaves; it cannot settle a clinical question.
                $out .= $this->output->notification(
                    get_string('warning:tier3only', 'local_contentchecker'),
                    \core\output\notification::NOTIFY_WARNING);
            }
        }

        return $out;
    }

    /**
     * The diff review list for one week.
     *
     * Findings are ordered worst-first so the reviewer meets the contradictions
     * before the merely unsupported ones.
     *
     * @param array $suggestions Suggestion rows with their evidence.
     * @param bool $canapprove Whether the viewer may decide.
     * @return string HTML.
     */
    public function suggestion_list(array $suggestions, bool $canapprove): string {
        if (!$suggestions) {
            return $this->output->notification(
                get_string('review:none', 'local_contentchecker'),
                \core\output\notification::NOTIFY_SUCCESS);
        }

        $out = '';
        foreach ($suggestions as $suggestion) {
            $out .= $this->render_from_template('local_contentchecker/suggestion_card',
                $this->suggestion_context($suggestion, $canapprove));
        }
        return $out;
    }

    /**
     * Mustache context for one suggestion card.
     *
     * @param \stdClass $suggestion The suggestion, with an evidence property.
     * @param bool $canapprove Whether the viewer may decide.
     * @return array The context.
     */
    protected function suggestion_context(\stdClass $suggestion, bool $canapprove): array {
        $verdictclass = [
            'contradicted' => 'danger',
            'contested' => 'warning',
            'partially_supported' => 'warning',
            'outdated' => 'warning',
            'unsupported' => 'secondary',
            'needs_source' => 'secondary',
            'supported' => 'success',
            'error' => 'dark',
        ];

        return [
            'id' => (int) $suggestion->id,
            'itemname' => $suggestion->itemname,
            'claim' => $suggestion->claim,
            'verdict' => $suggestion->verdict,
            'verdictlabel' => get_string('verdict:' . $suggestion->verdict,
                'local_contentchecker'),
            'verdictclass' => $verdictclass[$suggestion->verdict] ?? 'secondary',
            'quorum' => $suggestion->quorum,
            'currenttext' => $suggestion->currenttext,
            'suggestedtext' => $suggestion->suggestedtext,
            'hasdiff' => trim((string) $suggestion->suggestedtext) !== ''
                && trim((string) $suggestion->currenttext) !== '',
            // A suggestion whose original sentence could not be located
            // verbatim cannot be applied automatically, and the card says so
            // rather than offering a button that will refuse.
            'applicable' => trim((string) $suggestion->currenttext) !== '',
            'reasoning' => $suggestion->reasoning,
            'confidence' => number_format((float) $suggestion->confidence, 2),
            'quote' => $suggestion->quote,
            'quoteverbatim' => (bool) $suggestion->quoteverbatim,
            'evidence' => array_values(array_map(fn($e) => [
                'title' => $e->title,
                'url' => $e->url,
                'tier' => (int) $e->tier,
                'score' => number_format((float) $e->score, 3),
                'snippet' => $e->snippet,
            ], $suggestion->evidence ?? [])),
            'decided' => $suggestion->decision !== 'pending',
            'decision' => $suggestion->decision,
            'decisionlabel' => get_string('decision:' . $suggestion->decision,
                'local_contentchecker'),
            'applied' => (bool) $suggestion->applied,
            'canapprove' => $canapprove && $suggestion->decision === 'pending',
        ];
    }

    /**
     * The activities in one week, each linking to its own content.
     *
     * @param \stdClass $course The course.
     * @param int $sectionnum The week.
     * @param array $activities Rows from dashboard::activities_for_section().
     * @param bool $canrun Whether the viewer may start checks.
     * @return string HTML.
     */
    public function activity_list(\stdClass $course, int $sectionnum, array $activities,
            bool $canrun): string {
        if (!$activities) {
            return $this->output->notification(
                get_string('activity:none', 'local_contentchecker'),
                \core\output\notification::NOTIFY_INFO);
        }

        $table = new \html_table();
        $table->head = [
            get_string('activity:name', 'local_contentchecker'),
            get_string('activity:kind', 'local_contentchecker'),
            get_string('activity:size', 'local_contentchecker'),
            get_string('dashboard:status', 'local_contentchecker'),
            get_string('activity:claims', 'local_contentchecker'),
            get_string('dashboard:pending', 'local_contentchecker'),
            get_string('dashboard:actions', 'local_contentchecker'),
        ];
        $table->attributes['class'] = 'table generaltable cct-activities';

        foreach ($activities as $activity) {
            $actions = [];

            if ($canrun) {
                // Checks one activity, which is fast enough to wait for --
                // unlike a whole week.
                $actions[] = \html_writer::tag('button',
                    get_string('activity:verify', 'local_contentchecker'), [
                        'type' => 'button',
                        'class' => 'btn btn-sm btn-secondary',
                        'data-cct-verify' => '1',
                        'data-cct-section' => $sectionnum,
                        'data-cct-cmid' => $activity->cmid,
                    ]);
            }

            $actions[] = \html_writer::link(
                new \moodle_url('/mod/' . $activity->modname . '/view.php',
                    ['id' => $activity->cmid]),
                get_string('activity:open', 'local_contentchecker'),
                ['class' => 'btn btn-sm btn-link', 'target' => '_blank',
                 'rel' => 'noopener']);

            $row = new \html_table_row([
                \html_writer::link(
                    new \moodle_url('/local/contentchecker/activity.php',
                        ['cmid' => $activity->cmid]),
                    s($activity->name)),
                get_string('kind:' . $activity->kind, 'local_contentchecker'),
                get_string('activity:chars', 'local_contentchecker', $activity->chars),
                $this->status_badge($activity->status)
                    . \html_writer::span('', 'ml-1 small', ['data-cct-progress' => '1']),
                $activity->claims ?: '-',
                $activity->pending ?: '-',
                implode(' ', $actions),
            ]);
            $row->attributes['data-cct-row'] = $activity->cmid;
            $table->data[] = $row;
        }

        return \html_writer::table($table);
    }

    /**
     * One activity's stored content, rendered as a learner would see it.
     *
     * @param array $items content_source items for the activity.
     * @return string HTML.
     */
    public function activity_content(array $items): string {
        $out = '';

        foreach ($items as $item) {
            $context = \context_module::instance($item->cmid);

            if (count($items) > 1) {
                $out .= $this->output->heading(s($item->name), 4);
            }

            // Rewriting @@PLUGINFILE@@ is what makes inserted images actually
            // appear; without it they render as broken links.
            $html = $item->html;
            try {
                $area = \local_contentchecker\local\content_source::file_area($item);
                $html = file_rewrite_pluginfile_urls($html, 'pluginfile.php',
                    $area['contextid'], $area['component'], $area['filearea'],
                    $area['itemid']);
            } catch (\Throwable $e) {
                // A content type with no file area of its own still renders.
                $html = $item->html;
            }

            $out .= \html_writer::div(
                format_text($html, FORMAT_HTML, ['context' => $context, 'noclean' => true]),
                'cct-activity-content border rounded p-3 mb-3');
        }

        return $out;
    }

    /**
     * The editor's review list for auto-generated follow-up questions.
     *
     * Drafts are shown with their answer key visible, because an editor cannot
     * approve a question they cannot see the marking for.
     *
     * @param array $questions Question rows.
     * @return string HTML.
     */
    public function question_list(array $questions): string {
        $out = '';

        foreach ($questions as $question) {
            $options = json_decode($question->options, true) ?: [];
            $answer = json_decode($question->answer, true) ?: [];

            $rendered = [];
            foreach ($options as $i => $option) {
                $rendered[] = [
                    'index' => $i,
                    'text' => $option,
                    'correct' => in_array($i, $answer, true),
                ];
            }

            $out .= $this->render_from_template('local_contentchecker/question_card', [
                'id' => (int) $question->id,
                'cmid' => (int) $question->cmid,
                'blockref' => $question->blockref,
                'qtype' => $question->qtype,
                'qtypelabel' => get_string('qtype:' . $question->qtype,
                    'local_contentchecker'),
                'qtext' => $question->qtext,
                'options' => $rendered,
                'hasoptions' => (bool) $rendered,
                'explanation' => $question->explanation,
                'status' => $question->status,
                'statuslabel' => get_string('qstatus:' . $question->status,
                    'local_contentchecker'),
                'statusclass' => [
                    'draft' => 'secondary',
                    'approved' => 'success',
                    'rejected' => 'dark',
                ][$question->status] ?? 'secondary',
                'isdraft' => $question->status === 'draft',
            ]);
        }

        return $out;
    }

    /**
     * The site-wide oversight table.
     *
     * @param array $rows Course status rows.
     * @return string HTML.
     */
    public function site_overview(array $rows): string {
        $table = new \html_table();
        $table->head = [
            get_string('dashboard:course', 'local_contentchecker'),
            get_string('dashboard:checks', 'local_contentchecker'),
            get_string('dashboard:pending', 'local_contentchecker'),
            get_string('dashboard:lastchecked', 'local_contentchecker'),
        ];
        $table->attributes['class'] = 'table generaltable';

        foreach ($rows as $row) {
            $table->data[] = [
                \html_writer::link(
                    new \moodle_url('/local/contentchecker/index.php',
                        ['courseid' => $row->courseid]),
                    s($row->fullname) . ' (' . s($row->shortname) . ')'),
                $row->numchecks,
                $row->pending ?: '-',
                $row->lastchecked ?: get_string('never', 'moodle'),
            ];
        }

        return $this->warnings() . \html_writer::table($table);
    }
}
