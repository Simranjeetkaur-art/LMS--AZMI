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

use mod_flashdeck\cardtype\manager;

/**
 * AI card generation on top of the self-hosted inference server.
 *
 * Nothing here is hardcoded to a subject, a course or a card type:
 *  - the system prompt is an admin setting (default from a translatable
 *    language string);
 *  - the JSON format contract is built from the card-type registry —
 *    every type that implements get_ai_example() is offered to the
 *    model, so new types become generatable automatically;
 *  - the model's output is only ever a PROPOSAL: each card is validated
 *    through its card type, and the teacher reviews a rendered preview
 *    and chooses what to import (via the same atomic porter as every
 *    other bulk import).
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_generator {

    /**
     * Card types the model may author: registry entries with an AI example.
     *
     * @return array identifier => ['description' => string, 'content' => array]
     */
    public static function get_generatable_types(): array {
        $types = [];
        foreach (manager::get_types() as $identifier => $type) {
            if ($example = $type->get_ai_example()) {
                $types[$identifier] = $example;
            }
        }
        return $types;
    }

    /**
     * Whether the feature is usable: server configured and types available.
     *
     * @param ai_client|null $client optionally a preconfigured client
     * @return bool
     */
    public static function is_available(?ai_client $client = null): bool {
        $client = $client ?? new ai_client();
        return $client->is_configured() && self::get_generatable_types() !== [];
    }

    /**
     * Build the chat messages for one generation request.
     *
     * @param string $source the teacher's source material or topic brief
     * @param string[] $typeids requested card types (subset of generatable)
     * @param int $count how many cards to ask for
     * @return array chat messages
     */
    public static function build_messages(string $source, array $typeids, int $count): array {
        $available = self::get_generatable_types();
        $typeids = array_values(array_intersect($typeids, array_keys($available)));
        if (!$typeids) {
            $typeids = array_keys($available);
        }

        $system = trim((string) get_config(ai_client::COMPONENT, 'aisystemprompt'));
        if ($system === '') {
            $system = get_string('aisystempromptdefault', 'mod_flashdeck');
        }

        $contract = get_string('aicontractintro', 'mod_flashdeck') . "\n";
        $contract .= '[{"cardtype": "...", "tags": "comma,separated,topics", "content": {...}}]' . "\n\n";
        $contract .= get_string('aicontracttypes', 'mod_flashdeck') . "\n";
        foreach ($typeids as $typeid) {
            $contract .= "- \"{$typeid}\": " . $available[$typeid]['description'] . "\n";
            $contract .= '  content example: '
                . json_encode($available[$typeid]['content'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                . "\n";
        }
        $contract .= "\n" . get_string('aicontractcount', 'mod_flashdeck',
            (object) ['count' => $count, 'types' => implode(', ', $typeids)]);

        return [
            ['role' => 'system', 'content' => $system . "\n\n" . $contract],
            ['role' => 'user', 'content' => get_string('aisourceintro', 'mod_flashdeck') . "\n\n" . $source],
        ];
    }

    /**
     * Extract card definitions from a model response, defensively.
     *
     * Tolerates reasoning-model think blocks, markdown code fences,
     * commentary around the JSON, and a {"cards": [...]} wrapper.
     *
     * @param string $text the raw assistant text
     * @return array list of ['cardtype' =>, 'tags' =>, 'content' =>]; empty when unusable
     */
    public static function parse_response(string $text): array {
        $text = preg_replace('/<think>.*?<\/think>/s', '', $text);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $text, $matches)) {
            $text = $matches[1];
        }

        $decoded = null;
        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        }
        if (!is_array($decoded)) {
            // Maybe an object wrapper such as {"cards": [...]}.
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $object = json_decode(substr($text, $start, $end - $start + 1), true);
                if (is_array($object)) {
                    $decoded = $object['cards'] ?? (isset($object['cardtype']) ? [$object] : null);
                }
            }
        }
        if (!is_array($decoded)) {
            return [];
        }

        $defs = [];
        foreach ($decoded as $item) {
            if (!is_array($item) || !is_string($item['cardtype'] ?? null) || !is_array($item['content'] ?? null)) {
                continue;
            }
            $defs[] = [
                'cardtype' => trim($item['cardtype']),
                'tags' => is_string($item['tags'] ?? null) ? trim($item['tags']) : null,
                'content' => $item['content'],
            ];
        }
        return $defs;
    }

    /**
     * Split proposals into valid card definitions and rejects with reasons.
     *
     * @param array $defs proposals from {@see parse_response()}
     * @param string[] $typeids the types that were requested (others rejected)
     * @return array [valid defs, rejects as ['def' =>, 'problem' =>]]
     */
    public static function validate_proposals(array $defs, array $typeids): array {
        $valid = [];
        $rejected = [];
        foreach ($defs as $def) {
            if (!manager::exists($def['cardtype']) || ($typeids && !in_array($def['cardtype'], $typeids, true))) {
                $rejected[] = ['def' => $def,
                    'problem' => get_string('errunknowncardtype', 'mod_flashdeck', $def['cardtype'])];
                continue;
            }
            if ($problems = manager::get($def['cardtype'])->validate_content($def['content'])) {
                $rejected[] = ['def' => $def, 'problem' => implode('; ', $problems)];
                continue;
            }
            $valid[] = $def;
        }
        return [$valid, $rejected];
    }

    /**
     * Run one full generation: prompt, inference, parse, validate.
     *
     * Nothing is written to the database — the result is a proposal for
     * the teacher's review.
     *
     * @param string $source the teacher's source material
     * @param string[] $typeids requested card types
     * @param int $count how many cards to ask for
     * @param ai_client|null $client optionally a preconfigured client
     * @return array ['cards' => array, 'rejected' => array, 'error' => string]
     */
    public static function generate(string $source, array $typeids, int $count,
            ?ai_client $client = null): array {
        $client = $client ?? new ai_client();

        $messages = self::build_messages($source, $typeids, $count);
        $result = $client->chat($messages);
        if ($result['error'] !== '') {
            return ['cards' => [], 'rejected' => [], 'error' => $result['error']];
        }

        $defs = self::parse_response($result['content']);
        if (!$defs) {
            return ['cards' => [], 'rejected' => [],
                'error' => get_string('erraiunparseable', 'mod_flashdeck')];
        }

        [$valid, $rejected] = self::validate_proposals($defs, $typeids);
        return ['cards' => $valid, 'rejected' => $rejected, 'error' => ''];
    }
}
