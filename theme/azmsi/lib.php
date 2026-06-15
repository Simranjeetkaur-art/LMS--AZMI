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

/**
 * AZMSI theme SCSS callbacks — build on top of Moove (AGENT_02 / AGENT_00a §5).
 *
 * @package    theme_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Main SCSS: Moove's full SCSS, then our component layer (post.scss).
 *
 * @param theme_config $theme The theme config object.
 * @return string Raw SCSS to be compiled.
 */
function theme_azmsi_get_main_scss_content($theme) {
    global $CFG;

    require_once($CFG->dirroot . '/theme/moove/lib.php');

    // Inherit Moove's compiled SCSS (Boost preset + Moove variables + Moove SCSS).
    $moove = theme_config::load('moove');
    $scss = theme_moove_get_main_scss_content($moove);

    // AZMSI "Bold" component layer (cards, sidebar, KPI blocks, etc.).
    $post = __DIR__ . '/scss/post.scss';
    if (is_readable($post)) {
        $scss .= "\n" . file_get_contents($post);
    }

    return $scss;
}

/**
 * Pre-SCSS: AZMSI design tokens injected before everything so they can
 * override Moove/Bootstrap variables (colours, fonts, radii). See 02_DESIGN_TOKENS.md.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_azmsi_get_pre_scss($theme) {
    $pre = __DIR__ . '/scss/pre.scss';
    return is_readable($pre) ? file_get_contents($pre) : '';
}

/**
 * Extra SCSS: admin-configurable raw SCSS plus any inherited from Moove.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_azmsi_get_extra_scss($theme) {
    global $CFG;
    require_once($CFG->dirroot . '/theme/moove/lib.php');

    $scss = '';
    if (function_exists('theme_moove_get_extra_scss')) {
        $moove = theme_config::load('moove');
        $scss .= theme_moove_get_extra_scss($moove);
    }
    // Append this theme's own admin-entered extra SCSS if a setting is added later.
    if (!empty($theme->settings->scss)) {
        $scss .= "\n" . $theme->settings->scss;
    }
    return $scss;
}
