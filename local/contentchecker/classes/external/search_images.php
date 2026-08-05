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
use local_contentchecker\image\source_registry;

/**
 * Searches a configured image source.
 *
 * Returns candidates only. Nothing is inserted here -- choosing is a separate,
 * explicit action, on the same principle as AI suggestions.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_images extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course the search is made from'),
            'sourceid' => new external_value(PARAM_ALPHANUMEXT, 'Image source id'),
            'query' => new external_value(PARAM_TEXT, 'Search terms'),
            'page' => new external_value(PARAM_INT, 'One-based page number', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Run the search.
     *
     * @param int $courseid Course id.
     * @param string $sourceid Image source id.
     * @param string $query Search terms.
     * @param int $page Page number.
     * @return array Results.
     */
    public static function execute(int $courseid, string $sourceid, string $query,
            int $page = 1): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sourceid' => $sourceid,
            'query' => $query,
            'page' => $page,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/contentchecker:manage', $context);

        // Resolving through the registry means a disabled source cannot be
        // searched by naming it directly.
        $source = source_registry::get($params['sourceid']);
        $results = $source->search($params['query'], max(1, $params['page']), $context);

        return [
            'sourceid' => $params['sourceid'],
            'page' => max(1, $params['page']),
            'results' => array_map(fn($result) => $result->to_array(), $results),
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure The return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sourceid' => new external_value(PARAM_ALPHANUMEXT, 'Source searched'),
            'page' => new external_value(PARAM_INT, 'Page returned'),
            'results' => new external_multiple_structure(new external_single_structure([
                'sourceid' => new external_value(PARAM_ALPHANUMEXT, 'Source id'),
                'ref' => new external_value(PARAM_RAW, 'Opaque handle for insertion'),
                'title' => new external_value(PARAM_TEXT, 'Image title'),
                'thumburl' => new external_value(PARAM_RAW, 'Thumbnail URL'),
                'fullurl' => new external_value(PARAM_RAW, 'Full image URL'),
                'author' => new external_value(PARAM_TEXT, 'Creator'),
                'license' => new external_value(PARAM_RAW, 'Licence code'),
                'licenseurl' => new external_value(PARAM_RAW, 'Licence URL'),
                'attribution' => new external_value(PARAM_TEXT, 'Attribution line'),
                'landingurl' => new external_value(PARAM_RAW, 'Origin page'),
                'width' => new external_value(PARAM_INT, 'Pixel width'),
                'height' => new external_value(PARAM_INT, 'Pixel height'),
            ])),
        ]);
    }
}
