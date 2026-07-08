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
 * Moodle App support for mod_flashdeck.
 *
 * A basic handler: deck description, per-user progress counts and a
 * jump into the full browser study loop. A native in-app study loop is
 * a future enhancement.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'mod_flashdeck' => [
        'handlers' => [
            'flashdeckview' => [
                'displaydata' => [
                    'icon' => $CFG->wwwroot . '/mod/flashdeck/pix/monologo.svg',
                    'class' => '',
                ],
                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'mobile_course_view',
                'offlinefunctions' => [],
            ],
        ],
        'lang' => [
            ['pluginname', 'mod_flashdeck'],
            ['statduenow', 'mod_flashdeck'],
            ['statlearning', 'mod_flashdeck'],
            ['statnewleft', 'mod_flashdeck'],
            ['statpoints', 'mod_flashdeck'],
            ['statstreak', 'mod_flashdeck'],
            ['reportmastery', 'mod_flashdeck'],
            ['studymodelearn', 'mod_flashdeck'],
        ],
    ],
];
