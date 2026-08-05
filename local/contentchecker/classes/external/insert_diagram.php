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
use core_external\external_single_structure;
use core_external\external_value;
use local_contentchecker\local\enrichment;

/**
 * Inserts a generated diagram into an activity.
 *
 * The editor has read the diagram source and may have edited it before this is
 * called, so what gets stored is what they approved -- not what the model
 * first produced.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class insert_diagram extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Target course module'),
            'source' => new external_value(PARAM_RAW, 'Mermaid diagram source'),
            'title' => new external_value(PARAM_TEXT, 'Caption', VALUE_DEFAULT, ''),
            'position' => new external_value(PARAM_RAW, 'end|start|after:<blockref>',
                VALUE_DEFAULT, 'end'),
        ]);
    }

    /**
     * Insert the diagram.
     *
     * @param int $cmid Target course module.
     * @param string $source Mermaid source.
     * @param string $title Caption.
     * @param string $position Where to place it.
     * @return array Outcome.
     */
    public static function execute(int $cmid, string $source, string $title = '',
            string $position = 'end'): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'source' => $source,
            'title' => $title,
            'position' => $position,
        ]);

        $cm = $DB->get_record('course_modules', ['id' => $params['cmid']], '*', MUST_EXIST);
        $context = \context_course::instance((int) $cm->course);
        self::validate_context($context);
        require_capability('local/contentchecker:manage', $context);

        $source = trim($params['source']);
        if ($source === '') {
            throw new \moodle_exception('error:diagramempty', 'local_contentchecker');
        }

        // Only a real diagram declaration is accepted, so arbitrary markup
        // cannot be pushed into a page through this endpoint.
        if (!preg_match('/^(graph|flowchart|sequenceDiagram|classDiagram|mindmap)\b/i',
                $source)) {
            throw new \moodle_exception('error:diagraminvalid', 'local_contentchecker');
        }

        $allowed = array_keys(enrichment::positions($params['cmid']));
        $place = in_array($params['position'], $allowed, true) ? $params['position'] : 'end';

        // Rendered through the same asset renderer as a registered diagram, so
        // a generated one and a curated one look and behave identically.
        $html = enrichment::render_asset((object) [
            'name' => $params['title'] !== '' ? $params['title']
                : get_string('suggest:diagram', 'local_contentchecker'),
            'assettype' => 'diagram',
            'viewer' => 'mermaid',
            'url' => '',
            'posterurl' => '',
            'body' => $source,
            'description' => $params['title'],
            'licence' => '',
            'attribution' => '',
        ]);

        $ok = enrichment::insert_into_cm($params['cmid'], $html, null, $place);

        return [
            'ok' => $ok,
            'message' => $ok
                ? get_string('enrich:inserted', 'local_contentchecker')
                : get_string('enrich:insertfailed', 'local_contentchecker'),
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure The return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Was the diagram inserted'),
            'message' => new external_value(PARAM_TEXT, 'Outcome for the editor'),
        ]);
    }
}
