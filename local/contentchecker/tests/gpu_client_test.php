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

namespace local_contentchecker;

use local_contentchecker\api\gpu_client;

/**
 * Tests for the GPU gateway workarounds.
 *
 * Every assertion here guards a failure that was diagnosed the hard way
 * against the live box. They are cheap to run and they exist so that someone
 * tidying this class later finds out immediately, rather than discovering it
 * from a silently empty verification run weeks afterwards.
 *
 * No network is touched: the payload builder and the stream accumulator are
 * driven directly.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_contentchecker\api\gpu_client
 */
final class gpu_client_test extends \advanced_testcase {

    /**
     * A client pointed at a dummy endpoint. Nothing here makes a request.
     *
     * @return gpu_client The client.
     */
    private function client(): gpu_client {
        set_config('endpoint', 'https://gpu.invalid', 'local_contentchecker');
        set_config('token', 'dummy', 'local_contentchecker');
        return new gpu_client();
    }

    /**
     * Invoke a protected method.
     *
     * @param gpu_client $client The client.
     * @param string $name Method name.
     * @param array $args Arguments.
     * @return mixed The return value.
     */
    private function call(gpu_client $client, string $name, array $args) {
        $method = new \ReflectionMethod(gpu_client::class, $name);
        return $method->invokeArgs($client, $args);
    }

    /**
     * think=false is sent. The adjudicator is a thinking model: without this it
     * returns its output in a `thinking` field and leaves `response` empty, so
     * the claim extractor silently produces nothing at all.
     *
     * @return void
     */
    public function test_think_is_disabled(): void {
        $this->resetAfterTest();

        $payload = $this->call($this->client(), 'build_payload', ['m', 'p', null]);

        $this->assertArrayHasKey('think', $payload);
        $this->assertFalse($payload['think']);
    }

    /**
     * stream=true is sent. The gateway is Cloudflare-fronted and returns HTTP
     * 524 when the origin takes over ~100s to first byte; a cold load of the
     * large adjudicator exceeds that. This is not an optimisation.
     *
     * @return void
     */
    public function test_streaming_is_enabled(): void {
        $this->resetAfterTest();

        $payload = $this->call($this->client(), 'build_payload', ['m', 'p', null]);

        $this->assertTrue($payload['stream']);
    }

    /**
     * The generated-token ceiling is always present. A JSON schema constrains
     * shape but not length, and an unbounded field returned truncated,
     * unparseable JSON while blocking every request queued behind it.
     *
     * @return void
     */
    public function test_token_ceiling_and_context_are_bounded(): void {
        $this->resetAfterTest();

        set_config('numpredict', 321, 'local_contentchecker');
        set_config('numctx', 4096, 'local_contentchecker');

        $payload = $this->call($this->client(), 'build_payload', ['m', 'p', null]);

        $this->assertSame(321, $payload['options']['num_predict']);
        $this->assertSame(4096, $payload['options']['num_ctx']);
        $this->assertSame(0, $payload['options']['temperature']);
    }

    /**
     * Sane defaults apply when nothing is configured, so a fresh install does
     * not fall back to the server's own enormous context window.
     *
     * @return void
     */
    public function test_defaults_when_unconfigured(): void {
        $this->resetAfterTest();

        unset_config('numpredict', 'local_contentchecker');
        unset_config('numctx', 'local_contentchecker');
        unset_config('keepalive', 'local_contentchecker');

        $payload = $this->call($this->client(), 'build_payload', ['m', 'p', null]);

        $this->assertSame(600, $payload['options']['num_predict']);
        $this->assertSame(8192, $payload['options']['num_ctx']);
        $this->assertSame('30m', $payload['keep_alive']);
    }

    /**
     * A supplied schema is passed as `format`, which is what forces the model
     * to emit parseable JSON.
     *
     * @return void
     */
    public function test_schema_is_sent_as_format(): void {
        $this->resetAfterTest();

        $schema = ['type' => 'object', 'properties' => ['x' => ['type' => 'string']]];
        $payload = $this->call($this->client(), 'build_payload', ['m', 'p', $schema]);

        $this->assertSame($schema, $payload['format']);

        // And no format key at all when none was asked for.
        $plain = $this->call($this->client(), 'build_payload', ['m', 'p', null]);
        $this->assertArrayNotHasKey('format', $plain);
    }

