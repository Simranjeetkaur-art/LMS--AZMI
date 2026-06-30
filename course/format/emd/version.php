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
 * eMD weekly-module course format — one week = one section (AGENT_04).
 *
 * @package    format_emd
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'format_emd';
$plugin->version   = 2026061800;
$plugin->requires  = 2025100600;      // Moodle 5.1.
$plugin->supported = [501, 501];
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.3.0';
$plugin->dependencies = [
    'local_azmsi' => 2026061500, // Course custom fields (code/credits/faculty) read by the S5 header.
];
