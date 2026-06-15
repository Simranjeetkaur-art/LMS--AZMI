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
 * AZMSI branded split-screen login layout (AGENT_02a).
 *
 * Left = brand panel (editable copy from theme settings); right = the REAL
 * Moodle login form (rendered into output.main_content — CSRF, lockout, errors,
 * remember-me, configured IdP buttons all native).
 *
 * @package    theme_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$settings = $OUTPUT->page->theme->settings ?? new stdClass();

// Editable marketing copy (falls back to the prototype defaults).
$eyebrow  = !empty($settings->logineyebrow) ? $settings->logineyebrow : 'Executive Medical Doctorate · LMS';
$headline = !empty($settings->loginheadline) ? $settings->loginheadline : 'The campus for the future of medical science.';
$subhead  = !empty($settings->loginsubhead) ? $settings->loginsubhead :
    'Your courses, assessments, research, and live seminars — one online learning environment, built for working professionals.';

$bodyattributes = $OUTPUT->body_attributes(['pagelayout-login', 'theme-azmsi-login']);

$templatecontext = [
    'sitename'       => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), 'escape' => false]),
    'output'         => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'logineyebrow'   => $eyebrow,
    'loginheadline'  => $headline,
    'loginsubhead'   => $subhead,
    'logourl'        => theme_azmsi_get_logo_url(),
    'applyurl'       => 'https://azmsi.unicornfortunes.com',
    'wwwroot'        => $CFG->wwwroot,
    'sitehost'       => parse_url($CFG->wwwroot, PHP_URL_HOST),
    'year'           => userdate(time(), '%Y'),
];

echo $OUTPUT->render_from_template('theme_azmsi/login', $templatecontext);
