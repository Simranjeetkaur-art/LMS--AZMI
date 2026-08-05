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
 * The selectable publish layouts.
 *
 * A layout choice is stored per section (and optionally per page within it) and
 * is re-editable at any time: it is a row that gets updated, never a one-way
 * transformation of the content itself. That matters because the content stays
 * in its own activity in its original format -- the layout only decides how the
 * published week is arranged around it.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class templates {

    /**
     * The available layouts and the slots each one fills.
     *
     * @return array templatekey => [mustache, slots].
     */
    public static function all(): array {
        return [
            'medialeft' => [
                'mustache' => 'local_contentchecker/layout_medialeft',
                'slots' => ['media', 'body'],
            ],
            'stackedcallout' => [
                'mustache' => 'local_contentchecker/layout_stackedcallout',
                'slots' => ['body', 'callouts'],
            ],
            'tabbedcomparison' => [
                'mustache' => 'local_contentchecker/layout_tabbedcomparison',
                'slots' => ['intro', 'tabs'],
            ],
            'fullwidthvisual' => [
                'mustache' => 'local_contentchecker/layout_fullwidthvisual',
                'slots' => ['media', 'body', 'sidebar'],
            ],
        ];
    }

    /**
     * Layouts as a dropdown menu.
     *
     * @return array templatekey => localised name.
     */
    public static function menu(): array {
        $menu = [];
        foreach (array_keys(self::all()) as $key) {
            $menu[$key] = get_string('layout:' . $key, 'local_contentchecker');
        }
        return $menu;
    }

    /**
     * Is this a layout we actually ship?
     *
     * @param string $key The layout key.
     * @return bool True when known.
     */
    public static function exists(string $key): bool {
        return array_key_exists($key, self::all());
    }

    /**
     * The stored choice for a section or page.
     *
     * A page-level choice wins over the section-level one; when neither is set
     * the caller gets null and should fall back to the site default rather than
     * being handed a layout nobody picked.
     *
     * @param int $sectionid Section row id.
     * @param int $cmid Course module id, or 0 for the section itself.
     * @return \stdClass|null The stored row.
     */
    public static function get(int $sectionid, int $cmid = 0): ?\stdClass {
        global $DB;

        if ($cmid) {
            $row = $DB->get_record('local_cchecker_templates',
                ['sectionid' => $sectionid, 'cmid' => $cmid]);
            if ($row) {
                return $row;
            }
        }
        return $DB->get_record('local_cchecker_templates',
            ['sectionid' => $sectionid, 'cmid' => 0]) ?: null;
    }

    /**
     * Store a layout choice.
     *
     * @param int $courseid Course id.
     * @param int $sectionid Section row id.
     * @param int $cmid Course module id, or 0 for the whole section.
     * @param string $templatekey Which layout.
     * @param array $config Slot assignments.
     * @return \stdClass The stored row.
     */
    public static function set(int $courseid, int $sectionid, int $cmid,
            string $templatekey, array $config = []): \stdClass {
        global $DB, $USER;

        if (!self::exists($templatekey)) {
            throw new \moodle_exception('error:unknowntemplate', 'local_contentchecker');
        }

        $now = time();
        $existing = $DB->get_record('local_cchecker_templates',
            ['sectionid' => $sectionid, 'cmid' => $cmid]);

        $before = $existing ? $existing->templatekey : null;

        if ($existing) {
            $existing->templatekey = $templatekey;
            $existing->config = json_encode($config);
            $existing->usermodified = (int) $USER->id;
            $existing->timemodified = $now;
            $DB->update_record('local_cchecker_templates', $existing);
            $record = $existing;
        } else {
            $record = (object) [
                'courseid' => $courseid,
                'sectionid' => $sectionid,
                'cmid' => $cmid,
                'templatekey' => $templatekey,
                'config' => json_encode($config),
                'usermodified' => (int) $USER->id,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('local_cchecker_templates', $record);
        }

        audit::log('template', (int) $record->id, $existing ? 'updated' : 'created', [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'before' => $before,
            'after' => $templatekey,
        ]);

        return $record;
    }

    /**
     * Build the render context for a layout.
     *
     * Slots the chosen layout does not declare are dropped rather than passed
     * through, so switching layout cannot leak a stale slot into the output.
     *
     * @param string $templatekey Which layout.
     * @param array $config Stored slot assignments.
     * @return array Mustache context.
     */
    public static function context(string $templatekey, array $config): array {
        $spec = self::all()[$templatekey] ?? null;
        if (!$spec) {
            throw new \moodle_exception('error:unknowntemplate', 'local_contentchecker');
        }

        $context = ['templatekey' => $templatekey];
        foreach ($spec['slots'] as $slot) {
            $value = $config[$slot] ?? null;

            if (in_array($slot, ['callouts', 'tabs'], true)) {
                $context[$slot] = array_values(array_map(fn($entry) => [
                    'title' => (string) ($entry['title'] ?? ''),
                    'body' => (string) ($entry['body'] ?? ''),
                ], is_array($value) ? $value : []));
                continue;
            }

            $context[$slot] = (string) $value;
        }

        // Mustache needs to know which tab is first without an index helper.
        if (!empty($context['tabs'])) {
            $context['tabs'][0]['active'] = true;
            foreach ($context['tabs'] as $i => $tab) {
                $context['tabs'][$i]['tabid'] = 'cct-' . $i;
            }
        }

        return $context;
    }
}
