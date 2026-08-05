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
 * Library hooks for local_contentchecker.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add the content checker to a course's secondary navigation.
 *
 * This is the course-context entry point from the spec: editors working on one
 * course reach the dashboard from inside that course. The site-wide oversight
 * view lives under Site administration and is registered in settings.php.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course.
 * @param context_course $context The course context.
 * @return void
 */
function local_contentchecker_extend_navigation_course(navigation_node $navigation,
        stdClass $course, context_course $context): void {
    if (!has_capability('local/contentchecker:view', $context)) {
        return;
    }

    $navigation->add(
        get_string('coursedashboard', 'local_contentchecker'),
        new moodle_url('/local/contentchecker/index.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'contentchecker',
        new pix_icon('i/checkpermissions', '')
    );
}

/**
 * Serve files from the plugin's file areas.
 *
 * Enrichment assets uploaded by an admin (glTF models, diagram images) live in
 * the system context and are readable by any logged-in user, because learners
 * have to be able to see the model embedded in the page they are reading.
 *
 * @param stdClass $course Course object.
 * @param stdClass $cm Course module object.
 * @param context $context The context.
 * @param string $filearea The file area.
 * @param array $args Path arguments.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional options.
 * @return bool False if the file was not found.
 */
function local_contentchecker_pluginfile($course, $cm, $context, $filearea, $args,
        $forcedownload, array $options = []): bool {
    require_login();

    if ($context->contextlevel !== CONTEXT_SYSTEM || $filearea !== 'asset') {
        return false;
    }

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_contentchecker', $filearea,
        $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
    return true;
}
