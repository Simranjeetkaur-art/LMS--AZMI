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

namespace local_contentchecker\api;

use local_contentchecker\local\pronunciation;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Client for the self-hosted text-to-speech voice.
 *
 * Used only by the optional "High-quality voice" mode. The default read-aloud
 * engine is the browser's own Web Speech API, which costs nothing and needs no
 * backend at all -- this exists purely to pronounce medical terminology better
 * than a general-purpose browser voice does.
 *
 * The exact model and its request shape are still open. What is fixed is where
 * the seam is: the request is a POST of JSON to a configured endpoint and the
 * response is audio bytes. Both the voice name and the endpoint are settings,
 * so swapping the deployed model is configuration rather than a code change.
 *
 * When no endpoint is configured, is_available() returns false and the learner
 * is never shown the option at all -- offered-and-broken is worse than absent.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tts_client {

    /**
     * Is a high-quality voice configured?
     *
     * @return bool True when the endpoint is set.
     */
    public static function is_available(): bool {
        return trim((string) get_config('local_contentchecker', 'tts_endpoint')) !== '';
    }

    /**
     * Render a passage to audio.
     *
     * @param string $text The passage to speak.
     * @return array {mimetype, base64} of the rendered audio.
     */
    public function synthesize(string $text): array {
        if (!self::is_available()) {
            throw new \moodle_exception('error:ttsnotconfigured', 'local_contentchecker');
        }

        $maxchars = max(100, (int) (get_config('local_contentchecker', 'tts_maxchars') ?: 1200));
        $text = \core_text::substr(trim($text), 0, $maxchars);
        if ($text === '') {
            throw new \moodle_exception('error:ttsempty', 'local_contentchecker');
        }

        // The dictionary is applied server-side so a term is pronounced the
        // same way regardless of which page it appears on.
        $text = pronunciation::apply($text);

        $endpoint = rtrim((string) get_config('local_contentchecker', 'tts_endpoint'), '/');
        $token = (string) get_config('local_contentchecker', 'tts_token');
        $voice = (string) get_config('local_contentchecker', 'tts_voice');

        $headers = ['Content-Type: application/json'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $curl = new \curl();
        $curl->setHeader($headers);
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 60,
            'CURLOPT_CONNECTTIMEOUT' => 15,
        ]);

        $body = $curl->post($endpoint, json_encode([
            'text' => $text,
            'voice' => $voice,
        ]));

        if ($curl->get_errno() !== 0 || !is_string($body) || $body === '') {
            throw new \moodle_exception('error:ttsfailed', 'local_contentchecker', '',
                $curl->error);
        }

        $info = $curl->get_info();
        if ((int) ($info['http_code'] ?? 0) >= 400) {
            throw new \moodle_exception('error:ttsfailed', 'local_contentchecker', '',
                $info['http_code']);
        }

        // Trust the served content type when it looks like audio; a Piper-style
        // server returns audio/wav, others return mpeg or ogg.
        $mimetype = (string) ($info['content_type'] ?? '');
        $mimetype = strtok($mimetype, ';');
        if (strpos((string) $mimetype, 'audio/') !== 0) {
            $mimetype = 'audio/wav';
        }

        return ['mimetype' => $mimetype, 'base64' => base64_encode($body)];
    }
}
