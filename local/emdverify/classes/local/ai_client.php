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

namespace local_emdverify\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Client for the self-hosted Ollama GPU gateway.
 *
 * Every non-obvious setting here was established empirically against the live
 * box on 2026-07-31. Changing one without re-measuring will break runs in ways
 * that are hard to diagnose from a failed adhoc task:
 *
 *  - `think = false`. qwen3.5 is a thinking model: it returns generated text in
 *    a `thinking` field and leaves `response` an EMPTY STRING. Without this the
 *    atomiser silently produces nothing at all.
 *  - `stream = true`. The gateway sits behind Cloudflare, which kills a request
 *    with HTTP 524 when the origin takes longer than ~100s to first byte. A
 *    cold load of the 34.6 GB adjudicator reliably exceeds that. Streaming keeps
 *    bytes moving so the connection is never idle. This is not an optimisation.
 *  - `num_ctx`. The box loads models with a 262144-token context by default,
 *    which allocated 55.4 GB across three resident models and forced evictions
 *    between them. These prompts are a few thousand tokens.
 *  - `keep_alive`. Holds the adjudicator resident for the length of a run so a
 *    single week does not pay a cold load per claim.
 *  - Browser User-Agent. Cloudflare challenges bot user-agents on this hostname.
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_client {

    /** @var string Gateway base URL. */
    protected $endpoint;

    /** @var string Bearer token. Read from config at runtime, never stored here. */
    protected $token;

    /** @var string Browser UA; Cloudflare challenges bot UAs on this host. */
    protected $useragent;

    /** @var int Per-request timeout in seconds. */
    protected $timeout;

    /**
     * Build a client from plugin config.
     *
     * @param string|null $endpoint Override endpoint.
     * @param string|null $token Override token.
     */
    public function __construct(?string $endpoint = null, ?string $token = null) {
        $this->endpoint = rtrim($endpoint ?? get_config('local_emdverify', 'endpoint') ?: '', '/');
        $this->token = $token ?? (get_config('local_emdverify', 'token') ?: '');
        $this->useragent = get_config('local_emdverify', 'useragent') ?:
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) ' .
            'Chrome/124.0 Safari/537.36';
        $this->timeout = (int) (get_config('local_emdverify', 'timeout') ?: 900);
        if ($this->endpoint === '' || $this->token === '') {
            throw new \moodle_exception('error:notconfigured', 'local_emdverify');
        }
    }

    /**
     * Is the server up? Cheap enough to call before queueing a run.
     *
     * @return bool True when /healthz answers.
     */
    public function healthy(): bool {
        try {
            $curl = $this->curl();
            $body = $curl->get($this->endpoint . '/healthz');
            return $curl->get_errno() === 0 && stripos((string) $body, 'ok') !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Generate a completion.
     *
     * @param string $model Model name.
     * @param string $prompt The prompt.
     * @param array|null $schema JSON Schema to constrain output; forces valid JSON.
     * @param int $retries Attempts after the first.
     * @return string The generated text.
     */
    public function generate(string $model, string $prompt, ?array $schema = null,
            int $retries = 2): string {
        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => true,
            'think' => false,
            'keep_alive' => get_config('local_emdverify', 'keepalive') ?: '30m',
            'options' => [
                'temperature' => 0,
                'num_ctx' => (int) (get_config('local_emdverify', 'numctx') ?: 8192),
                // Hard ceiling on generated tokens. A schema constrains SHAPE
                // but not LENGTH -- a free-text field like `reasoning` can run
                // away. Because Ollama serialises requests per model and keeps
                // generating after a client disconnects, one runaway claim
                // blocks every later claim in the run behind it. Observed on
                // 2026-07-31: a single adjudication stalled a whole batch for
                // over ten minutes and abandoned generations kept the 35b busy
                // long after the client was gone.
                'num_predict' => (int) (get_config('local_emdverify', 'numpredict') ?: 600),
            ],
        ];
        if ($schema) {
            $payload['format'] = $schema;
        }

        $last = null;
        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            try {
                return $this->stream_generate($payload);
            } catch (\Throwable $e) {
                $last = $e;
                if ($attempt < $retries) {
                    sleep(2 + 3 * $attempt);
                }
            }
        }
        throw new \moodle_exception('error:aicall', 'local_emdverify', '',
            $last ? $last->getMessage() : '');
    }

    /**
     * Generate and decode JSON in one step.
     *
     * @param string $model Model name.
     * @param string $prompt The prompt.
     * @param array $schema JSON Schema.
     * @return array Decoded object.
     */
    public function generate_json(string $model, string $prompt, array $schema): array {
        $text = $this->generate($model, $prompt, $schema);
        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            throw new \moodle_exception('error:aijson', 'local_emdverify', '',
                substr($text, 0, 200));
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
        $model = get_config('local_emdverify', 'model_embed') ?: 'nomic-embed-text';
        $curl = $this->curl();
        $body = $curl->post($this->endpoint . '/api/embeddings',
            json_encode(['model' => $model, 'prompt' => $text]));
        if ($curl->get_errno() !== 0) {
            throw new \moodle_exception('error:aicall', 'local_emdverify', '', $curl->error);
        }
        $decoded = json_decode((string) $body, true);
        if (empty($decoded['embedding']) || !is_array($decoded['embedding'])) {
            throw new \moodle_exception('error:aiembedding', 'local_emdverify');
        }
        return $decoded['embedding'];
    }

    /**
     * Cosine similarity between two vectors.
     *
     * @param array $a First vector.
     * @param array $b Second vector.
     * @return float Similarity in [-1, 1].
     */
    public static function cosine(array $a, array $b): float {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na <= 0 || $nb <= 0) {
            return 0.0;
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }

    /**
     * POST /api/generate with stream=true and reassemble the NDJSON body.
     *
     * Ollama streams one JSON object per line; the final object carries
     * `done: true`. We concatenate `response` across lines. `thinking` is read
     * only as a fallback for the case where a model ignores think=false.
     *
     * The write callback ABORTS the transfer as soon as the terminal object
     * arrives. This is load-bearing: the gateway holds the connection open
     * after `done: true`, so reading to EOF blocks until the socket timeout.
     * Measured against the live box: 0.98s aborting on done, a 9-minute hang
     * reading to EOF.
     *
     * @param array $payload Request payload.
     * @return string Concatenated response text.
     */
    protected function stream_generate(array $payload): string {
        $text = '';
        $thinking = '';
        $done = false;
        $buffer = '';

        $consume = function($ch, $chunk) use (&$text, &$thinking, &$done, &$buffer) {
            $len = strlen($chunk);
            $buffer .= $chunk;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($line === '') {
                    continue;
                }
                $obj = json_decode($line, true);
                if (!is_array($obj)) {
                    continue;
                }
                if (isset($obj['response'])) {
                    $text .= $obj['response'];
                }
                if (!empty($obj['thinking'])) {
                    $thinking .= $obj['thinking'];
                }
                if (!empty($obj['done'])) {
                    $done = true;
                    return 0; // Returning < $len aborts the transfer.
                }
            }
            return $len;
        };

        $curl = $this->curl();
        $curl->setopt(['CURLOPT_WRITEFUNCTION' => $consume]);
        $curl->post($this->endpoint . '/api/generate', json_encode($payload));

        $errno = $curl->get_errno();
        // CURLE_WRITE_ERROR (23) is how the deliberate abort surfaces; it is
        // only an error if we aborted without having seen the terminal object.
        if ($errno !== 0 && !($done && (int) $errno === CURLE_WRITE_ERROR)) {
            throw new \moodle_exception('error:aicall', 'local_emdverify', '', $curl->error);
        }
        $info = $curl->get_info();
        if (!$done && !empty($info['http_code']) && (int) $info['http_code'] >= 400) {
            throw new \moodle_exception('error:aihttp', 'local_emdverify', '',
                $info['http_code']);
        }

        return $text !== '' ? $text : $thinking;
    }

    /**
     * A configured curl instance.
     *
     * @return \curl The curl wrapper.
     */
    protected function curl(): \curl {
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
        ]);
        $curl->setopt([
            'CURLOPT_TIMEOUT' => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => 20,
            'CURLOPT_USERAGENT' => $this->useragent,
        ]);
        return $curl;
    }
}
