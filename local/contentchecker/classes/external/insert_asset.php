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
 * Inserts a registered 3D model or diagram into an activity.
 *
 * The AJAX counterpart of the enrichment form, so a suggestion can be accepted
 * without leaving the activity being edited.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class insert_asset extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Target course module'),
            'assetid' => new external_value(PARAM_INT, 'Registered asset id'),
            'position' => new external_value(PARAM_RAW, 'end|start|after:<blockref>',
                VALUE_DEFAULT, 'end'),
        ]);
    }

    /**
     * Insert the asset.
     *
     * @param int $cmid Target course module.
     * @param int $assetid Registered asset id.
     * @param string $position Where to place it.
     * @return array Outcome.
     */
    public static function execute(int $cmid, int $assetid, string $position = 'end'): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'assetid' => $assetid,
            'position' => $position,
        ]);

        $cm = $DB->get_record('course_modules', ['id' => $params['cmid']], '*', MUST_EXIST);
        $context = \context_course::instance((int) $cm->course);
        self::validate_context($context);
        require_capability('local/contentchecker:manage', $context);

        // Only an enabled asset can be inserted, so an entry still waiting for
        // its URL cannot be pushed into a page as a broken embed.
        $asset = $DB->get_record('local_cchecker_assets',
            ['id' => $params['assetid'], 'enabled' => 1], '*', MUST_EXIST);

        // The placement key names a heading in THIS activity, so an arbitrary
        // string cannot steer the insert somewhere unexpected.
        $allowed = array_keys(enrichment::positions($params['cmid']));
        $place = in_array($params['position'], $allowed, true) ? $params['position'] : 'end';

        $html = enrichment::render_asset($asset);
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
            'ok' => new external_value(PARAM_BOOL, 'Was the asset inserted'),
            'message' => new external_value(PARAM_TEXT, 'Outcome for the editor'),
        ]);
    }
}
