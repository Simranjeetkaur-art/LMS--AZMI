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

namespace local_contentchecker\image;

/**
 * A searchable source of images an editor can insert.
 *
 * Same pattern as the enrichment asset registry and the reference fetcher: a
 * source is discovered through the registry and selected by configuration, so
 * one can be added or removed without touching the picker, the web service or
 * the insertion code.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface image_source {

    /**
     * Stable identifier used in config and in search requests.
     *
     * @return string The source id.
     */
    public function get_id(): string;

    /**
     * Human-readable name for the picker.
     *
     * @return string Localised name.
     */
    public function get_name(): string;

    /**
     * Is this source usable right now?
     *
     * A source needing an API key that has not been configured must return
     * false so the picker hides it rather than offering a tab that errors.
     *
     * @return bool True when it can be searched.
     */
    public function is_available(): bool;

    /**
     * Search for images.
     *
     * @param string $query Free-text search terms.
     * @param int $page One-based page number.
     * @param \context $context Context the search is being made from, so a
     *      source can respect what the caller is allowed to see.
     * @return image_result[] Results, best first.
     */
    public function search(string $query, int $page, \context $context): array;

    /**
     * Retrieve the bytes for a chosen result.
     *
     * Returning the content rather than a URL is what lets the inserter store
     * the image in Moodle's own file storage, so published content does not
     * depend on a third-party host staying up.
     *
     * @param string $ref The result's opaque handle.
     * @param \context $context Context the insert is being made from.
     * @return array {content: string, filename: string, mimetype: string}.
     */
    public function fetch(string $ref, \context $context): array;
}
