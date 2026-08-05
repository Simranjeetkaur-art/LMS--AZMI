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
 * Admin settings for local_emdverify.
 *
 * @package    local_emdverify
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_emdverify',
        get_string('pluginname', 'local_emdverify'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading('local_emdverify/serverheading',
        get_string('setting:serverheading', 'local_emdverify'),
        get_string('setting:serverheading_desc', 'local_emdverify')));

    $settings->add(new admin_setting_configtext('local_emdverify/endpoint',
        get_string('setting:endpoint', 'local_emdverify'),
        get_string('setting:endpoint_desc', 'local_emdverify'),
        'https://ai-aws.unicornfortunes.com', PARAM_URL));

    $settings->add(new admin_setting_configpasswordunmask('local_emdverify/token',
        get_string('setting:token', 'local_emdverify'),
        get_string('setting:token_desc', 'local_emdverify'), ''));

    $settings->add(new admin_setting_configtext('local_emdverify/useragent',
        get_string('setting:useragent', 'local_emdverify'),
        get_string('setting:useragent_desc', 'local_emdverify'),
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) ' .
        'Chrome/124.0 Safari/537.36', PARAM_RAW));

    $settings->add(new admin_setting_heading('local_emdverify/modelheading',
        get_string('setting:modelheading', 'local_emdverify'),
        get_string('setting:modelheading_desc', 'local_emdverify')));

    $settings->add(new admin_setting_configtext('local_emdverify/model_atomise',
        get_string('setting:model_atomise', 'local_emdverify'),
        get_string('setting:model_atomise_desc', 'local_emdverify'),
        'qwen3.5:latest', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_emdverify/model_adjudicate',
        get_string('setting:model_adjudicate', 'local_emdverify'),
        get_string('setting:model_adjudicate_desc', 'local_emdverify'),
        'qwen3.5:35b', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_emdverify/model_embed',
        get_string('setting:model_embed', 'local_emdverify'),
        get_string('setting:model_embed_desc', 'local_emdverify'),
        'nomic-embed-text', PARAM_TEXT));

    $settings->add(new admin_setting_heading('local_emdverify/tuningheading',
        get_string('setting:tuningheading', 'local_emdverify'),
        get_string('setting:tuningheading_desc', 'local_emdverify')));

    $settings->add(new admin_setting_configtext('local_emdverify/numctx',
        get_string('setting:numctx', 'local_emdverify'),
        get_string('setting:numctx_desc', 'local_emdverify'), 8192, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_emdverify/numpredict',
        get_string('setting:numpredict', 'local_emdverify'),
        get_string('setting:numpredict_desc', 'local_emdverify'), 600, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_emdverify/timeout',
        get_string('setting:timeout', 'local_emdverify'),
        get_string('setting:timeout_desc', 'local_emdverify'), 150, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_emdverify/keepalive',
        get_string('setting:keepalive', 'local_emdverify'),
        get_string('setting:keepalive_desc', 'local_emdverify'), '30m', PARAM_TEXT));

    $settings->add(new admin_setting_heading('local_emdverify/retrievalheading',
        get_string('setting:retrievalheading', 'local_emdverify'),
        get_string('setting:retrievalheading_desc', 'local_emdverify')));

    $settings->add(new admin_setting_configtext('local_emdverify/topk',
        get_string('setting:topk', 'local_emdverify'),
        get_string('setting:topk_desc', 'local_emdverify'), 3, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_emdverify/minscore',
        get_string('setting:minscore', 'local_emdverify'),
        get_string('setting:minscore_desc', 'local_emdverify'), '0.55', PARAM_RAW));

    $settings->add(new admin_setting_configtext('local_emdverify/dedupe',
        get_string('setting:dedupe', 'local_emdverify'),
        get_string('setting:dedupe_desc', 'local_emdverify'), '0.95', PARAM_RAW));
}
