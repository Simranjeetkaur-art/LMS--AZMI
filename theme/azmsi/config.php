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
 * AZMSI theme config — inherits Moove (which inherits Boost).
 *
 * @package    theme_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$THEME->name = 'azmsi';

// Chain confirmed on server: moove -> boost.
$THEME->parents = ['moove', 'boost'];

// No static sheets — all styling flows through SCSS callbacks below.
$THEME->sheets = [];
$THEME->editor_sheets = [];

// Build the compiled CSS on top of Moove. See lib.php.
$THEME->scss = function ($theme) {
    return theme_azmsi_get_main_scss_content($theme);
};
$THEME->prescsscallback = 'theme_azmsi_get_pre_scss';
$THEME->extrascsscallback = 'theme_azmsi_get_extra_scss';

// Inherit Moove's layouts, overriding only the login layout for the branded
// split-screen sign-in page (AGENT_02a). The login page always uses the site
// theme's layout, so this renders when azmsi is the active site theme.
$THEME->layouts = [
    'login' => [
        'file' => 'login.php',
        'regions' => [],
    ],
];

$THEME->enable_dock = false;
$THEME->usefallback = true;
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->requiredblocks = '';
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;

// Moove uses this; keep so our overrides slot into the same regions.
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;
