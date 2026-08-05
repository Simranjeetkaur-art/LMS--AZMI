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
 * External service definitions for local_contentchecker.
 *
 * Every function below declares its capability here AND re-checks it inside
 * execute() against the context the request actually names. The declaration
 * alone is not a guarantee: it is checked against the site context for token
 * access, which is not the course the caller is asking about.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_contentchecker_run_check' => [
        'classname' => 'local_contentchecker\external\run_check',
        'description' => 'Start a verification check on one activity or one week.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/contentchecker:manage',
    ],

    'local_contentchecker_get_check_status' => [
        'classname' => 'local_contentchecker\external\get_check_status',
        'description' => 'Poll a running check for progress and results.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/contentchecker:view',
    ],

    'local_contentchecker_decide_suggestion' => [
        'classname' => 'local_contentchecker\external\decide_suggestion',
        'description' => 'Approve, reject or edit-and-approve an AI suggestion.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/contentchecker:approve',
    ],

    'local_contentchecker_queue_job' => [
        'classname' => 'local_contentchecker\external\queue_job',
        'description' => 'Queue a background whole-course or multi-course verification.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/contentchecker:runbackground',
    ],

    'local_contentchecker_generate_questions' => [
        'classname' => 'local_contentchecker\external\generate_questions',
        'description' => 'Generate draft follow-up questions for an activity.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/contentchecker:manage',
    ],

    'local_contentchecker_save_question' => [
        'classname' => 'local_contentchecker\external\save_question',
        'description' => 'Edit, approve, reject or delete a follow-up question.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/contentchecker:manage',
    ],

    'local_contentchecker_set_template' => [
        'classname' => 'local_contentchecker\external\set_template',
        'description' => 'Choose the publish layout for a section or page.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/contentchecker:manage',
    ],

    'local_contentchecker_search_images' => [
        'classname' => 'local_contentchecker\external\search_images',
        'description' => 'Search a configured image source for insertable images.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/contentchecker:manage',
    ],

    'local_contentchecker_insert_image' => [
        'classname' => 'local_contentchecker\external\insert_image',
        'description' => 'Insert an editor-chosen image into an activity.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/contentchecker:manage',
    ],

    'local_contentchecker_suggest_enrichment' => [
        'classname' => 'local_contentchecker\external\suggest_enrichment',
        'description' => 'Analyse an activity and propose illustrations for it.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/contentchecker:manage',
    ],

    'local_contentchecker_insert_asset' => [
        'classname' => 'local_contentchecker\external\insert_asset',
        'description' => 'Insert a registered 3D model or diagram into an activity.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/contentchecker:manage',
    ],

    'local_contentchecker_insert_diagram' => [
        'classname' => 'local_contentchecker\external\insert_diagram',
        'description' => 'Insert a generated diagram into an activity.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/contentchecker:manage',
    ],

    'local_contentchecker_synthesize' => [
        'classname' => 'local_contentchecker\external\synthesize',
        'description' => 'Render a passage to speech using the self-hosted TTS voice.',
        'type' => 'read',
        'ajax' => true,
    ],
];
