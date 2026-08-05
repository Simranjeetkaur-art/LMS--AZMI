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
use local_contentchecker\image\image_inserter;

/**
 * Inserts an editor-chosen image into an activity.
 *
 * Reached only by a person clicking a specific search result. The attribution
 * fields travel with the request and are stored with the file.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class insert_image extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Target course module'),
            'recordid' => new external_value(PARAM_INT, 'Target record, or 0 for the first'),
            'sourceid' => new external_value(PARAM_ALPHANUMEXT, 'Image source id'),
            'ref' => new external_value(PARAM_RAW, 'Opaque handle from the search result'),
            'title' => new external_value(PARAM_TEXT, 'Image title', VALUE_DEFAULT, ''),
            'author' => new external_value(PARAM_TEXT, 'Creator', VALUE_DEFAULT, ''),
            'license' => new external_value(PARAM_RAW, 'Licence code', VALUE_DEFAULT, ''),
            'licenseurl' => new external_value(PARAM_URL, 'Licence URL', VALUE_DEFAULT, ''),
            'attribution' => new external_value(PARAM_TEXT, 'Attribution line', VALUE_DEFAULT, ''),
            'landingurl' => new external_value(PARAM_URL, 'Origin page', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Insert the image.
     *
     * @param int $cmid Target course module.
     * @param int $recordid Target record.
     * @param string $sourceid Image source id.
     * @param string $ref Opaque handle.
     * @param string $title Image title.
     * @param string $author Creator.
     * @param string $license Licence code.
     * @param string $licenseurl Licence URL.
     * @param string $attribution Attribution line.
     * @param string $landingurl Origin page.
     * @return array Outcome.
     */
    public static function execute(int $cmid, int $recordid, string $sourceid, string $ref,
            string $title = '', string $author = '', string $license = '',
            string $licenseurl = '', string $attribution = '',
            string $landingurl = ''): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'recordid' => $recordid,
            'sourceid' => $sourceid,
            'ref' => $ref,
            'title' => $title,
            'author' => $author,
            'license' => $license,
            'licenseurl' => $licenseurl,
            'attribution' => $attribution,
            'landingurl' => $landingurl,
        ]);

        $cm = $DB->get_record('course_modules', ['id' => $params['cmid']], '*', MUST_EXIST);
        $context = \context_course::instance((int) $cm->course);
        self::validate_context($context);
        require_capability('local/contentchecker:manage', $context);

        \core_php_time_limit::raise(120);

        $result = image_inserter::insert(
            $params['cmid'],
            $params['recordid'],
            $params['sourceid'],
            $params['ref'],
            [
                'title' => $params['title'],
                'author' => $params['author'],
                'license' => $params['license'],
                'licenseurl' => $params['licenseurl'],
                'attribution' => $params['attribution'],
                'landingurl' => $params['landingurl'],
            ]
        );

        return [
            'ok' => $result['ok'],
            'filename' => $result['filename'],
            'message' => $result['ok']
                ? get_string('image:inserted', 'local_contentchecker')
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
            'ok' => new external_value(PARAM_BOOL, 'Was the image inserted'),
            'filename' => new external_value(PARAM_FILE, 'Stored filename'),
            'message' => new external_value(PARAM_TEXT, 'Outcome for the editor'),
        ]);
    }
}
