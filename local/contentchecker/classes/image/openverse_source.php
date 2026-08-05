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
require_once($CFG->libdir . '/filelib.php');

/**
 * Openverse: openly licensed images from across the web.
 *
 * Chosen as the default stock source because every result is CC-licensed or
 * public domain and the search API needs no commercial key, so the feature
 * works on a fresh install with nothing to sign up for. Unsplash and Pexels
 * would both require an account and carry licence terms that are more
 * restrictive for republished teaching material.
 *
 * An optional client key raises the rate limit; without one Openverse still
 * answers anonymously, which is why is_available() does not require it.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openverse_source implements image_source {

    /** @var string Default API base. */
    const DEFAULT_ENDPOINT = 'https://api.openverse.org/v1';

    /**
     * Results per page for an anonymous request.
     *
     * Openverse rejects anything above 20 without a client key, with
     * "page_size may not exceed 20 for anonymous requests". Measured against
     * the live API, not read from the docs.
     *
     * @var int
     */
    const PAGE_SIZE_ANON = 20;

    /** @var int Results per page once a client key is configured. */
    const PAGE_SIZE_KEYED = 48;

    /**
     * Stable identifier.
     *
     * @return string The source id.
     */
    public function get_id(): string {
        return 'openverse';
    }

    /**
     * Human-readable name.
     *
     * @return string Localised name.
     */
    public function get_name(): string {
        return get_string('image:source:openverse', 'local_contentchecker');
    }

    /**
     * Openverse answers anonymously, so this only checks it has not been
     * switched off and that an endpoint is set.
     *
     * @return bool True when usable.
     */
    public function is_available(): bool {
        if (!source_registry::setting_enabled('image_openverse_enabled')) {
            return false;
        }
        return $this->endpoint() !== '';
    }

    /**
     * The configured API base.
     *
     * @return string Base URL with no trailing slash.
     */
    protected function endpoint(): string {
        $endpoint = trim((string) get_config('local_contentchecker', 'image_openverse_endpoint'));
        return rtrim($endpoint !== '' ? $endpoint : self::DEFAULT_ENDPOINT, '/');
    }

    /**
     * Search Openverse.
     *
     * @param string $query Free-text search terms.
     * @param int $page One-based page number.
     * @param \context $context Unused; Openverse is not context sensitive.
     * @return image_result[] Results.
     */
    public function search(string $query, int $page, \context $context): array {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $key = trim((string) get_config('local_contentchecker', 'image_openverse_key'));

        $params = [
            'q' => $query,
            'page' => max(1, $page),
            'page_size' => $key !== '' ? self::PAGE_SIZE_KEYED : self::PAGE_SIZE_ANON,
            // Only formats a browser will actually render inline.
            'extension' => 'jpg,png,gif,webp',
        ];

        // Optional: restrict to licences that permit commercial use and
        // modification, for institutions that need that.
        if (get_config('local_contentchecker', 'image_openverse_commercial')) {
            $params['license_type'] = 'commercial,modification';
        }

        $curl = new \curl();
        $headers = ['Accept: application/json'];
        if ($key !== '') {
            $headers[] = 'Authorization: Bearer ' . $key;
        }
        $curl->setHeader($headers);
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 20,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_FOLLOWLOCATION' => true,
        ]);

        $body = $curl->get($this->endpoint() . '/images/', $params);
        if ($curl->get_errno() !== 0) {
            throw new \moodle_exception('error:imagesearch', 'local_contentchecker', '',
                $curl->error);
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded) || !isset($decoded['results'])) {
            throw new \moodle_exception('error:imagesearch', 'local_contentchecker', '',
                \core_text::substr((string) $body, 0, 200));
        }

        $results = [];
        foreach ($decoded['results'] as $item) {
            $url = (string) ($item['url'] ?? '');
            if ($url === '') {
                continue;
            }

            $license = (string) ($item['license'] ?? '');
            $version = (string) ($item['license_version'] ?? '');

            $results[] = new image_result(
                sourceid: $this->get_id(),
                // The full URL is the handle: fetch() needs it and it is
                // already validated as an image endpoint by the search.
                ref: $url,
                title: (string) ($item['title'] ?? ''),
                thumburl: (string) ($item['thumbnail'] ?? $url),
                fullurl: $url,
                author: (string) ($item['creator'] ?? ''),
                license: $version !== '' ? $license . '-' . $version : $license,
                licenseurl: (string) ($item['license_url'] ?? ''),
                attribution: (string) ($item['attribution'] ?? ''),
                landingurl: (string) ($item['foreign_landing_url'] ?? ''),
                width: (int) ($item['width'] ?? 0),
                height: (int) ($item['height'] ?? 0),
            );
        }

        return $results;
    }

    /**
     * Download the chosen image.
     *
     * @param string $ref The image URL.
     * @param \context $context Unused.
     * @return array {content, filename, mimetype}.
     */
    public function fetch(string $ref, \context $context): array {
        if (!preg_match('#^https?://#i', $ref)) {
            throw new \moodle_exception('error:imagefetch', 'local_contentchecker');
        }

        $maxbytes = max(1, (int) (get_config('local_contentchecker', 'image_maxsize') ?: 8))
            * 1024 * 1024;

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 45,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_MAXREDIRS' => 5,
        ]);
        $content = $curl->get($ref);

        if ($curl->get_errno() !== 0 || !is_string($content) || $content === '') {
            throw new \moodle_exception('error:imagefetch', 'local_contentchecker', '',
                $curl->error);
        }
        if (strlen($content) > $maxbytes) {
            throw new \moodle_exception('error:imagetoolarge', 'local_contentchecker');
        }

        // Trust the bytes, not the URL or the served content type: this is
        // remote data being written into course content.
        $info = @getimagesizefromstring($content);
        if ($info === false || empty($info['mime'])
                || strpos($info['mime'], 'image/') !== 0) {
            throw new \moodle_exception('error:imagenotimage', 'local_contentchecker');
        }

        return [
            'content' => $content,
            'filename' => $this->filename_for($ref, $info['mime']),
            'mimetype' => $info['mime'],
        ];
    }

    /**
     * A safe filename derived from the source URL.
     *
     * @param string $url The image URL.
     * @param string $mimetype The detected MIME type.
     * @return string Cleaned filename with a matching extension.
     */
    protected function filename_for(string $url, string $mimetype): string {
        $base = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_FILENAME);
        $base = clean_param($base, PARAM_FILE);
        if ($base === '') {
            $base = 'openverse-image';
        }

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        // Extension comes from the sniffed type, so a .jpg URL serving a PNG
        // still lands on disk with the right name.
        $ext = $extensions[$mimetype] ?? 'jpg';

        return \core_text::substr($base, 0, 80) . '.' . $ext;
    }
}
