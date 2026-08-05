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
 * The custom pronunciation dictionary for the high-quality TTS voice.
 *
 * Medical vocabulary is exactly where a general-purpose voice fails, and it
 * fails in ways only a human listener notices. The dictionary is therefore a
 * simple term to replacement map an admin can extend over time rather than
 * anything the model is expected to learn.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pronunciation {

    /** @var string Cache key for the compiled dictionary. */
    const CACHE_KEY = 'dictionary';

    /**
     * Rewrite a passage using the dictionary.
     *
     * Word-match entries are applied with word boundaries so "ia" as a term
     * cannot corrupt "iatrogenic". Substring entries are offered for prefixes
     * and suffixes where that is genuinely wanted, but they are opt-in per
     * entry rather than the default.
     *
     * Longer terms are applied first so a multi-word entry is not broken up by
     * a single-word one that happens to match part of it.
     *
     * @param string $text The passage.
     * @return string The rewritten passage.
     */
    public static function apply(string $text): string {
        foreach (self::dictionary() as $entry) {
            if ($entry->matchtype === 'substring') {
                $text = str_ireplace($entry->term, $entry->replacement, $text);
                continue;
            }
            $pattern = '/\b' . preg_quote($entry->term, '/') . '\b/iu';
            $replaced = preg_replace($pattern,
                str_replace('$', '\\$', $entry->replacement), $text);
            if (is_string($replaced)) {
                $text = $replaced;
            }
        }
        return $text;
    }

    /**
     * The enabled dictionary entries, longest term first.
     *
     * @return array List of entry rows.
     */
    public static function dictionary(): array {
        $cache = \cache::make('local_contentchecker', 'pronunciation');
        $entries = $cache->get(self::CACHE_KEY);
        if ($entries !== false) {
            return $entries;
        }

        global $DB;
        $rows = array_values($DB->get_records('local_cchecker_pronounce',
            ['enabled' => 1], 'term ASC'));

        usort($rows, fn($a, $b) =>
            \core_text::strlen($b->term) <=> \core_text::strlen($a->term));

        $cache->set(self::CACHE_KEY, $rows);
        return $rows;
    }

    /**
     * Drop the cached dictionary after an edit.
     *
     * @return void
     */
    public static function invalidate(): void {
        \cache::make('local_contentchecker', 'pronunciation')->delete(self::CACHE_KEY);
    }
}
