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
use local_contentchecker\local\templates;

/**
 * Stores a publish layout choice and returns its rendered preview.
 *
 * The preview comes back from the same Mustache template that will render the
 * published week, so what the editor previews is what gets published.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_template extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sectionid' => new external_value(PARAM_INT, 'Section row id'),
            'cmid' => new external_value(PARAM_INT, 'Course module id, or 0',
                VALUE_DEFAULT, 0),
            'templatekey' => new external_value(PARAM_ALPHANUMEXT, 'Layout key'),
            'config' => new external_value(PARAM_RAW, 'JSON slot assignments',
                VALUE_DEFAULT, '{}'),
            'previewonly' => new external_value(PARAM_BOOL, 'Render without saving',
                VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Store the choice, or just preview it.
     *
     * @param int $courseid Course id.
     * @param int $sectionid Section row id.
     * @param int $cmid Course module id, or 0.
     * @param string $templatekey Layout key.
     * @param string $config JSON slot assignments.
     * @param bool $previewonly Render without saving.
     * @return array The rendered preview.
     */
    public static function execute(int $courseid, int $sectionid, int $cmid,
            string $templatekey, string $config = '{}', bool $previewonly = false): array {
        global $DB, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sectionid' => $sectionid,
            'cmid' => $cmid,
            'templatekey' => $templatekey,
            'config' => $config,
            'previewonly' => $previewonly,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/contentchecker:manage', $context);

        // The section must belong to the course the caller named.
        $section = $DB->get_record('course_sections',
            ['id' => $params['sectionid'], 'course' => $params['courseid']], '*', MUST_EXIST);

        if (!templates::exists($params['templatekey'])) {
            throw new \moodle_exception('error:unknowntemplate', 'local_contentchecker');
        }

        $decoded = json_decode($params['config'], true);
        $decoded = is_array($decoded) ? $decoded : [];

        if (!$params['previewonly']) {
            templates::set($params['courseid'], (int) $section->id, $params['cmid'],
                $params['templatekey'], $decoded);
        }

        $PAGE->set_context($context);
        $renderer = $PAGE->get_renderer('local_contentchecker');
        $html = $renderer->render_from_template(
            templates::all()[$params['templatekey']]['mustache'],
            templates::context($params['templatekey'], $decoded));

        return ['saved' => !$params['previewonly'], 'html' => $html];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure The return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'saved' => new external_value(PARAM_BOOL, 'Was the choice stored'),
            'html' => new external_value(PARAM_RAW, 'Rendered layout preview'),
        ]);
    }
}
