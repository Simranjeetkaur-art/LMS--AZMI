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
use local_contentchecker\local\enrichment;
use local_contentchecker\local\queue;
use local_contentchecker\local\suggester;

/**
 * Analyses an activity and proposes illustrations for it.
 *
 * Returns candidates only. Nothing reaches course content until an editor
 * picks one.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suggest_enrichment extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module to analyse'),
        ]);
    }

    /**
     * Analyse and propose.
     *
     * @param int $cmid Course module id.
     * @return array Concepts with their candidate illustrations.
     */
    public static function execute(int $cmid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/contentchecker:manage', $context);

        // Reading the content costs a model call, so it shares the same
        // concurrency cap as verification rather than competing with it.
        $concepts = queue::with_slot(function() use ($params, $context) {
            \core_php_time_limit::raise(300);
            return (new suggester())->suggest($params['cmid'], $context);
        }, 30);

        $out = [];
        foreach ($concepts as $concept) {
            $out[] = [
                'concept' => $concept->concept,
                'mediatype' => $concept->mediatype,
                'reason' => $concept->reason,
                'searchterms' => $concept->searchterms,
                'broadened' => !empty($concept->broadened),
                'diagram' => (string) ($concept->diagram ?? ''),
                'assets' => array_map(fn($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'assettype' => $a->assettype,
                    'licence' => $a->licence,
                    'attribution' => $a->attribution,
                ], $concept->assets),
                'images' => array_map(fn($i) => [
                    'sourceid' => $i['sourceid'],
                    'ref' => $i['ref'],
                    'title' => $i['title'],
                    'thumburl' => $i['thumburl'],
                    'author' => $i['author'],
                    'license' => $i['license'],
                    'licenseurl' => $i['licenseurl'],
                    'attribution' => $i['attribution'],
                    'landingurl' => $i['landingurl'],
                    'restrictive' => !empty($i['restrictive']),
                ], $concept->images),
            ];
        }

        return [
            'concepts' => $out,
            'positions' => array_map(fn($k, $v) => ['key' => (string) $k, 'label' => $v],
                array_keys(enrichment::positions($params['cmid'])),
                array_values(enrichment::positions($params['cmid']))),
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure The return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'concepts' => new external_multiple_structure(new external_single_structure([
                'concept' => new external_value(PARAM_TEXT, 'What the passage teaches'),
                'mediatype' => new external_value(PARAM_ALPHANUMEXT, 'model3d|diagram|image'),
                'reason' => new external_value(PARAM_TEXT, 'What the visual adds'),
                'searchterms' => new external_value(PARAM_TEXT, 'Terms used to search'),
                'broadened' => new external_value(PARAM_BOOL,
                    'True when a looser query was needed, so relevance is weaker'),
                'diagram' => new external_value(PARAM_RAW,
                    'Generated Mermaid source for a diagram concept, or empty'),
                'assets' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Registered asset id'),
                    'name' => new external_value(PARAM_TEXT, 'Asset name'),
                    'assettype' => new external_value(PARAM_ALPHANUMEXT, 'Asset kind'),
                    'licence' => new external_value(PARAM_TEXT, 'Licence'),
                    'attribution' => new external_value(PARAM_TEXT, 'Attribution line'),
                ])),
                'images' => new external_multiple_structure(new external_single_structure([
                    'sourceid' => new external_value(PARAM_ALPHANUMEXT, 'Image source'),
                    'ref' => new external_value(PARAM_RAW, 'Opaque handle'),
                    'title' => new external_value(PARAM_TEXT, 'Title'),
                    'thumburl' => new external_value(PARAM_RAW, 'Thumbnail URL'),
                    'author' => new external_value(PARAM_TEXT, 'Creator'),
                    'license' => new external_value(PARAM_RAW, 'Licence code'),
                    'licenseurl' => new external_value(PARAM_RAW, 'Licence URL'),
                    'attribution' => new external_value(PARAM_TEXT, 'Attribution line'),
                    'landingurl' => new external_value(PARAM_RAW, 'Origin page'),
                    'restrictive' => new external_value(PARAM_BOOL,
                        'Licence restricts commercial use or adaptation'),
                ])),
            ])),
            'positions' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_RAW, 'Placement key'),
                'label' => new external_value(PARAM_TEXT, 'Placement label'),
            ])),
        ]);
    }
}
