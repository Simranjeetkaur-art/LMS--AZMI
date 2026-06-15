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

namespace theme_azmsi\hook;

/**
 * Output hook callbacks for theme_azmsi.
 *
 * @package    theme_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_callbacks {
    /**
     * Load the AZMSI web fonts (Archivo + Source Serif 4) in the page head.
     *
     * Loaded here rather than via an SCSS `@import url(...)` (which the bundled
     * scssphp tries to resolve as a local file and fails). Dev uses Google Fonts;
     * self-hosting is finalised in Agent 11. Only added when azmsi is the active
     * theme so it does not affect other themes.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function add_web_fonts(\core\hook\output\before_standard_head_html_generation $hook): void {
        global $PAGE;
        if (!isset($PAGE->theme->name) || $PAGE->theme->name !== 'azmsi') {
            return;
        }
        $hook->add_html(
            '<link rel="preconnect" href="https://fonts.googleapis.com">' .
            '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' .
            '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?' .
            'family=Archivo:wght@400;500;600;700;800;900&' .
            'family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;0,8..60,700;1,8..60,400&' .
            'display=swap">'
        );
    }
}
