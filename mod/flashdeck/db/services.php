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
 * External function declarations for mod_flashdeck.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_flashdeck_get_next_due_card' => [
        'classname' => 'mod_flashdeck\external\get_next_due_card',
        'description' => 'Get the next due card (server-scheduled) for the current user in a deck.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/flashdeck:study',
    ],
    'mod_flashdeck_submit_review' => [
        'classname' => 'mod_flashdeck\external\submit_review',
        'description' => 'Submit a self-grade for a card; the server computes the new schedule and returns the next card.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/flashdeck:study',
    ],
    'mod_flashdeck_get_deck_progress' => [
        'classname' => 'mod_flashdeck\external\get_deck_progress',
        'description' => 'Get the current user\'s progress counts for a deck.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/flashdeck:study',
    ],
];
