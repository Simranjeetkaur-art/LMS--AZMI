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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Images already uploaded to this Moodle site.
 *
 * Searching goes through Moodle's own repository subsystem rather than a
 * parallel index of the files table: the repository already knows how to search
 * server files, and file_browser already enforces who may see which file. Any
 * hand-rolled index would have to reimplement that access control and would
 * drift from it.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_source implements image_source {

    /** @var string Which repository plugin type to search. */
    const REPO_TYPE = 'local';

    /**
     * Stable identifier.
     *
     * @return string The source id.
     */
    public function get_id(): string {
        return 'repository';
    }

    /**
     * Human-readable name.
     *
     * @return string Localised name.
     */
    public function get_name(): string {
        return get_string('image:source:repository', 'local_contentchecker');
    }

    /**
     * Is a searchable repository instance enabled on this site?
     *
     * @return bool True when usable.
     */
    public function is_available(): bool {
        if (!source_registry::setting_enabled('image_repository_enabled')) {
            return false;
        }
        return $this->instance() !== null;
    }

    /**
     * The first visible instance of the searchable repository.
     *
     * @return \repository|null The repository, or null when none is enabled.
     */
    protected function instance(): ?\repository {
        $instances = \repository::get_instances(['type' => self::REPO_TYPE]);
        foreach ($instances as $instance) {
            if (!empty($instance->options['visible']) || $instance->is_visible()) {
                return $instance;
            }
        }
        return $instances ? reset($instances) : null;
    }

    /**
     * Search the site's files.
     *
     * @param string $query Free-text search terms.
     * @param int $page One-based page number.
     * @param \context $context Context the search is made from.
     * @return image_result[] Results.
     */
    public function search(string $query, int $page, \context $context): array {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $repo = $this->instance();
        if (!$repo) {
            return [];
        }

        $listing = $repo->search($query, max(1, $page));
        $nodes = is_array($listing) ? ($listing['list'] ?? []) : [];

        $results = [];
        foreach ($nodes as $node) {
            // Only nodes the repository resolved as images carry dimensions;
            // everything else is a document and cannot be embedded as one.
            if (empty($node['image_width']) || empty($node['source'])) {
                continue;
            }

            $results[] = new image_result(
                sourceid: $this->get_id(),
                ref: (string) $node['source'],
                title: (string) ($node['title'] ?? ''),
                thumburl: (string) ($node['realthumbnail'] ?? $node['thumbnail'] ?? ''),
                fullurl: (string) ($node['realthumbnail'] ?? ''),
                author: (string) ($node['author'] ?? ''),
                license: (string) ($node['license'] ?? ''),
                licenseurl: '',
                // Files already on the site carry whatever author and licence
                // the uploader set; there is no canonical attribution string to
                // reuse, so image_result builds one from the parts.
                attribution: '',
                landingurl: '',
                width: (int) $node['image_width'],
                height: (int) ($node['image_height'] ?? 0),
            );
        }

        return $results;
    }

    /**
     * Read the chosen file's bytes.
     *
     * @param string $ref Base64-encoded file params from the repository node.
     * @param \context $context Context the insert is made from.
     * @return array {content, filename, mimetype}.
     */
    public function fetch(string $ref, \context $context): array {
        $params = json_decode(base64_decode($ref), true);
        if (!is_array($params) || empty($params['contextid']) || empty($params['filename'])) {
            throw new \moodle_exception('error:imagefetch', 'local_contentchecker');
        }

        $filecontext = \context::instance_by_id((int) $params['contextid'], IGNORE_MISSING);
        if (!$filecontext) {
            throw new \moodle_exception('error:imagefetch', 'local_contentchecker');
        }

        // file_browser is what enforces whether the CURRENT user may see this
        // file. Reading the row straight out of file_storage would bypass that
        // and let an editor pull a file out of a course they cannot access.
        $browser = get_file_browser();
        $fileinfo = $browser->get_file_info(
            $filecontext,
            $params['component'] ?? null,
            $params['filearea'] ?? null,
            $params['itemid'] ?? null,
            $params['filepath'] ?? null,
            $params['filename'] ?? null
        );
        if (!$fileinfo || $fileinfo->is_directory()) {
            throw new \moodle_exception('error:imagenopermission', 'local_contentchecker');
        }

        $fs = get_file_storage();
        $file = $fs->get_file(
            (int) $params['contextid'],
            (string) $params['component'],
            (string) $params['filearea'],
            (int) $params['itemid'],
            (string) $params['filepath'],
            (string) $params['filename']
        );
        if (!$file || $file->is_directory()) {
            throw new \moodle_exception('error:imagefetch', 'local_contentchecker');
        }

        $mimetype = (string) $file->get_mimetype();
        if (strpos($mimetype, 'image/') !== 0) {
            throw new \moodle_exception('error:imagenotimage', 'local_contentchecker');
        }

        return [
            'content' => $file->get_content(),
            'filename' => $file->get_filename(),
            'mimetype' => $mimetype,
        ];
    }
}
