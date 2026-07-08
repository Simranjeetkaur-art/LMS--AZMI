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

namespace mod_flashdeck;

use mod_flashdeck\local\ai_client;
use mod_flashdeck\local\ai_generator;

/**
 * Tests for the AI card generator (prompt building, parsing, validation).
 *
 * No HTTP happens here: the client is stubbed where needed and the
 * parsing/validation functions are pure.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_flashdeck\local\ai_generator
 * @covers     \mod_flashdeck\local\ai_client
 */
final class ai_generator_test extends \advanced_testcase {

    /**
     * Types with files (imagelabel) are not offered to the model.
     */
    public function test_generatable_types(): void {
        $types = ai_generator::get_generatable_types();

        $this->assertArrayNotHasKey('imagelabel', $types);
        foreach (['basic', 'termdissection', 'cloze', 'matching', 'ordering',
                'comparecontrast', 'qanda'] as $expected) {
            $this->assertArrayHasKey($expected, $types);
            $this->assertNotEmpty($types[$expected]['description']);
            $this->assertIsArray($types[$expected]['content']);
        }
    }

    /**
     * The prompt is assembled from settings and the registry, nothing fixed.
     */
    public function test_build_messages(): void {
        $this->resetAfterTest();

        $messages = ai_generator::build_messages('The heart pumps blood.', ['basic', 'cloze'], 5);

        $this->assertCount(2, $messages);
        $this->assertSame('system', $messages[0]['role']);
        $system = $messages[0]['content'];
        // Default system prompt (admin setting empty) plus the dynamic contract.
        $this->assertStringContainsString('instructional designer', $system);
        $this->assertStringContainsString('"basic"', $system);
        $this->assertStringContainsString('"cloze"', $system);
        $this->assertStringNotContainsString('"matching"', $system);
        $this->assertStringContainsString('exactly 5 cards', $system);

        $this->assertSame('user', $messages[1]['role']);
        $this->assertStringContainsString('The heart pumps blood.', $messages[1]['content']);

        // An admin-configured system prompt replaces the default.
        set_config('aisystemprompt', 'CUSTOM PREAMBLE', 'mod_flashdeck');
        $messages = ai_generator::build_messages('x', ['basic'], 1);
        $this->assertStringContainsString('CUSTOM PREAMBLE', $messages[0]['content']);
        $this->assertStringNotContainsString('instructional designer', $messages[0]['content']);

        // Unknown requested types fall back to every generatable type.
        $messages = ai_generator::build_messages('x', ['hologram'], 1);
        $this->assertStringContainsString('"comparecontrast"', $messages[0]['content']);
    }

    /**
     * Model output is parsed defensively across common response shapes.
     */
    public function test_parse_response(): void {
        $card = ['cardtype' => 'basic', 'tags' => 'w1',
            'content' => ['front' => 'Q', 'back' => 'A']];
        $json = json_encode([$card]);

        // Plain array.
        $this->assertCount(1, ai_generator::parse_response($json));
        // Markdown fences.
        $this->assertCount(1, ai_generator::parse_response("```json\n{$json}\n```"));
        // Reasoning-model think block plus commentary.
        $this->assertCount(1, ai_generator::parse_response(
            "<think>Let me plan...</think>Here are your cards: {$json} Enjoy!"));
        // Object wrapper.
        $this->assertCount(1, ai_generator::parse_response(json_encode(['cards' => [$card]])));
        // Single object.
        $this->assertCount(1, ai_generator::parse_response(json_encode($card)));
        // Garbage.
        $this->assertSame([], ai_generator::parse_response('The model rambles with no JSON.'));
        // Malformed items are dropped, valid ones kept.
        $mixed = json_encode([$card, ['cardtype' => 'basic'], 'nonsense']);
        $this->assertCount(1, ai_generator::parse_response($mixed));
    }

    /**
     * Proposals are filtered through real card-type validation.
     */
    public function test_validate_proposals(): void {
        $defs = [
            ['cardtype' => 'basic', 'tags' => null,
                'content' => ['front' => 'Q', 'back' => 'A']],
            ['cardtype' => 'basic', 'tags' => null,
                'content' => ['front' => 'missing back']],
            ['cardtype' => 'hologram', 'tags' => null, 'content' => []],
            ['cardtype' => 'cloze', 'tags' => null,
                'content' => ['text' => 'No blank here.']],
        ];

        [$valid, $rejected] = ai_generator::validate_proposals($defs, ['basic', 'cloze']);
        $this->assertCount(1, $valid);
        $this->assertSame('Q', $valid[0]['content']['front']);
        $this->assertCount(3, $rejected);

        // A type outside the requested set is rejected even when valid.
        [$valid] = ai_generator::validate_proposals(
            [['cardtype' => 'basic', 'tags' => null, 'content' => ['front' => 'Q', 'back' => 'A']]],
            ['cloze']);
        $this->assertCount(0, $valid);
    }

    /**
     * End-to-end generate() with a stubbed client: no HTTP, real validation.
     */
    public function test_generate_with_stub(): void {
        $this->resetAfterTest();

        $payload = json_encode([
            ['cardtype' => 'basic', 'tags' => 'ai', 'content' => ['front' => 'Q1', 'back' => 'A1']],
            ['cardtype' => 'basic', 'tags' => null, 'content' => ['front' => 'broken']],
        ]);
        $stub = new class(['baseurl' => 'http://stub', 'model' => 'stub']) extends ai_client {
            /** @var string canned model output, injected by the test */
            public static $canned = '';

            #[\Override]
            public function chat(array $messages): array {
                return ['content' => self::$canned, 'error' => ''];
            }
        };
        $stub::$canned = "```json\n{$payload}\n```";

        $result = ai_generator::generate('source', ['basic'], 2, $stub);
        $this->assertSame('', $result['error']);
        $this->assertCount(1, $result['cards']);
        $this->assertCount(1, $result['rejected']);

        // A transport error propagates cleanly with no cards.
        $errorstub = new class(['baseurl' => '', 'model' => '']) extends ai_client {
        };
        $result = ai_generator::generate('source', ['basic'], 2, $errorstub);
        $this->assertNotSame('', $result['error']);
        $this->assertSame([], $result['cards']);
    }

    /**
     * The client resolves configuration from settings with overrides on top.
     */
    public function test_client_configuration(): void {
        $this->resetAfterTest();

        $client = new ai_client();
        $this->assertFalse($client->is_configured());

        set_config('aibaseurl', 'http://example.com:11434/', 'mod_flashdeck');
        set_config('aimodel', 'llama3.1:8b', 'mod_flashdeck');
        $client = new ai_client();
        $this->assertTrue($client->is_configured());
        $this->assertSame('llama3.1:8b', $client->get_model());

        $client = new ai_client(['model' => 'override:latest']);
        $this->assertSame('override:latest', $client->get_model());
    }
}
