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
 * Client for the self-hosted AI models on the AWS GPU server.
 *
 * Every HTTP call the plugin makes to the GPU goes through this class. The
 * endpoint, auth token, timeout and retry policy are all admin settings; none
 * of them is hardcoded, and the token is never exposed to client-side code.
 *
 * The non-obvious defaults below were established by measuring the live gateway
 * and each one guards a failure that is hard to diagnose from a dead task:
 *
 *  - `think = false`. The adjudicator is a thinking model: it returns generated
 *    text in a `thinking` field and leaves `response` an EMPTY STRING. Without
 *    this the atomiser silently produces nothing at all.
 *  - `stream = true`. The gateway sits behind Cloudflare, which kills a request
 *    with HTTP 524 when the origin takes more than ~100s to first byte. A cold
 *    load of the 35b adjudicator reliably exceeds that. Streaming keeps bytes
 *    moving so the connection is never idle. This is not an optimisation.
 *  - abort on `done`. The gateway holds the connection open after the terminal
 *    object, so reading to EOF blocks until the socket timeout.
 *  - bounded `num_predict`. A JSON schema constrains shape, not length. An
 *    unbounded free-text field can run past the token ceiling and return
 *    truncated, unparseable JSON -- and because Ollama serialises per model and
 *    keeps generating after a client disconnects, one runaway request blocks
 *    every request queued behind it.
 *  - `num_ctx`. The box otherwise loads models with a 262144-token context,
 *    which forces evictions between the three resident models.
 *  - browser User-Agent. Cloudflare challenges bot user-agents on this host.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gpu_client implements ai_backend {

    /** @var string Gateway base URL. */
    protected $endpoint;

    /** @var string Bearer token. Read from config at call time, never logged. */
    protected $token;

    /** @var string Browser UA; Cloudflare challenges bot UAs on this host. */
    protected $useragent;

    /** @var int Per-request timeout in seconds. */
    protected $timeout;

    /** @var int Attempts after the first. */
    protected $retries;

    /** @var string|null Why the last generation stopped: 'stop', 'length', ... */
    protected $lastdonereason = null;

    /** @var int The output ceiling used on the last call, for error messages. */
    protected $lastpredict = 0;

    /**
     * Build a client from plugin config.
     *
     * @param string|null $endpoint Override endpoint.
     * @param string|null $token Override token.
     */
    public function __construct(?string $endpoint = null, ?string $token = null) {
        $this->endpoint = rtrim($endpoint ?? (get_config('local_contentchecker', 'endpoint') ?: ''), '/');
        $this->token = $token ?? (get_config('local_contentchecker', 'token') ?: '');
        $this->useragent = get_config('local_contentchecker', 'useragent') ?:
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) ' .
            'Chrome/124.0 Safari/537.36';
        $this->timeout = (int) (get_config('local_contentchecker', 'timeout') ?: 150);
        $this->retries = (int) (get_config('local_contentchecker', 'retries') ?: 2);

        if ($this->endpoint === '') {
            throw new \moodle_exception('error:notconfigured', 'local_contentchecker');
        }
    }

    /**
     * Has an admin configured an endpoint at all?
     *
     * Cheap enough to call from a page render to decide whether to offer a
     * "Verify" button, without constructing a client that would throw.
     *
     * @return bool True when an endpoint is set.
     */
    public static function is_configured(): bool {
        return trim((string) get_config('local_contentchecker', 'endpoint')) !== '';
    }

    /**
     * Is the server up? Cheap enough to call before queueing a run.
     *
     * @return bool True when the model list answers.
     */
    public function healthy(): bool {
        try {
            $curl = $this->curl(20);
            $body = $curl->get($this->endpoint . '/api/tags');
            return $curl->get_errno() === 0 && is_string($body)
                && is_array(json_decode($body, true));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Model names the server currently has loaded.
     *
     * @return array List of model name strings.
     */
    public function models(): array {
        $curl = $this->curl(20);
        $body = $curl->get($this->endpoint . '/api/tags');
        $decoded = json_decode((string) $body, true);
        return array_column($decoded['models'] ?? [], 'name');
    }

    /**
     * Generate a completion, retrying on transport failure.
     *
     * @param string $model Model name.
     * @param string $prompt The prompt.
     * @param array|null $schema JSON Schema to constrain output; forces valid JSON.
     * @param int|null $maxtokens Override the configured output ceiling.
     * @return string The generated text.
     */
    public function generate(string $model, string $prompt, ?array $schema = null,
            ?int $maxtokens = null): string {
        $payload = $this->build_payload($model, $prompt, $schema, $maxtokens);

        $last = null;
        for ($attempt = 0; $attempt <= $this->retries; $attempt++) {
            try {
                return $this->stream_generate($payload);
            } catch (\Throwable $e) {
                $last = $e;
                if ($attempt < $this->retries) {
                    // Backs off so a retry drains past a busy model rather than
                    // piling onto it.
                    sleep(2 + 3 * $attempt);
                }
            }
        }
        throw new \moodle_exception('error:aicall', 'local_contentchecker', '',
            $last ? $last->getMessage() : '');
    }

    /**
     * Build the request body.
     *
     * Split out from generate() so the flags that keep this gateway working
     * can be asserted in a test rather than only being reviewed by eye. Every
     * value here is load-bearing; see the class docblock for what each one
     * guards against.
     *
     * @param string $model Model name.
     * @param string $prompt The prompt.
     * @param array|null $schema JSON Schema.
     * @param int|null $maxtokens Override the configured output ceiling.
     * @return array The request payload.
     */
    protected function build_payload(string $model, string $prompt, ?array $schema,
            ?int $maxtokens = null): array {
        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => true,
            'think' => false,
            'keep_alive' => get_config('local_contentchecker', 'keepalive') ?: '30m',
            'options' => [
                'temperature' => 0,
                'num_ctx' => (int) (get_config('local_contentchecker', 'numctx') ?: 8192),
                'num_predict' => $maxtokens
                    ?: (int) (get_config('local_contentchecker', 'numpredict') ?: 600),
            ],
        ];
        if ($schema) {
            $payload['format'] = $schema;
        }
        return $payload;
    }

    /**
     * Consume one chunk of the streamed NDJSON body.
     *
     * Returning less than the chunk length is what aborts the transfer, and
     * that is the whole point: the gateway holds the connection open after the
     * terminal object, so reading to EOF blocks until the socket timeout.
     *
     * Buffering matters because a chunk boundary lands mid-line often enough
     * to matter -- a naive per-chunk json_decode silently loses tokens.
     *
     * @param string $chunk Bytes from curl.
     * @param array $state Accumulator with text, thinking, done and buffer keys.
     * @return int The chunk length to continue, or 0 to abort.
     */
    protected function consume_chunk(string $chunk, array &$state): int {
        $len = strlen($chunk);
        $state['buffer'] .= $chunk;

        while (($pos = strpos($state['buffer'], "\n")) !== false) {
            $line = trim(substr($state['buffer'], 0, $pos));
            $state['buffer'] = substr($state['buffer'], $pos + 1);
            if ($line === '') {
                continue;
            }
            $obj = json_decode($line, true);
            if (!is_array($obj)) {
                continue;
            }
            if (isset($obj['response'])) {
                $state['text'] .= $obj['response'];
            }
            if (!empty($obj['thinking'])) {
                $state['thinking'] .= $obj['thinking'];
            }
            if (!empty($obj['done'])) {
                $state['done'] = true;
                // 'length' means the ceiling cut the output off mid-stream.
                $state['donereason'] = $obj['done_reason'] ?? null;
                return 0; // Returning less than $len aborts the transfer.
            }
        }

        return $len;
    }

    /**
     * Generate and decode JSON in one step.
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
            // Distinguish "the model hit its output ceiling" from "the model
            // emitted nonsense". They look identical at the JSON layer -- both
            // are just a decode failure -- but the fix is completely different,
            // and the truncation case was previously reported as unparseable
            // JSON while the real cause was a num_predict that was too low.
            if ($this->lastdonereason === 'length') {
                throw new \moodle_exception('error:aitruncated', 'local_contentchecker',
                    '', $this->lastpredict);
            }
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
        $model = get_config('local_contentchecker', 'model_embed') ?: 'nomic-embed-text';
        $curl = $this->curl();
        $body = $curl->post($this->endpoint . '/api/embeddings',
            json_encode(['model' => $model, 'prompt' => $text]));
        if ($curl->get_errno() !== 0) {
            throw new \moodle_exception('error:aicall', 'local_contentchecker', '', $curl->error);
        }
        $decoded = json_decode((string) $body, true);
        if (empty($decoded['embedding']) || !is_array($decoded['embedding'])) {
            throw new \moodle_exception('error:aiembedding', 'local_contentchecker');
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
     * One JSON object per line; the final object carries `done: true`. We
     * concatenate `response` across lines. `thinking` is read only as a
     * fallback for a model that ignores think=false.
     *
     * The write callback ABORTS the transfer as soon as the terminal object
     * arrives. This is load-bearing: the gateway holds the connection open
     * after `done: true`, so reading to EOF blocks until the socket timeout.
     * Measured: 0.98s aborting on done against a 9-minute hang reading to EOF.
     *
     * @param array $payload Request payload.
     * @return string Concatenated response text.
     */
    protected function stream_generate(array $payload): string {
        $state = ['text' => '', 'thinking' => '', 'done' => false, 'buffer' => '',
            'donereason' => null];
        $this->lastdonereason = null;
        $this->lastpredict = (int) ($payload['options']['num_predict'] ?? 0);

        $consume = function($ch, $chunk) use (&$state) {
            return $this->consume_chunk($chunk, $state);
        };

        $curl = $this->curl();
        $curl->setopt(['CURLOPT_WRITEFUNCTION' => $consume]);
        $curl->post($this->endpoint . '/api/generate', json_encode($payload));

        $errno = $curl->get_errno();
        // CURLE_WRITE_ERROR (23) is how the deliberate abort surfaces. It is
        // only a real error if we aborted without having seen the terminal
        // object.
        if ($errno !== 0 && !($state['done'] && (int) $errno === CURLE_WRITE_ERROR)) {
            throw new \moodle_exception('error:aicall', 'local_contentchecker', '', $curl->error);
        }
        $info = $curl->get_info();
        if (!$state['done'] && !empty($info['http_code']) && (int) $info['http_code'] >= 400) {
            throw new \moodle_exception('error:aihttp', 'local_contentchecker', '',
                $info['http_code']);
        }

        $this->lastdonereason = $state['donereason'];

        return $state['text'] !== '' ? $state['text'] : $state['thinking'];
    }

    /**
     * A configured curl instance.
     *
     * @param int|null $timeout Override the configured timeout.
     * @return \curl The curl wrapper.
     */
    protected function curl(?int $timeout = null): \curl {
        $curl = new \curl();
        $headers = ['Content-Type: application/json'];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        $curl->setHeader($headers);
        $curl->setopt([
            'CURLOPT_TIMEOUT' => $timeout ?? $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => 20,
            'CURLOPT_USERAGENT' => $this->useragent,
        ]);
        return $curl;
    }
}
