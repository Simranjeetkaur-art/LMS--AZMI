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
 * One image returned by a search.
 *
 * Deliberately a value object rather than an array: every provider must fill in
 * the same licensing fields, and a typed constructor makes it obvious when one
 * of them has been forgotten. Attribution is not optional metadata here -- for
 * CC-licensed material in teaching content that may be republished, it is the
 * condition of use.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class image_result {

    /**
     * Constructor.
     *
     * @param string $sourceid Which provider produced this.
     * @param string $ref Opaque provider-specific handle used to fetch it later.
     * @param string $title Human-readable title.
     * @param string $thumburl URL for the picker thumbnail.
     * @param string $fullurl URL of the full image.
     * @param string $author Creator name, or ''.
     * @param string $license Short licence code, e.g. 'cc-by-sa' or 'allrightsreserved'.
     * @param string $licenseurl Canonical licence URL, or ''.
     * @param string $attribution Ready-made attribution line, or ''.
     * @param string $landingurl Page the image came from, or ''.
     * @param int $width Pixel width, 0 if unknown.
     * @param int $height Pixel height, 0 if unknown.
     */
    public function __construct(
        /** @var string Which provider produced this. */
        public readonly string $sourceid,
        /** @var string Opaque provider-specific handle. */
        public readonly string $ref,
        /** @var string Human-readable title. */
        public readonly string $title,
        /** @var string URL for the picker thumbnail. */
        public readonly string $thumburl,
        /** @var string URL of the full image. */
        public readonly string $fullurl,
        /** @var string Creator name. */
        public readonly string $author = '',
        /** @var string Short licence code. */
        public readonly string $license = '',
        /** @var string Canonical licence URL. */
        public readonly string $licenseurl = '',
        /** @var string Ready-made attribution line. */
        public readonly string $attribution = '',
        /** @var string Page the image came from. */
        public readonly string $landingurl = '',
        /** @var int Pixel width. */
        public readonly int $width = 0,
        /** @var int Pixel height. */
        public readonly int $height = 0,
    ) {
    }

    /**
     * The attribution line to display, built from parts when none was supplied.
     *
     * @return string Attribution text, or '' when there is genuinely nothing to say.
     */
    public function attribution_line(): string {
        if (trim($this->attribution) !== '') {
            return $this->attribution;
        }

        $parts = [];
        if ($this->title !== '') {
            $parts[] = '"' . $this->title . '"';
        }
        if ($this->author !== '') {
            $parts[] = get_string('image:by', 'local_contentchecker', $this->author);
        }
        if ($this->license !== '') {
            $parts[] = get_string('image:licensedunder', 'local_contentchecker',
                strtoupper(str_replace('-', ' ', $this->license)));
        }

        return implode(' ', $parts);
    }

    /**
     * Does this licence restrict commercial use or adaptation?
     *
     * NonCommercial and NoDerivatives are the two that bite an institution
     * later: NC can forbid use in a course that is ever sold, ND forbids
     * adapting the image at all. Both are easy to miss in a licence code like
     * "by-nc-nd-2.0", so it is surfaced as a plain flag.
     *
     * @return bool True when the licence needs a second look.
     */
    public function is_restrictive(): bool {
        $parts = preg_split('/[-\s]+/', strtolower($this->license)) ?: [];
        return in_array('nc', $parts, true) || in_array('nd', $parts, true);
    }

    /**
     * Shape sent to the picker.
     *
     * @return array Plain array for the web service.
     */
    public function to_array(): array {
        return [
            'sourceid' => $this->sourceid,
            'ref' => $this->ref,
            'title' => $this->title,
            'thumburl' => $this->thumburl,
            'fullurl' => $this->fullurl,
            'author' => $this->author,
            'license' => $this->license,
            'licenseurl' => $this->licenseurl,
            'attribution' => $this->attribution_line(),
            'landingurl' => $this->landingurl,
            'width' => $this->width,
            'height' => $this->height,
            'restrictive' => $this->is_restrictive(),
        ];
    }
}
