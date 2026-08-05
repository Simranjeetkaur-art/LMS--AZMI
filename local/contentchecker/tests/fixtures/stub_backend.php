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

use local_contentchecker\api\ai_backend;

/**
 * An AI backend that answers from a script instead of a GPU.
 *
 * Tests must never depend on a live model: it is slow, it is not deterministic,
 * and it would make the suite fail whenever the GPU box is busy. Injecting this
 * is what the ai_backend interface is for.
 *
 * This lives in tests/fixtures/ and is require_once'd rather than autoloaded,
 * because Moodle's class loader maps local_contentchecker\* to classes/ only.
 * Putting it in classes/ would autoload it but would also ship a test double
 * to production.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stub_backend implements ai_backend {

    /** @var array Queued responses, returned in order. */
    protected $responses;

    /** @var array Prompts this backend was asked, for assertions. */
    public $prompts = [];

    /** @var array Output ceilings requested, so callers can assert on them. */
    public $budgets = [];

    /**
     * Constructor.
     *
     * @param array $responses Decoded objects to return in order.
     */
    public function __construct(array $responses = []) {
        $this->responses = $responses;
    }

    /**
     * Always healthy.
     *
     * @return bool Always true.
     */
    public function healthy(): bool {
        return true;
    }

    /**
     * Return the next scripted response as JSON.
     *
     * @param string $model Model name.
     * @param string $prompt The prompt.
     * @param array|null $schema JSON Schema.
     * @param int|null $maxtokens Requested output ceiling.
     * @return string Generated text.
     */
    public function generate(string $model, string $prompt, ?array $schema = null,
            ?int $maxtokens = null): string {
        $this->prompts[] = $prompt;
        $this->budgets[] = $maxtokens;
        return json_encode(array_shift($this->responses) ?? []);
    }

    /**
     * Return the next scripted response.
     *
     * @param string $model Model name.
     * @param string $prompt The prompt.
     * @param array $schema JSON Schema.
     * @param int|null $maxtokens Requested output ceiling.
     * @return array Decoded object.
     */
    public function generate_json(string $model, string $prompt, array $schema,
            ?int $maxtokens = null): array {
        $this->prompts[] = $prompt;
        $this->budgets[] = $maxtokens;
        return array_shift($this->responses) ?? [];
    }

    /**
     * A deterministic pseudo-embedding.
     *
     * Derived from the text so that identical text embeds identically and
     * different text does not, which is all the dedupe logic needs.
     *
     * @param string $text Text to embed.
     * @return array Float vector.
     */
    public function embed(string $text): array {
        $hash = md5($text);
        $vector = [];
        for ($i = 0; $i < 16; $i++) {
            $vector[] = hexdec(substr($hash, $i * 2, 2)) / 255;
        }
        return $vector;
    }
}
