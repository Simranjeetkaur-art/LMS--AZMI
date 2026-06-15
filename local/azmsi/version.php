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
 * AZMSI core plugin — Web Services, event observers, DB schema, tasks.
 *
 * Skeleton from the AZMSI handoff (AGENT_03) adapted to Moodle 5.1 + public/
 * layout per AGENT_00a. External functions use the core_external\* namespace.
 *
 * @package    local_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_azmsi';
$plugin->version   = 2026061600;
$plugin->requires  = 2025100600;      // Moodle 5.1.
$plugin->supported = [501, 501];
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.3.0';
