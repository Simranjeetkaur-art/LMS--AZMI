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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Optional fallback to an external OpenAI-compatible API.
 *
 * This exists only because the spec allows an admin to opt in explicitly. It is
 * OFF by default and stays off unless someone deliberately ticks the setting,
 * because using it sends unpublished medical course content to a third party.
 *
 * The constructor refuses to build unless the opt-in is set, so there is no
 * path by which a misconfiguration silently routes content off-site.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class thirdparty_client implements ai_backend {

    /** @var string API base URL. */
    protected $endpoint;

    /** @var string API key. */
    protected $key;

    /** @var int Per-request timeout in seconds. */
    protected $timeout;

    /**
     * Build a client, refusing unless the admin opted in.
     */
    public function __construct() {
        if (!get_config('local_contentchecker', 'allow_thirdparty')) {
            throw new \moodle_exception('error:thirdpartydisabled', 'local_contentchecker');
        }
        $this->endpoint = rtrim((string) get_config('local_contentchecker',
            'thirdparty_endpoint'), '/');
        $this->key = (string) get_config('local_contentchecker', 'thirdparty_key');
        $this->timeout = (int) (get_config('local_contentchecker', 'timeout') ?: 150);

        if ($this->endpoint === '' || $this->key === '') {
            throw new \moodle_exception('error:notconfigured', 'local_contentchecker');
        }
    }

    /**
     * Is the fallback both enabled and configured?
     *
     * @return bool True when usable.
     */
    public static function is_available(): bool {
        return (bool) get_config('local_contentchecker', 'allow_thirdparty')
            && trim((string) get_config('local_contentchecker', 'thirdparty_endpoint')) !== ''
            && trim((string) get_config('local_contentchecker', 'thirdparty_key')) !== '';
    }

    /**
     * Is the backend reachable?
     *
     * @return bool True when it answers.
     */
    public function healthy(): bool {
        try {
            $curl = $this->curl();
            $curl->get($this->endpoint . '/models');
            $info = $curl->get_info();
            return $curl->get_errno() === 0 && (int) ($info['http_code'] ?? 0) < 400;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Generate text.
     *
     * @param string $model Model name.
     * @param string $prompt The prompt.
     * @param array|null $schema JSON Schema constraining the output shape.
     * @param int|null $maxtokens Override the configured output ceiling.
     * @return string Generated text.
     */
    public function generate(string $model, string $prompt, ?array $schema = null,
            ?int $maxtokens = null): string {
        $payload = [
            'model' => get_config('local_contentchecker', 'thirdparty_model') ?: $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0,
            'max_tokens' => $maxtokens
                ?: (int) (get_config('local_contentchecker', 'numpredict') ?: 600),
        ];
        if ($schema) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'result', 'schema' => $schema, 'strict' => false],
            ];
        }

        $curl = $this->curl();
        $body = $curl->post($this->endpoint . '/chat/completions', json_encode($payload));
        if ($curl->get_errno() !== 0) {
            throw new \moodle_exception('error:aicall', 'local_contentchecker', '', $curl->error);
        }
        $decoded = json_decode((string) $body, true);
        $text = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($text)) {
            throw new \moodle_exception('error:aicall', 'local_contentchecker', '',
                \core_text::substr((string) $body, 0, 200));
        }
        return $text;
    }

    /**
     * Generate and decode a JSON object in one step.
     *
     * @param string $model Model name.
     * @param string $prompt The prompt.
     * @param array $schema JSON Schema.
     * @param int|null $maxtokens Override the configured output ceiling.
     * @return array Decoded object.
     */
    public function generate_json(string $model, string $prompt, array $schema,
            ?int $maxtokens = null): array {
        $text = $this->generate($model, $prompt, $schema, $maxtokens);
        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            throw new \moodle_exception('error:aijson', 'local_contentchecker', '',
                \core_text::substr($text, 0, 200));
        }
        return $decoded;
    }

    /**
     * Embed a passage.
     *
     * @param string $text Text to embed.
     * @return array Float vector.
     */
    public function embed(string $text): array {
        $curl = $this->curl();
        $body = $curl->post($this->endpoint . '/embeddings', json_encode([
            'model' => get_config('local_contentchecker', 'model_embed') ?: 'text-embedding-3-small',
            'input' => $text,
        ]));
        if ($curl->get_errno() !== 0) {
            throw new \moodle_exception('error:aicall', 'local_contentchecker', '', $curl->error);
        }
        $decoded = json_decode((string) $body, true);
        $vector = $decoded['data'][0]['embedding'] ?? null;
        if (!is_array($vector)) {
            throw new \moodle_exception('error:aiembedding', 'local_contentchecker');
        }
        return $vector;
    }

    /**
     * A configured curl instance.
     *
     * @return \curl The curl wrapper.
     */
    protected function curl(): \curl {
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
        ]);
        $curl->setopt([
            'CURLOPT_TIMEOUT' => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => 20,
        ]);
        return $curl;
    }
}
