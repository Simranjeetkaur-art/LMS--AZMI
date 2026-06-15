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
 * AZMSI theme settings.
 *
 * @package    theme_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('themesettingazmsi', get_string('configtitle', 'theme_azmsi'));

    // General tab: brand identity.
    $page = new admin_settingpage('theme_azmsi_general', get_string('generalsettings', 'theme_azmsi'));

    // Logo upload (brand wordmark in the dark sidebar; served by theme_azmsi_pluginfile()).
    $setting = new admin_setting_configstoredfile(
        'theme_azmsi/logo',
        get_string('logo', 'theme_azmsi'),
        get_string('logo_desc', 'theme_azmsi'),
        'logo',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['.png', '.jpg', '.svg', '.webp']]
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Primary accent (gold) — overrides $az-gold token (02_DESIGN_TOKENS §A).
    $setting = new admin_setting_configcolourpicker(
        'theme_azmsi/brandaccent',
        get_string('brandaccent', 'theme_azmsi'),
        get_string('brandaccent_desc', 'theme_azmsi'),
        '#C9A13B'
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Faculty accent (teal) — overrides $az-teal-bright token (03_SCREEN_SPECS S10).
    $setting = new admin_setting_configcolourpicker(
        'theme_azmsi/facultyaccent',
        get_string('facultyaccent', 'theme_azmsi'),
        get_string('facultyaccent_desc', 'theme_azmsi'),
        '#5FCDBD'
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);

    // Advanced tab: raw SCSS.
    $page = new admin_settingpage('theme_azmsi_advanced', get_string('advancedsettings', 'theme_azmsi'));

    // Raw SCSS appended last (consumed by theme_azmsi_get_extra_scss()).
    $setting = new admin_setting_configtextarea(
        'theme_azmsi/scss',
        get_string('rawscss', 'theme_azmsi'),
        get_string('rawscss_desc', 'theme_azmsi'),
        '',
        PARAM_RAW
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
}