    /**
     * The stream accumulator concatenates `response` across NDJSON lines.
     *
     * @return void
     */
    public function test_stream_concatenates_response(): void {
        $this->resetAfterTest();
        $client = $this->client();

        $state = ['text' => '', 'thinking' => '', 'done' => false, 'buffer' => ''];
        $chunk = json_encode(['response' => 'Hello ']) . "\n"
            . json_encode(['response' => 'world']) . "\n";

        $returned = $this->call($client, 'consume_chunk', [$chunk, &$state]);

        $this->assertSame(strlen($chunk), $returned, 'should ask curl to continue');
        $this->assertSame('Hello world', $state['text']);
        $this->assertFalse($state['done']);
    }

    /**
     * A chunk boundary landing mid-line loses nothing. A naive per-chunk
     * json_decode would silently drop the split token.
     *
     * @return void
     */
    public function test_stream_handles_split_lines(): void {
        $this->resetAfterTest();
        $client = $this->client();

        $line = json_encode(['response' => 'chambers']) . "\n";
        $first = substr($line, 0, 12);
        $second = substr($line, 12);

        $state = ['text' => '', 'thinking' => '', 'done' => false, 'buffer' => ''];
        $this->call($client, 'consume_chunk', [$first, &$state]);
        $this->assertSame('', $state['text'], 'incomplete line must not be decoded yet');

        $this->call($client, 'consume_chunk', [$second, &$state]);
        $this->assertSame('chambers', $state['text']);
    }

    /**
     * The terminal object aborts the transfer by returning 0. Reading to EOF
     * instead blocks until the socket timeout: measured 0.98s aborting against
     * a 9-minute hang.
     *
     * @return void
     */
    public function test_stream_aborts_on_done(): void {
        $this->resetAfterTest();
        $client = $this->client();

        $state = ['text' => '', 'thinking' => '', 'done' => false, 'buffer' => ''];
        $chunk = json_encode(['response' => 'final']) . "\n"
            . json_encode(['done' => true]) . "\n";

        $returned = $this->call($client, 'consume_chunk', [$chunk, &$state]);

        $this->assertSame(0, $returned, 'returning 0 is what aborts the transfer');
        $this->assertTrue($state['done']);
        $this->assertSame('final', $state['text']);
    }

    /**
     * `thinking` is collected as a fallback for a model that ignores
     * think=false, so such a model degrades rather than returning nothing.
     *
     * @return void
     */
    public function test_thinking_is_captured_as_fallback(): void {
        $this->resetAfterTest();
        $client = $this->client();

        $state = ['text' => '', 'thinking' => '', 'done' => false, 'buffer' => ''];
        $chunk = json_encode(['thinking' => 'reasoning...', 'response' => '']) . "\n";

        $this->call($client, 'consume_chunk', [$chunk, &$state]);

        $this->assertSame('', $state['text']);
        $this->assertSame('reasoning...', $state['thinking']);
    }

    /**
     * Garbage lines are skipped rather than aborting the whole read.
     *
     * @return void
     */
    public function test_stream_skips_unparseable_lines(): void {
        $this->resetAfterTest();
        $client = $this->client();

        $state = ['text' => '', 'thinking' => '', 'done' => false, 'buffer' => ''];
        $chunk = "not json\n" . json_encode(['response' => 'ok']) . "\n\n";

        $this->call($client, 'consume_chunk', [$chunk, &$state]);

        $this->assertSame('ok', $state['text']);
    }

    /**
     * Cosine similarity behaves, since retrieval and duplicate collapsing both
     * rest on it.
     *
     * @return void
     */
    public function test_cosine_similarity(): void {
        $this->assertEqualsWithDelta(1.0, gpu_client::cosine([1, 0, 0], [1, 0, 0]), 0.0001);
        $this->assertEqualsWithDelta(0.0, gpu_client::cosine([1, 0], [0, 1]), 0.0001);
        $this->assertEqualsWithDelta(-1.0, gpu_client::cosine([1, 0], [-1, 0]), 0.0001);
        // A zero vector must not divide by zero.
        $this->assertSame(0.0, gpu_client::cosine([0, 0], [1, 1]));
    }

    /**
     * A client cannot be built without an endpoint, so a misconfigured site
     * fails loudly instead of silently posting nowhere.
     *
     * @return void
     */
    public function test_refuses_to_build_without_endpoint(): void {
        $this->resetAfterTest();

        unset_config('endpoint', 'local_contentchecker');
        $this->assertFalse(gpu_client::is_configured());

        $this->expectException(\moodle_exception::class);
        new gpu_client();
    }
}
