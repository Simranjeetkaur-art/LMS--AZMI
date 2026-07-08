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
 * Bulk card import and export.
 *
 * Three input formats, one output contract (validated card definitions):
 *  - JSON: {"name": ..., "cards": [{"cardtype", "tags", "content"}]} —
 *    covers every card type and round-trips exports byte-compatibly.
 *  - CSV: columns cardtype,tags,f1,f2,f3,f4 with documented per-type
 *    field mappings for the text-friendly types (see README).
 *  - GIFT: basic and cloze cards from the classic plain-text format the
 *    team already authors in (short answer, multichoice, true/false,
 *    missing-word).
 *
 * Every definition is validated through its card type BEFORE anything
 * is written, so a bad file imports nothing.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class porter {

    /** @var string[] CSV header row */
    const CSV_COLUMNS = ['cardtype', 'tags', 'f1', 'f2', 'f3', 'f4'];

    // ---------------------------------------------------------------- import.

    /**
     * Validate and insert a list of card definitions.
     *
     * @param \stdClass $deck the flashdeck record
     * @param array $carddefs list of ['cardtype' =>, 'tags' =>, 'content' => array]
     * @param int $userid recorded as the cards' author
     * @return int number of cards added
     * @throws \moodle_exception when any definition is invalid (nothing is written)
     */
    public static function import_cards(\stdClass $deck, array $carddefs, int $userid): int {
        global $DB;

        if (!$carddefs) {
            throw new \moodle_exception('errimportempty', 'mod_flashdeck');
        }

        $now = time();
        $position = (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(position), 0) FROM {flashdeck_cards} WHERE deckid = ?', [$deck->id]);

        $records = [];
        foreach (array_values($carddefs) as $i => $def) {
            $cardtype = $def['cardtype'] ?? '';
            if (!manager::exists($cardtype)) {
                throw new \moodle_exception('errimportcard', 'mod_flashdeck', '',
                    (object) ['line' => $i + 1, 'problem' => "unknown card type '{$cardtype}'"]);
            }
            $content = $def['content'] ?? [];
            if ($problems = manager::get($cardtype)->validate_content($content)) {
                throw new \moodle_exception('errimportcard', 'mod_flashdeck', '',
                    (object) ['line' => $i + 1, 'problem' => implode('; ', $problems)]);
            }
            $records[] = (object) [
                'deckid' => $deck->id,
                'cardtype' => $cardtype,
                'position' => ++$position,
                'tags' => $def['tags'] ?? null,
                'content' => json_encode($content),
                'usermodified' => $userid,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
        }

        $DB->insert_records('flashdeck_cards', $records);
        return count($records);
    }

    /**
     * Import cards from a JSON export/sample file.
     *
     * @param \stdClass $deck the flashdeck record
     * @param string $json the file content
     * @param int $userid recorded as the cards' author
     * @return int number of cards added
     */
    public static function import_json(\stdClass $deck, string $json, int $userid): int {
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['cards']) || !is_array($decoded['cards'])) {
            throw new \moodle_exception('errimportformat', 'mod_flashdeck', '', 'JSON');
        }
        return self::import_cards($deck, $decoded['cards'], $userid);
    }

    /**
     * Import cards from CSV (columns: cardtype,tags,f1,f2,f3,f4).
     *
     * @param \stdClass $deck the flashdeck record
     * @param string $csv the file content
     * @param int $userid recorded as the cards' author
     * @return int number of cards added
     */
    public static function import_csv(\stdClass $deck, string $csv, int $userid): int {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $iid = \csv_import_reader::get_new_iid('mod_flashdeck');
        $reader = new \csv_import_reader($iid, 'mod_flashdeck');
        if ($reader->load_csv_content($csv, 'utf-8', 'comma') === false) {
            $reader->cleanup();
            throw new \moodle_exception('errimportformat', 'mod_flashdeck', '', 'CSV');
        }

        $columns = array_map('strtolower', array_map('trim', (array) $reader->get_columns()));
        if (!in_array('cardtype', $columns, true)) {
            $reader->close();
            $reader->cleanup();
            throw new \moodle_exception('errimportformat', 'mod_flashdeck', '', 'CSV');
        }

        $carddefs = [];
        $reader->init();
        while ($row = $reader->next()) {
            $fields = [];
            foreach ($columns as $index => $column) {
                $fields[$column] = trim((string) ($row[$index] ?? ''));
            }
            if ($fields['cardtype'] === '') {
                continue;
            }
            $content = self::content_from_csv($fields['cardtype'], $fields);
            $carddefs[] = [
                'cardtype' => $fields['cardtype'],
                'tags' => $fields['tags'] !== '' ? $fields['tags'] : null,
                'content' => $content,
            ];
        }
        $reader->close();
        $reader->cleanup();

        return self::import_cards($deck, $carddefs, $userid);
    }

    /**
     * Import basic and cloze cards from GIFT plain text.
     *
     * @param \stdClass $deck the flashdeck record
     * @param string $gift the file content
     * @param int $userid recorded as the cards' author
     * @return int number of cards added
     */
    public static function import_gift(\stdClass $deck, string $gift, int $userid): int {
        return self::import_cards($deck, self::parse_gift($gift), $userid);
    }

    /**
     * Parse GIFT text into card definitions (basic and cloze).
     *
     * Supported: short answer {=a =b}, multichoice {=right ~wrong},
     * true/false {T}/{FALSE}, and the missing-word format (text after
     * the braces), which maps onto a cloze blank. Comments, ::titles::,
     * category lines, format prefixes and per-answer #feedback are
     * stripped. Unsupported entries (essays, numerical, matching) are
     * skipped rather than mangled.
     *
     * @param string $text GIFT source
     * @return array list of card definitions for {@see import_cards()}
     */
    public static function parse_gift(string $text): array {
        // Normalise newlines, drop comment lines.
        $text = preg_replace('/^\/\/.*$/m', '', str_replace(["\r\n", "\r"], "\n", $text));

        $carddefs = [];
        foreach (preg_split('/\n\s*\n/', $text) as $entry) {
            $entry = trim($entry);
            if ($entry === '' || str_starts_with($entry, '$CATEGORY')) {
                continue;
            }
            // Optional ::title:: prefix and format hints.
            $entry = preg_replace('/^::.*?::/s', '', $entry);
            $entry = preg_replace('/\[(html|moodle|plain|markdown)\]/', '', $entry);

            // Protect escaped control characters during parsing.
            $entry = strtr($entry, ['\\=' => "\x01", '\\~' => "\x02", '\\{' => "\x03",
                '\\}' => "\x04", '\\:' => ':', '\\#' => "\x05"]);

            $open = strpos($entry, '{');
            $close = strrpos($entry, '}');
            if ($open === false || $close === false || $close < $open) {
                continue;
            }
            $before = trim(substr($entry, 0, $open));
            $inside = trim(substr($entry, $open + 1, $close - $open - 1));
            $after = trim(substr($entry, $close + 1));

            $unescape = static fn(string $s): string => trim(strtr($s,
                ["\x01" => '=', "\x02" => '~', "\x03" => '{', "\x04" => '}', "\x05" => '#']));

            if ($before === '') {
                continue;
            }

            // True/false.
            if (preg_match('/^(TRUE|FALSE|T|F)\s*(#.*)?$/is', $inside, $matches)) {
                $answer = in_array(strtoupper($matches[1]), ['TRUE', 'T'], true)
                    ? get_string('true', 'core_question') : get_string('false', 'core_question');
                $carddefs[] = ['cardtype' => 'basic', 'tags' => null, 'content' => [
                    'front' => $unescape($before), 'frontformat' => FORMAT_MOODLE,
                    'back' => $answer, 'backformat' => FORMAT_MOODLE,
                ]];
                continue;
            }

            // Collect correct answers (= tokens), stripping #feedback.
            $corrects = [];
            if (preg_match_all('/=\s*(?:%-?\d+(?:\.\d+)?%)?([^=~]*)/s', $inside, $matches)) {
                foreach ($matches[1] as $answer) {
                    $answer = $unescape(preg_replace('/#.*$/s', '', $answer));
                    if ($answer !== '') {
                        $corrects[] = $answer;
                    }
                }
            }
            if (!$corrects) {
                continue;
            }

            if ($after !== '') {
                // Missing-word format: the blank sits between before and after.
                $carddefs[] = ['cardtype' => 'cloze', 'tags' => null, 'content' => [
                    'text' => $unescape($before) . ' [[' . implode('|', $corrects) . ']] ' . $unescape($after),
                    'casesensitive' => false,
                ]];
            } else {
                $carddefs[] = ['cardtype' => 'basic', 'tags' => null, 'content' => [
                    'front' => $unescape($before), 'frontformat' => FORMAT_MOODLE,
                    'back' => implode(' / ', $corrects), 'backformat' => FORMAT_MOODLE,
                ]];
            }
        }
        return $carddefs;
    }

    // ---------------------------------------------------------------- export.

    /**
     * Export a deck's cards as JSON (round-trips through import_json).
     *
     * @param \stdClass $deck the flashdeck record
     * @param \stdClass[] $cards flashdeck_cards records
     * @return string pretty-printed JSON
     */
    public static function export_json(\stdClass $deck, array $cards): string {
        $out = ['name' => $deck->name, 'cards' => []];
        foreach ($cards as $card) {
            $out['cards'][] = [
                'cardtype' => $card->cardtype,
                'tags' => $card->tags,
                'content' => json_decode($card->content, true),
            ];
        }
        return json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Export a deck's cards as CSV. Types without a CSV mapping
     * (imagelabel) are skipped — use JSON for full fidelity.
     *
     * @param \stdClass $deck the flashdeck record
     * @param \stdClass[] $cards flashdeck_cards records
     * @return string CSV content
     */
    public static function export_csv(\stdClass $deck, array $cards): string {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $writer = new \csv_export_writer('comma');
        $writer->add_data(self::CSV_COLUMNS);
        foreach ($cards as $card) {
            $content = json_decode($card->content, true) ?: [];
            if ($fields = self::content_to_csv($card->cardtype, $content)) {
                $writer->add_data(array_merge([$card->cardtype, (string) $card->tags], $fields));
            }
        }
        return $writer->print_csv_data(true);
    }

    /**
     * Map a card's content onto the four CSV payload fields.
     *
     * @param string $cardtype the type identifier
     * @param array $content decoded content JSON
     * @return array|null [f1, f2, f3, f4], or null when the type has no CSV form
     */
    public static function content_to_csv(string $cardtype, array $content): ?array {
        switch ($cardtype) {
            case 'basic':
                return [$content['front'] ?? '', $content['back'] ?? '', '', ''];
            case 'qanda':
                return [$content['front'] ?? '', $content['back'] ?? '', $content['guidance'] ?? '', ''];
            case 'cloze':
                return [$content['text'] ?? '', empty($content['casesensitive']) ? '0' : '1', '', ''];
            case 'termdissection':
                $parts = [];
                foreach ($content['parts'] ?? [] as $part) {
                    $parts[] = $part['text'] . ':' . $part['role']
                        . (($part['meaning'] ?? '') !== '' ? ':' . $part['meaning'] : '');
                }
                return [$content['term'] ?? '', $content['definition'] ?? '', implode('|', $parts), ''];
            case 'matching':
                $pairs = [];
                foreach ($content['pairs'] ?? [] as $pair) {
                    $pairs[] = $pair['left'] . '=' . $pair['right'];
                }
                return [$content['prompt'] ?? '', implode('|', $pairs), '', ''];
            case 'ordering':
                return [$content['prompt'] ?? '', implode('|', $content['items'] ?? []), '', ''];
            case 'comparecontrast':
                $rows = [];
                foreach ($content['rows'] ?? [] as $row) {
                    $rows[] = $row['aspect'] . ';' . $row['a'] . ';' . $row['b'];
                }
                return [$content['prompt'] ?? '', ($content['columna'] ?? '') . '|' . ($content['columnb'] ?? ''),
                    implode('|', $rows), ''];
            default:
                // No lossless plain-text form (e.g. imagelabel): JSON only.
                return null;
        }
    }

    /**
     * Build content JSON from the four CSV payload fields.
     *
     * @param string $cardtype the type identifier
     * @param array $fields associative row with f1..f4
     * @return array content payload (validated later by the card type)
     */
    public static function content_from_csv(string $cardtype, array $fields): array {
        $f1 = $fields['f1'] ?? '';
        $f2 = $fields['f2'] ?? '';
        $f3 = $fields['f3'] ?? '';

        switch ($cardtype) {
            case 'basic':
                return ['front' => $f1, 'frontformat' => FORMAT_HTML,
                    'back' => $f2, 'backformat' => FORMAT_HTML];
            case 'qanda':
                return ['front' => $f1, 'frontformat' => FORMAT_HTML,
                    'back' => $f2, 'backformat' => FORMAT_HTML, 'guidance' => $f3];
            case 'cloze':
                return ['text' => $f1, 'casesensitive' => $f2 === '1'];
            case 'termdissection':
                $parts = [];
                foreach (array_filter(explode('|', $f3)) as $encoded) {
                    $bits = explode(':', $encoded, 3);
                    $parts[] = ['text' => trim($bits[0]), 'role' => trim($bits[1] ?? 'root'),
                        'meaning' => trim($bits[2] ?? '')];
                }
                return ['term' => $f1, 'definition' => $f2, 'parts' => $parts];
            case 'matching':
                $pairs = [];
                foreach (array_filter(explode('|', $f2)) as $encoded) {
                    $bits = explode('=', $encoded, 2);
                    $pairs[] = ['left' => trim($bits[0]), 'right' => trim($bits[1] ?? '')];
                }
                return ['prompt' => $f1, 'pairs' => $pairs];
            case 'ordering':
                return ['prompt' => $f1,
                    'items' => array_values(array_filter(array_map('trim', explode('|', $f2))))];
            case 'comparecontrast':
                $columns = explode('|', $f2, 2);
                $rows = [];
                foreach (array_filter(explode('|', $f3)) as $encoded) {
                    $bits = explode(';', $encoded, 3);
                    $rows[] = ['aspect' => trim($bits[0]), 'a' => trim($bits[1] ?? ''),
                        'b' => trim($bits[2] ?? '')];
                }
                return ['prompt' => $f1, 'columna' => trim($columns[0] ?? ''),
                    'columnb' => trim($columns[1] ?? ''), 'rows' => $rows];
            default:
                return [];
        }
    }
}
