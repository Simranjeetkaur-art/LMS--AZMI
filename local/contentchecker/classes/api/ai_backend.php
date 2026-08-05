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

/**
 * The contract every AI backend satisfies.
 *
 * Calling code -- the pipeline, the question generator -- depends only on this.
 * The GPU server's request/response shape is still being finalised, so keeping
 * that detail behind an interface is what lets the real contract be dropped in
 * later by writing one class, with no change to any caller.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface ai_backend {

    /**
     * Is the backend reachable?
     *
     * @return bool True when it answers.
     */
    public function healthy(): bool;

    /**
     * Generate text.
     *
     * @param string $model Model name.
     * @param string $prompt The prompt.
     * @param array|null $schema JSON Schema constraining the output shape.
     * @return string Generated text.
     */
    public function generate(string $model, string $prompt, ?array $schema = null): string;

    /**
     * Generate and decode a JSON object in one step.
     *
     * @param string $model Model name.
     * @param string $prompt The prompt.
     * @param array $schema JSON Schema.
     * @return array Decoded object.
     */
    public function generate_json(string $model, string $prompt, array $schema): array;

    /**
     * Embed a passage as a float vector.
     *
     * @param string $text Text to embed.
     * @return array Float vector.
     */
    public function embed(string $text): array;
}
