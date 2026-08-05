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
 * Writes an approved correction into the live activity it came from.
 *
 * Nothing reaches this class without an explicit human decision -- there is no
 * code path that applies a model suggestion automatically.
 *
 * The replacement is located by matching the ORIGINAL sentence in the stored
 * HTML, not by regenerating the field. That keeps every other byte of the page
 * -- images, embeds, markup, adjacent paragraphs -- untouched.
 *
 * When the sentence cannot be located with certainty the write is refused and
 * reported as such. Refusing is the correct outcome: a fuzzy match that lands
 * in the wrong sentence of a clinical page is far worse than an editor being
 * told to make the change by hand.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class applier {

    /**
     * Apply an approved correction.
     *
     * @param \stdClass $suggestion Row from local_cchecker_suggestions.
     * @param string $newtext The text to write; the editor may have amended it.
     * @return array {applied: bool, reason: string|null, before: string, after: string}.
     */
    public static function apply(\stdClass $suggestion, string $newtext): array {
        global $DB;

        $original = trim((string) $suggestion->currenttext);
        if ($original === '') {
            return self::refuse('apply:nooriginal');
        }

        $item = self::locate_item($suggestion);
        if (!$item) {
            return self::refuse('apply:itemgone');
        }

        $record = $DB->get_record($item->table, ['id' => $item->recordid]);
        if (!$record) {
            return self::refuse('apply:itemgone');
        }

        $before = (string) $record->{$item->field};
        $after = self::replace_in_html($before, $original, $newtext);

        if ($after === null) {
            return self::refuse('apply:notlocated');
        }
        if ($after === $before) {
            return self::refuse('apply:nochange');
        }

        $record->{$item->field} = $after;
        if (isset($record->timemodified)) {
            $record->timemodified = time();
        }
        $DB->update_record($item->table, $record);

        self::invalidate($suggestion, $item);

        return ['applied' => true, 'reason' => null, 'before' => $before, 'after' => $after];
    }

    /**
     * Build a refusal result.
     *
     * @param string $reason Language string key describing why.
     * @return array The refusal.
     */
    protected static function refuse(string $reason): array {
        return ['applied' => false, 'reason' => $reason, 'before' => '', 'after' => ''];
    }

    /**
     * Find the content item a suggestion came from.
     *
     * Re-derived from the live course rather than trusted from the stored row,
     * because the activity may have been edited or deleted since the check ran.
     *
     * @param \stdClass $suggestion The suggestion row.
     * @return \stdClass|null The matching content item.
     */
    protected static function locate_item(\stdClass $suggestion): ?\stdClass {
        $original = trim((string) $suggestion->currenttext);
        foreach (content_source::for_cm((int) $suggestion->cmid) as $item) {
            if (strpos($item->text, $original) !== false) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Replace a plain-text sentence inside stored HTML.
     *
     * The sentence was extracted with html_to_text, so it will not always
     * appear literally in the markup: inline tags and HTML entities can sit
     * between its words. Two attempts are made, in order of confidence:
     *
     *  1. A literal match, which covers the common case of unmarked prose.
     *  2. A tag-tolerant match that allows tags, entities and whitespace
     *     BETWEEN words but never inside one. Matching within a word would
     *     start accepting coincidental fragments, and a wrong match here
     *     silently rewrites the wrong clinical sentence.
     *
     * If the pattern matches more than once the replacement is refused, since
     * there is no way to know which occurrence the model was judging.
     *
     * @param string $html The stored HTML.
     * @param string $original The sentence to replace.
     * @param string $replacement The new sentence.
     * @return string|null The new HTML, or null when it could not be located.
     */
    public static function replace_in_html(string $html, string $original,
            string $replacement): ?string {
        $safe = s($replacement);

        $count = substr_count($html, $original);
        if ($count === 1) {
            return str_replace($original, $safe, $html);
        }
        if ($count > 1) {
            return null; // Ambiguous: refuse rather than guess.
        }

        $pattern = self::tolerant_pattern($original);
        if ($pattern === null) {
            return null;
        }

        $matches = preg_match_all($pattern, $html);
        if ($matches !== 1) {
            return null;
        }

        $result = preg_replace($pattern, str_replace('$', '\\$', $safe), $html, 1);
        return is_string($result) ? $result : null;
    }

    /**
     * A regex matching a sentence across intervening markup.
     *
     * @param string $original The sentence.
     * @return string|null The pattern, or null when the sentence is too short
     *      to match safely.
     */
    protected static function tolerant_pattern(string $original): ?string {
        $words = preg_split('/\s+/u', trim($original), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        // A two-word "sentence" would match far too much.
        if (count($words) < 4) {
            return null;
        }

        $parts = [];
        foreach ($words as $word) {
            $escaped = preg_quote($word, '/');
            // The stored HTML may carry entity forms of characters that
            // html_to_text handed back decoded.
            $escaped = str_replace(
                ['&', '<', '>', '"', "'"],
                ['(?:&|&amp;)', '(?:<|&lt;)', '(?:>|&gt;)', '(?:"|&quot;)', "(?:'|&#0?39;|&apos;|&rsquo;|\xE2\x80\x99)"],
                $escaped);
            $parts[] = $escaped;
        }

        // Tags, entities and whitespace are allowed between words only.
        $gap = '(?:\s|&nbsp;|<[^>]*>)+';
        return '/' . implode($gap, $parts) . '/u';
    }

    /**
     * Clear the caches that would otherwise keep serving the old text.
     *
     * @param \stdClass $suggestion The suggestion row.
     * @param \stdClass $item The content item that was written to.
     * @return void
     */
    protected static function invalidate(\stdClass $suggestion, \stdClass $item): void {
        global $DB;

        $cm = $DB->get_record('course_modules', ['id' => $suggestion->cmid]);
        if (!$cm) {
            return;
        }

        // Formatted-text and module-info caches would otherwise keep serving
        // the old sentence after the row itself has changed.
        rebuild_course_cache((int) $cm->course, true);
        \cache_helper::purge_by_event('changesincourse');
    }
}
