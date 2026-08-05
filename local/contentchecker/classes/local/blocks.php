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

namespace local_contentchecker\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Splits page content into addressable blocks.
 *
 * Both the follow-up question widget and the read-aloud control need to agree
 * on what "a content block" is, and the agreement has to survive an editor
 * rewording a paragraph. Blocks are therefore cut at headings and keyed by a
 * hash of the heading text rather than by position: inserting a new section in
 * the middle of a page does not orphan the questions attached to the ones
 * after it.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blocks {

    /** @var int Shortest block worth generating questions for. */
    const MIN_CHARS = 240;

    /**
     * Split stored HTML into blocks at heading boundaries.
     *
     * @param string $html The stored HTML.
     * @return array List of block objects with ref, title, html and text.
     */
    public static function split(string $html): array {
        // Capture each heading with the content that follows it, plus any lead
        // content before the first heading.
        $parts = preg_split('/(<h[1-4][^>]*>.*?<\/h[1-4]>)/is', $html, -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        $blocks = [];
        $current = null;
        $seen = [];

        foreach ($parts as $part) {
            if (preg_match('/^<h[1-4][^>]*>(.*?)<\/h[1-4]>$/is', $part, $m)) {
                if ($current) {
                    $blocks[] = $current;
                }
                $title = trim(html_to_text($m[1], 0, false));
                $current = (object) [
                    'ref' => self::ref($title, $seen),
                    'title' => $title,
                    'html' => $part,
                    'text' => '',
                ];
                continue;
            }

            if (!$current) {
                $current = (object) [
                    'ref' => self::ref('', $seen),
                    'title' => '',
                    'html' => '',
                    'text' => '',
                ];
            }
            $current->html .= $part;
        }

        if ($current) {
            $blocks[] = $current;
        }

        foreach ($blocks as $block) {
            $block->text = trim(html_to_text($block->html, 0, false));
        }

        return array_values(array_filter($blocks,
            fn($b) => \core_text::strlen($b->text) >= self::MIN_CHARS));
    }

    /**
     * A stable reference for a block.
     *
     * Keyed on the heading text so the reference survives edits elsewhere in
     * the page. Duplicate headings on one page get a numeric suffix, since two
     * blocks sharing a reference would show each other's questions.
     *
     * @param string $title The heading text, or '' for the lead block.
     * @param array $seen Refs already issued for this page, by reference.
     * @return string The block reference.
     */
    protected static function ref(string $title, array &$seen): string {
        $base = $title === '' ? 'lead' : substr(sha1(\core_text::strtolower(trim($title))), 0, 12);
        $ref = 'b-' . $base;

        $n = 1;
        while (isset($seen[$ref])) {
            $ref = 'b-' . $base . '-' . (++$n);
        }
        $seen[$ref] = true;

        return $ref;
    }
}
