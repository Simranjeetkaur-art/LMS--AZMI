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
use local_contentchecker\api\tts_client;

/**
 * Renders a passage to speech with the self-hosted voice.
 *
 * This is a learner-facing endpoint, so it is capability-gated differently
 * from the rest of the plugin: a student legitimately needs it, and requiring
 * an editor capability would defeat the accessibility feature entirely. The
 * gates that do apply are that the caller must be logged in, must be able to
 * see the activity whose text they are asking to have read, and the text is
 * length-capped so the endpoint cannot be used to drive the GPU as a general
 * transcription service.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class synthesize extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module the text is from'),
            'text' => new external_value(PARAM_TEXT, 'The passage to speak'),
        ]);
    }

    /**
     * Render the passage.
     *
     * @param int $cmid Course module id.
     * @param string $text The passage.
     * @return array The audio, base64 encoded.
     */
    public static function execute(int $cmid, string $text): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'text' => $text,
        ]);

        // Reading aloud is only offered for content the caller can already
        // read, so this resolves and checks the real module context rather
        // than trusting the id. validate_context establishes the session and
        // enforces access to the context; uservisible then covers availability
        // restrictions, which context access alone does not.
        [, $cm] = get_course_and_cm_from_cmid($params['cmid']);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        if (!$cm->uservisible) {
            throw new \moodle_exception('error:nopermissiontoread', 'local_contentchecker');
        }
        if (!get_config('local_contentchecker', 'readaloud_enabled')) {
            throw new \moodle_exception('error:readalouddisabled', 'local_contentchecker');
        }

        $audio = (new tts_client())->synthesize($params['text']);

        return [
            'mimetype' => $audio['mimetype'],
            'audio' => $audio['base64'],
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure The return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'mimetype' => new external_value(PARAM_TEXT, 'Audio MIME type'),
            'audio' => new external_value(PARAM_RAW, 'Base64-encoded audio'),
        ]);
    }
}
