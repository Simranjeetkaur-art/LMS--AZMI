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
 * AZMSI theme — "Bold" (dark) LMS theme, child of Moove.
 *
 * Skeleton produced from the AZMSI handoff (AGENT_02) adapted to Moodle 5.1
 * + public/ layout per AGENT_00a. Implement SCSS tokens + layouts next.
 *
 * @package    theme_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'theme_azmsi';
$plugin->version   = 2026061801;
$plugin->requires  = 2025100600;      // Moodle 5.1 (verified against public/version.php $version=2025100605.00).
$plugin->supported = [501, 501];
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.2.2';
$plugin->dependencies = [
    'theme_moove' => 2025093001, // Parent theme — Moove 5.1.2.
];
