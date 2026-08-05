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

namespace local_contentchecker;

use core\hook\output\before_footer_html_generation;
use local_contentchecker\api\tts_client;
use local_contentchecker\local\blocks;
use local_contentchecker\local\content_source;
use local_contentchecker\local\questions;
use local_contentchecker\local\settings;

/**
 * Attaches the learner-facing features to content pages.
 *
 * Both features are additive and attach to the rendered page rather than being
 * baked into stored content, so a course whose content was authored before this
 * plugin existed gets them with no migration.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /** @var array Modules whose view page carries readable prose. */
    const SUPPORTED = ['page', 'book', 'assign', 'forum', 'url', 'resource', 'quiz'];

    /**
     * Load the read-aloud control and the follow-up question widget.
     *
     * @param before_footer_html_generation $hook The hook.
     * @return void
     */
    public static function before_footer_html_generation(
            before_footer_html_generation $hook): void {
        global $PAGE;

        if (during_initial_install() || !isloggedin() || isguestuser()) {
            return;
        }

        $cm = $PAGE->cm;
        if (!$cm || !in_array($cm->modname, self::SUPPORTED, true)) {
            return;
        }
        // Only the activity's own view page, not its settings or reports.
        if (strpos($PAGE->url->get_path(), '/mod/' . $cm->modname . '/view.php') === false) {
            return;
        }

        self::load_read_aloud($cm);
        self::load_questions($cm);
        self::load_enrichment();
    }

    /**
     * Upgrade embedded enrichment assets where a local viewer library is set.
     *
     * Loaded unconditionally rather than after sniffing the content, because
     * the module itself checks for the elements before fetching anything and
     * doing the check server-side would mean re-parsing the activity's HTML on
     * every page view.
     *
     * @return void
     */
    protected static function load_enrichment(): void {
        global $PAGE;

        $modelviewer = trim((string) get_config('local_contentchecker', 'modelviewer_script'));
        $mermaid = trim((string) get_config('local_contentchecker', 'mermaid_script'));

        if ($modelviewer === '' && $mermaid === '') {
            return;
        }

        $PAGE->requires->js_call_amd('local_contentchecker/enrichment', 'init', [[
            'modelviewer' => $modelviewer,
            'mermaid' => $mermaid,
        ]]);
    }

    /**
     * Attach the read-aloud control.
     *
     * @param \cm_info $cm The course module.
     * @return void
     */
    protected static function load_read_aloud(\cm_info $cm): void {
        global $PAGE;

        if (!settings::enabled('readaloud_enabled')) {
            return;
        }

        // The high-quality option is only advertised when a voice is actually
        // configured. Showing a toggle that cannot work is worse than not
        // showing one.
        $PAGE->requires->js_call_amd('local_contentchecker/read_aloud', 'init', [[
            'cmid' => (int) $cm->id,
            'highquality' => tts_client::is_available(),
        ]]);
    }

    /**
     * Attach the follow-up question widget.
     *
     * @param \cm_info $cm The course module.
     * @return void
     */
    protected static function load_questions(\cm_info $cm): void {
        global $PAGE;

        if (!settings::enabled('questions_enabled')) {
            return;
        }

        $approved = questions::approved_for((int) $cm->id);
        if (!$approved) {
            return;
        }

        // Grouped by block so the widget can place each set after the content
        // block it belongs to.
        $byblock = [];
        foreach ($approved as $question) {
            $byblock[$question->blockref][] = $question;
        }

        // The widget locates a block in the rendered DOM by its heading text,
        // so the ref alone is not enough -- it is a hash. Re-deriving the
        // titles here keeps the hashing rule in one place, in PHP.
        $titles = [];
        foreach (content_source::for_cm((int) $cm->id) as $item) {
            foreach (blocks::split($item->html) as $block) {
                $titles[$block->ref] = $block->title;
            }
        }

        $payload = [];
        foreach ($byblock as $ref => $set) {
            $payload[] = [
                'blockref' => $ref,
                'blocktitle' => $titles[$ref] ?? '',
                'questions' => array_values($set),
            ];
        }

        $PAGE->requires->js_call_amd('local_contentchecker/question_widget', 'init', [[
            'cmid' => (int) $cm->id,
            'blocks' => $payload,
        ]]);
    }
}
