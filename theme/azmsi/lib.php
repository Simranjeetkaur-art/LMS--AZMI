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
 * Theme-settings-derived overrides (brand/faculty accent) are prepended first so
 * they win over the `!default` accent tokens declared in pre.scss.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_azmsi_get_pre_scss($theme) {
    $scss = '';

    // Map admin settings -> SCSS variables. Each accent token is `!default` in
    // pre.scss, so a non-default assignment here takes precedence.
    $configurable = [
        // Setting key => SCSS variable name.
        'brandaccent'   => 'az-gold',
        'facultyaccent' => 'az-teal-bright',
    ];
    foreach ($configurable as $key => $var) {
        $value = isset($theme->settings->{$key}) ? $theme->settings->{$key} : null;
        if (!empty($value)) {
            $scss .= '$' . $var . ': ' . $value . ";\n";
        }
    }

    $pre = __DIR__ . '/scss/pre.scss';
    if (is_readable($pre)) {
        $scss .= file_get_contents($pre);
    }

    return $scss;
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

/**
 * Serve the theme's files (currently just the uploaded brand logo).
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param context $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether to force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file was not found, just send the file otherwise and do not return anything
 */
function theme_azmsi_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && $filearea === 'logo') {
        $theme = theme_config::load('azmsi');
        return $theme->setting_file_serve('logo', $args, $forcedownload, $options);
    }
    send_file_not_found();
}

/**
 * Resolve the configured brand logo URL, or null when none is uploaded.
 *
 * Used by the dark sidebar to show the brand mark; templates fall back to the
 * text wordmark when this returns null. Never hardcodes an image path.
 *
 * @return moodle_url|null
 */
function theme_azmsi_get_logo_url() {
    $theme = theme_config::load('azmsi');
    $url = $theme->setting_file_url('logo', 'logo');
    return $url ? new moodle_url($url) : null;
}
