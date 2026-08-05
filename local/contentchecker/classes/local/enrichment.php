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
 * Builds and inserts richer content into weekly material.
 *
 * Where the insertion UI lives was a real choice. Course content here is
 * mod_page HTML edited with TinyMCE, so a TinyMCE button would be the natural
 * home -- but a TinyMCE button has to be its own tiny_* plugin, and it would
 * only ever be able to hand the editor a blob of markup. Insertion is done from
 * the content checker's own page instead, which can round-trip: it reads the
 * activity's existing HTML, appends the new element to it, and writes it back
 * to the same field in the same format. Nothing is converted to Markdown or to
 * a bespoke JSON document on the way through, so the content stays editable in
 * TinyMCE exactly as before.
 *
 * The 3D and diagram assets are a registry rather than three hardcoded embeds:
 * an admin registers a name, a viewer type and a URL, and any compatible
 * open-source asset works without a code change.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrichment {

    /**
     * Registered assets an editor can insert.
     *
     * @param string|null $assettype Restrict to model3d|diagram|embed.
     * @return array Asset rows.
     */
    public static function assets(?string $assettype = null): array {
        global $DB;

        $conditions = ['enabled' => 1];
        if ($assettype !== null) {
            $conditions['assettype'] = $assettype;
        }
        return $DB->get_records('local_cchecker_assets', $conditions, 'sortorder, name');
    }

    /**
     * Render one registered asset as embeddable HTML.
     *
     * Every viewer type produces a self-contained, static fragment. Nothing
     * here emits a script tag: the interactive behaviour is attached by the
     * plugin's AMD module when the page loads, so the stored content stays
     * clean HTML that survives being re-edited in TinyMCE.
     *
     * @param \stdClass $asset Row from local_cchecker_assets.
     * @return string HTML.
     */
    public static function render_asset(\stdClass $asset): string {
        $caption = self::caption($asset);

        switch ($asset->viewer) {
            case 'image':
                return \html_writer::tag('figure',
                    \html_writer::empty_tag('img', [
                        'src' => $asset->url,
                        'alt' => $asset->description ?: $asset->name,
                        'class' => 'img-fluid',
                        'loading' => 'lazy',
                    ]) . $caption,
                    ['class' => 'cct-asset cct-asset-image']);

            case 'mermaid':
                // A div, not a pre: Mermaid replaces the host element's
                // innerHTML with an <svg>, and a <pre> is styled for
                // preformatted text so the diagram never lays out properly.
                // CSS keeps the source readable until the renderer runs, so it
                // still degrades gracefully when one is not configured.
                return \html_writer::tag('figure',
                    \html_writer::div(s((string) $asset->body), 'cct-mermaid',
                        ['data-cct-mermaid' => '1']) . $caption,
                    ['class' => 'cct-asset cct-asset-diagram']);

            case 'iframe':
                return \html_writer::tag('figure',
                    \html_writer::tag('iframe', '', [
                        'src' => $asset->url,
                        'title' => $asset->name,
                        'loading' => 'lazy',
                        'class' => 'cct-embed',
                        'allowfullscreen' => 'allowfullscreen',
                        // A 3D viewer needs WebGL, fullscreen and, for AR-capable
                        // models, spatial tracking. Without these the frame loads
                        // but the model will not render or go fullscreen.
                        'allow' => 'fullscreen; xr-spatial-tracking; accelerometer; gyroscope',
                        // An embedded third-party viewer gets no ambient
                        // authority over the Moodle page around it.
                        'sandbox' => 'allow-scripts allow-same-origin allow-popups',
                    ]) . $caption,
                    ['class' => 'cct-asset cct-asset-embed']);

            case 'modelviewer':
            default:
                // <model-viewer> is a custom element: a browser without it
                // simply shows the poster image and the download link, which
                // is why the fallback content sits inside the element.
                $fallback = $asset->posterurl
                    ? \html_writer::empty_tag('img', [
                        'src' => $asset->posterurl,
                        'alt' => $asset->description ?: $asset->name,
                        'class' => 'img-fluid',
                    ])
                    : \html_writer::link($asset->url, $asset->name);

                return \html_writer::tag('figure',
                    \html_writer::tag('model-viewer', $fallback, [
                        'src' => $asset->url,
                        'poster' => (string) $asset->posterurl,
                        'alt' => $asset->description ?: $asset->name,
                        'camera-controls' => 'camera-controls',
                        'auto-rotate' => 'auto-rotate',
                        'ar' => 'ar',
                        'class' => 'cct-model',
                        'data-cct-model' => '1',
                    ]) . $caption,
                    ['class' => 'cct-asset cct-asset-model']);
        }
    }

    /**
     * The caption and attribution shown under an asset.
     *
     * Attribution is rendered whenever it is set, because most open-source 3D
     * and diagram assets are licensed on condition that it is.
     *
     * @param \stdClass $asset The asset row.
     * @return string HTML figcaption, or ''.
     */
    protected static function caption(\stdClass $asset): string {
        $parts = [];
        if (trim((string) $asset->description) !== '') {
            $parts[] = s($asset->description);
        }
        if (trim((string) $asset->attribution) !== '') {
            $credit = s($asset->attribution);
            if (trim((string) $asset->licence) !== '') {
                $credit .= ' (' . s($asset->licence) . ')';
            }
            $parts[] = \html_writer::tag('small', $credit, ['class' => 'text-muted']);
        }
        if (!$parts) {
            return '';
        }
        return \html_writer::tag('figcaption', implode('<br>', $parts),
            ['class' => 'cct-asset-caption']);
    }

    /**
     * Render a comparison table.
     *
     * Built as a real table with a header row and a row scope on the first
     * column, which is what makes a drug-A versus drug-B comparison navigable
     * in a screen reader rather than a grid of unlabelled cells.
     *
     * @param string $caption Table caption.
     * @param array $headers Column headings; the first labels the row axis.
     * @param array $rows List of row arrays.
     * @return string HTML.
     */
    public static function render_table(string $caption, array $headers, array $rows): string {
        $table = new \html_table();
        $table->head = array_map('s', $headers);
        $table->attributes['class'] = 'table table-bordered cct-comparison';
        $table->caption = s($caption);
        $table->captionhide = false;

        foreach ($rows as $row) {
            $cells = [];
            foreach (array_values((array) $row) as $i => $value) {
                $cell = new \html_table_cell(s((string) $value));
                if ($i === 0) {
                    // The first column is the thing being compared, so it
                    // labels its row rather than being data.
                    $cell->header = true;
                    $cell->scope = 'row';
                }
                $cells[] = $cell;
            }
            $table->data[] = new \html_table_row($cells);
        }

        return \html_writer::div(\html_writer::table($table), 'cct-asset cct-asset-table');
    }

    /**
     * Where an editor may drop an inserted element.
     *
     * @param int $cmid Course module id, for listing that activity's headings.
     * @return array position key => label.
     */
    public static function positions(int $cmid = 0): array {
        $positions = [
            'end' => get_string('enrich:pos:end', 'local_contentchecker'),
            'start' => get_string('enrich:pos:start', 'local_contentchecker'),
        ];

        if (!$cmid) {
            return $positions;
        }

        // Offering the activity's own headings is what turns "it went
        // somewhere in the page" into a decision the editor made.
        foreach (content_source::for_cm($cmid) as $item) {
            foreach (blocks::split($item->html) as $block) {
                if ($block->title === '') {
                    continue;
                }
                $positions['after:' . $block->ref] = get_string('enrich:pos:after',
                    'local_contentchecker', shorten_text($block->title, 60));
            }
        }

        return $positions;
    }

    /**
     * Put the fragment where the editor asked for it.
     *
     * @param string $html The activity's existing content.
     * @param string $fragment What to insert.
     * @param string $position end|start|after:<blockref>.
     * @return string The new content.
     */
    protected static function place(string $html, string $fragment, string $position): string {
        if ($position === 'start') {
            return $fragment . "\n" . $html;
        }

        if (str_starts_with($position, 'after:')) {
            $ref = substr($position, strlen('after:'));

            // Insert immediately before the NEXT heading, which is the end of
            // the chosen section rather than the start of the following one.
            $blocks = blocks::split($html);
            $found = false;
            foreach ($blocks as $block) {
                if ($found && $block->html !== '') {
                    $at = strpos($html, $block->html);
                    if ($at !== false) {
                        return substr($html, 0, $at) . $fragment . "\n" . substr($html, $at);
                    }
                    break;
                }
                if ($block->ref === $ref) {
                    $found = true;
                }
            }
            // The chosen heading was the last one, so the end of the page IS
            // the end of that section.
        }

        return $html . "\n" . $fragment;
    }

    /**
     * Append a fragment to an activity's stored content and save it.
     *
     * The activity's own format is preserved: the fragment is HTML appended to
     * HTML, in the same field the editor already edits.
     *
     * @param int $cmid Course module id.
     * @param string $fragment HTML to append.
     * @param int|null $recordid Restrict to one record, for a multi-part module.
     * @return bool True when something was written.
     */
    public static function insert_into_cm(int $cmid, string $fragment,
            ?int $recordid = null, string $position = 'end'): bool {
        global $DB;

        $items = content_source::for_cm($cmid);
        if (!$items) {
            return false;
        }

        $target = null;
        foreach ($items as $item) {
            if ($recordid === null || (int) $item->recordid === $recordid) {
                $target = $item;
                break;
            }
        }
        if (!$target) {
            return false;
        }

        $record = $DB->get_record($target->table, ['id' => $target->recordid]);
        if (!$record) {
            return false;
        }

        $before = (string) $record->{$target->field};
        $record->{$target->field} = self::place($before, $fragment, $position);
        if (isset($record->timemodified)) {
            $record->timemodified = time();
        }
        $DB->update_record($target->table, $record);

        $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
        rebuild_course_cache((int) $cm->course, true);
        \cache_helper::purge_by_event('changesincourse');

        audit::log('asset', $cmid, 'inserted', [
            'courseid' => (int) $cm->course,
            'cmid' => $cmid,
            'before' => \core_text::substr($before, -400),
            'after' => \core_text::substr($fragment, 0, 400),
        ]);

        return true;
    }
}
