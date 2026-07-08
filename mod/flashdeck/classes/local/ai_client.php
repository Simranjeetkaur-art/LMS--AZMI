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

namespace mod_flashdeck\local;

/**
 * Chat client for a self-hosted inference server.
 *
 * Speaks both native Ollama (POST /api/chat) and any OpenAI-compatible
 * server such as vLLM (POST /v1/chat/completions), with optional
 * bearer-token auth for proxied production endpoints. Every knob —
 * provider, base URL, token, model, timeout, temperature — comes from
 * plugin admin settings; constructor overrides exist for tests and for
 * borrowing another component's configuration. Nothing is hardcoded.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_client {

    /** @var string plugin config component */
    const COMPONENT = 'mod_flashdeck';

    /** @var array resolved configuration */
    protected $config;

    /**
     * Constructor.
     *
     * @param array $overrides optional config overrides (provider, baseurl,
     *              bearertoken, model, timeout, temperature)
     */
    public function __construct(array $overrides = []) {
        $this->config = $overrides + [
            'provider' => get_config(self::COMPONENT, 'aiprovider') ?: 'ollama',
            'baseurl' => rtrim(trim((string) get_config(self::COMPONENT, 'aibaseurl')), '/'),
            'bearertoken' => trim((string) get_config(self::COMPONENT, 'aibearertoken')),
            'model' => trim((string) get_config(self::COMPONENT, 'aimodel')),
            'timeout' => (int) get_config(self::COMPONENT, 'aitimeout') ?: 120,
            'temperature' => (float) get_config(self::COMPONENT, 'aitemperature'),
        ];
        $this->config['baseurl'] = rtrim(trim((string) $this->config['baseurl']), '/');
    }

    /**
     * Whether a server and model are configured.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return $this->config['baseurl'] !== '' && $this->config['model'] !== '';
    }

    /**
     * The configured model identifier.
     *
     * @return string
     */
    public function get_model(): string {
        return $this->config['model'];
    }

    /**
     * Add the Authorization header when a bearer token is configured.
     *
     * @param array $options http_client request options
     * @return array
     */
    protected function with_bearer(array $options): array {
        if ($this->config['bearertoken'] !== '') {
            $options['headers'] = array_merge($options['headers'] ?? [],
                ['Authorization' => 'Bearer ' . $this->config['bearertoken']]);
        }
        return $options;
    }

    /**
     * List models the server currently offers (for the settings page).
     *
     * @return string[] model identifiers, empty on any failure
     */
    public function get_models(): array {
        if ($this->config['baseurl'] === '') {
            return [];
        }
        try {
            $client = new \core\http_client();
            if ($this->config['provider'] === 'vllm') {
                $response = $client->get($this->config['baseurl'] . '/v1/models',
                    $this->with_bearer(['timeout' => 10]));
                $data = json_decode($response->getBody()->getContents(), true);
                return array_values(array_filter(array_map(
                    static fn($m) => $m['id'] ?? null, $data['data'] ?? [])));
            }
            $response = $client->get($this->config['baseurl'] . '/api/tags',
                $this->with_bearer(['timeout' => 10]));
            $data = json_decode($response->getBody()->getContents(), true);
            return array_values(array_filter(array_map(
                static fn($m) => $m['name'] ?? null, $data['models'] ?? [])));
        } catch (\Throwable $e) {
            debugging(self::COMPONENT . ': get_models() failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Run one chat completion and return the assistant text.
     *
     * @param array $messages list of ['role' => system|user|assistant, 'content' => string]
     * @return array ['content' => string, 'error' => string] — exactly one is non-empty
     */
    public function chat(array $messages): array {
        if (!$this->is_configured()) {
            return ['content' => '', 'error' => get_string('erraiunconfigured', 'mod_flashdeck')];
        }

        try {
            $client = new \core\http_client();
            if ($this->config['provider'] === 'vllm') {
                $response = $client->post($this->config['baseurl'] . '/v1/chat/completions',
                    $this->with_bearer([
                        'timeout' => $this->config['timeout'],
                        'json' => [
                            'model' => $this->config['model'],
                            'messages' => $messages,
                            'temperature' => $this->config['temperature'],
                            'stream' => false,
                        ],
                    ]));
                $data = json_decode($response->getBody()->getContents(), true);
                $content = $data['choices'][0]['message']['content'] ?? '';
            } else {
                $response = $client->post($this->config['baseurl'] . '/api/chat',
                    $this->with_bearer([
                        'timeout' => $this->config['timeout'],
                        'json' => [
                            'model' => $this->config['model'],
                            'messages' => $messages,
                            'options' => ['temperature' => $this->config['temperature']],
                            'stream' => false,
                        ],
                    ]));
                $data = json_decode($response->getBody()->getContents(), true);
                $content = $data['message']['content'] ?? '';
            }

            if (!is_string($content) || trim($content) === '') {
                return ['content' => '', 'error' => get_string('erraiempty', 'mod_flashdeck')];
            }
            return ['content' => $content, 'error' => ''];
        } catch (\Throwable $e) {
            return ['content' => '',
                'error' => get_string('erraifailed', 'mod_flashdeck', $e->getMessage())];
        }
    }
}
