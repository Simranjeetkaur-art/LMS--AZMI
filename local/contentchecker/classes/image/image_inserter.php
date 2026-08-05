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

use local_contentchecker\local\audit;
use local_contentchecker\local\content_source;
use local_contentchecker\local\enrichment;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// license_manager is a global class in licenselib.php, not autoloaded. Without
// this, inserting an image from a source that has not already pulled in
// repository/lib.php fatals inside map_license().
require_once($CFG->libdir . '/licenselib.php');

/**
 * Materialises a chosen image into course content.
 *
 * The image is COPIED into the target activity's own file area rather than
 * hotlinked. Two reasons, both of which matter for teaching material:
 *
 *  - A remote host going away, rate-limiting or rewriting a URL would silently
 *    break published content that people are being taught from.
 *  - Course backup and restore carries the activity's file area with it. A
 *    hotlink survives backup only as long as the third party does.
 *
 * Attribution is stored in three places, deliberately: in the file's own
 * author and licence columns (Moodle's native fields for exactly this, and
 * they survive backup/restore), in a visible figcaption, and in the audit
 * trail. CC licences require the credit to travel with the work, so it cannot
 * live only in a database column an editor could later miss.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class image_inserter {

    /**
     * Insert an image into an activity.
     *
     * @param int $cmid Target course module.
     * @param int $recordid Target record within that module.
     * @param string $sourceid Which image source the result came from.
     * @param string $ref The result's opaque handle.
     * @param array $meta Attribution fields carried back from the picker.
     * @return array {ok, html, filename}.
     */
    public static function insert(int $cmid, int $recordid, string $sourceid,
            string $ref, array $meta): array {
        global $DB, $USER;

        $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance((int) $cm->course);
        require_capability('local/contentchecker:manage', $coursecontext);

        $item = self::locate_item($cmid, $recordid);
        if (!$item) {
            throw new \moodle_exception('error:imagetargetunsupported', 'local_contentchecker');
        }

        $source = source_registry::get($sourceid);
        $fetched = $source->fetch($ref, $coursecontext);

        $area = content_source::file_area($item);
        $fs = get_file_storage();

        // Prefer the image's title for the stored name: remote URLs routinely
        // end in an opaque hash or base64 blob, which is unreadable for anyone
        // browsing the activity's files later. Extension comes from the
        // sniffed bytes, not the title.
        $filename = self::name_from_title(
            (string) ($meta['title'] ?? ''), $fetched['filename']);

        // A collision would otherwise silently serve the previously stored
        // image under the new caption.
        $filename = self::unique_filename($fs, $area, $filename);

        $attribution = trim((string) ($meta['attribution'] ?? ''));
        $author = trim((string) ($meta['author'] ?? ''));
        $license = self::map_license((string) ($meta['license'] ?? ''));

        $file = $fs->create_file_from_string([
            'contextid' => $area['contextid'],
            'component' => $area['component'],
            'filearea' => $area['filearea'],
            'itemid' => $area['itemid'],
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $USER->id,
            // Moodle's native licensing columns. Course backup carries these,
            // so the credit survives the content being moved to another site.
            'author' => $author !== '' ? $author : $attribution,
            'license' => $license,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $fetched['content']);

        $html = self::render($filename, $meta, $file->get_id());

        if (!enrichment::insert_into_cm($cmid, $html, $recordid)) {
            // The file is already stored; leaving it orphaned would litter the
            // file area with an image nothing references.
            $file->delete();
            return ['ok' => false, 'html' => '', 'filename' => ''];
        }

        audit::log('image', $cmid, 'inserted', [
            'courseid' => (int) $cm->course,
            'cmid' => $cmid,
            'after' => $filename,
            'detail' => [
                'source' => $sourceid,
                'title' => $meta['title'] ?? '',
                'author' => $author,
                'license' => $meta['license'] ?? '',
                'licenseurl' => $meta['licenseurl'] ?? '',
                'attribution' => $attribution,
                'origin' => $meta['landingurl'] ?? '',
            ],
        ]);

        return ['ok' => true, 'html' => $html, 'filename' => $filename];
    }

    /**
     * Find the content item being inserted into.
     *
     * @param int $cmid Course module id.
     * @param int $recordid Record id within the module.
     * @return \stdClass|null The item.
     */
    protected static function locate_item(int $cmid, int $recordid): ?\stdClass {
        foreach (content_source::for_cm($cmid) as $item) {
            if ($recordid === 0 || (int) $item->recordid === $recordid) {
                return $item;
            }
        }
        return null;
    }

    /**
     * A readable filename built from the image's title.
     *
     * Falls back to the source's own filename when there is no usable title,
     * so this can never produce an empty or extension-less name.
     *
     * @param string $title The image title, possibly empty.
     * @param string $fallback Filename supplied by the source.
     * @return string A filename with the fallback's extension.
     */
    protected static function name_from_title(string $title, string $fallback): string {
        $ext = pathinfo($fallback, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? '.' . strtolower($ext) : '.jpg';

        $slug = \core_text::strtolower(trim($title));
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug);
        $slug = trim((string) $slug, '-');
        $slug = clean_param($slug, PARAM_FILE);

        if ($slug === '' || \core_text::strlen($slug) < 3) {
            return $fallback;
        }

        return \core_text::substr($slug, 0, 70) . $ext;
    }

    /**
     * A filename not already taken in the target area.
     *
     * @param \file_storage $fs File storage.
     * @param array $area The target file area.
     * @param string $filename Desired filename.
     * @return string A free filename.
     */
    protected static function unique_filename(\file_storage $fs, array $area,
            string $filename): string {
        $filename = clean_param($filename, PARAM_FILE);
        if ($filename === '') {
            $filename = 'image.jpg';
        }

        $info = pathinfo($filename);
        $base = $info['filename'] ?? 'image';
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';

        $candidate = $filename;
        $n = 1;
        while ($fs->file_exists($area['contextid'], $area['component'], $area['filearea'],
                $area['itemid'], '/', $candidate)) {
            $candidate = $base . '-' . (++$n) . $ext;
        }
        return $candidate;
    }

    /**
     * Map a source licence string onto a Moodle licence shortname.
     *
     * Falls back to "unknown" rather than guessing: recording the wrong licence
     * on republishable teaching material is worse than recording none, and the
     * human-readable attribution is preserved in the caption regardless.
     *
     * @param string $license Licence code from the source.
     * @return string A Moodle licence shortname.
     */
    protected static function map_license(string $license): string {
        $license = strtolower(trim($license));
        if ($license === '') {
            return 'unknown';
        }

        $known = array_keys(\license_manager::get_licenses());

        // Openverse returns e.g. "by-sa-2.0"; Moodle ships "cc-4.0",
        // "cc-sa-4.0" and friends. Try progressively looser matches.
        $candidates = [
            $license,
            'cc-' . $license,
            preg_replace('/-\d+(\.\d+)?$/', '', $license),
            'cc-' . preg_replace('/-\d+(\.\d+)?$/', '', $license),
        ];
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $known, true)) {
                return $candidate;
            }
        }

        if (str_starts_with($license, 'cc0') || $license === 'pdm') {
            return in_array('public', $known, true) ? 'public' : 'unknown';
        }

        return 'unknown';
    }

    /**
     * Build the figure markup.
     *
     * The src is @@PLUGINFILE@@, which is what Moodle rewrites at render time
     * into a correctly permissioned pluginfile URL. Writing an absolute URL
     * instead is the classic way to produce content that works for its author
     * and 404s for everyone else.
     *
     * @param string $filename The stored filename.
     * @param array $meta Attribution fields.
     * @param int $fileid Stored file id, recorded for traceability.
     * @return string HTML.
     */
    protected static function render(string $filename, array $meta, int $fileid): string {
        $alt = trim((string) ($meta['title'] ?? ''));
        if ($alt === '') {
            $alt = get_string('image:defaultalt', 'local_contentchecker');
        }

        $img = \html_writer::empty_tag('img', [
            'src' => '@@PLUGINFILE@@/' . rawurlencode($filename),
            'alt' => $alt,
            'class' => 'img-fluid',
            'loading' => 'lazy',
        ]);

        $caption = '';
        $attribution = trim((string) ($meta['attribution'] ?? ''));
        if ($attribution !== '') {
            $text = s($attribution);
            $licenseurl = trim((string) ($meta['licenseurl'] ?? ''));
            if ($licenseurl !== '') {
                $text .= ' ' . \html_writer::link($licenseurl,
                    get_string('image:licence', 'local_contentchecker'),
                    ['target' => '_blank', 'rel' => 'noopener noreferrer license']);
            }
            $caption = \html_writer::tag('figcaption',
                \html_writer::tag('small', $text, ['class' => 'text-muted']),
                ['class' => 'cct-asset-caption']);
        }

        return \html_writer::tag('figure', $img . $caption, [
            'class' => 'cct-asset cct-asset-image',
            'data-cct-fileid' => $fileid,
        ]);
    }
}
