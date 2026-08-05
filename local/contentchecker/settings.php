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
 * Admin settings and site-administration links for local_contentchecker.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $category = new admin_category('local_contentchecker_cat',
        get_string('pluginname', 'local_contentchecker'));
    $ADMIN->add('localplugins', $category);

    // ---------------------------------------------------------------------
    // Site-wide oversight and the registries editors depend on.
    // ---------------------------------------------------------------------

    $ADMIN->add('local_contentchecker_cat', new admin_externalpage(
        'local_contentchecker_overview',
        get_string('siteoverview', 'local_contentchecker'),
        new moodle_url('/local/contentchecker/admin.php'),
        'local/contentchecker:viewsitereports'
    ));

    $ADMIN->add('local_contentchecker_cat', new admin_externalpage(
        'local_contentchecker_sources',
        get_string('managesources', 'local_contentchecker'),
        new moodle_url('/local/contentchecker/sources.php'),
        'local/contentchecker:manageconfig'
    ));

    $ADMIN->add('local_contentchecker_cat', new admin_externalpage(
        'local_contentchecker_assets',
        get_string('manageassets', 'local_contentchecker'),
        new moodle_url('/local/contentchecker/assets.php'),
        'local/contentchecker:manageconfig'
    ));

    $ADMIN->add('local_contentchecker_cat', new admin_externalpage(
        'local_contentchecker_pronounce',
        get_string('managepronunciation', 'local_contentchecker'),
        new moodle_url('/local/contentchecker/pronunciation.php'),
        'local/contentchecker:manageconfig'
    ));

    // ---------------------------------------------------------------------
    // Settings.
    // ---------------------------------------------------------------------

    $settings = new admin_settingpage('local_contentchecker_settings',
        get_string('settings', 'local_contentchecker'));
    $ADMIN->add('local_contentchecker_cat', $settings);

    // --- AI backend -------------------------------------------------------

    $settings->add(new admin_setting_heading('local_contentchecker/serverheading',
        get_string('setting:serverheading', 'local_contentchecker'),
        get_string('setting:serverheading_desc', 'local_contentchecker')));

    $settings->add(new admin_setting_configtext('local_contentchecker/endpoint',
        get_string('setting:endpoint', 'local_contentchecker'),
        get_string('setting:endpoint_desc', 'local_contentchecker'),
        '', PARAM_URL));

    $settings->add(new admin_setting_configpasswordunmask('local_contentchecker/token',
        get_string('setting:token', 'local_contentchecker'),
        get_string('setting:token_desc', 'local_contentchecker'), ''));

    $settings->add(new admin_setting_configtext('local_contentchecker/useragent',
        get_string('setting:useragent', 'local_contentchecker'),
        get_string('setting:useragent_desc', 'local_contentchecker'),
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) ' .
        'Chrome/124.0 Safari/537.36', PARAM_RAW));

    // --- Models -----------------------------------------------------------

    $settings->add(new admin_setting_heading('local_contentchecker/modelheading',
        get_string('setting:modelheading', 'local_contentchecker'),
        get_string('setting:modelheading_desc', 'local_contentchecker')));

    $settings->add(new admin_setting_configtext('local_contentchecker/model_atomise',
        get_string('setting:model_atomise', 'local_contentchecker'),
        get_string('setting:model_atomise_desc', 'local_contentchecker'),
        'qwen3.5:latest', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_contentchecker/model_adjudicate',
        get_string('setting:model_adjudicate', 'local_contentchecker'),
        get_string('setting:model_adjudicate_desc', 'local_contentchecker'),
        'qwen3.5:35b', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_contentchecker/model_questions',
        get_string('setting:model_questions', 'local_contentchecker'),
        get_string('setting:model_questions_desc', 'local_contentchecker'),
        'qwen3.5:35b', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_contentchecker/model_embed',
        get_string('setting:model_embed', 'local_contentchecker'),
        get_string('setting:model_embed_desc', 'local_contentchecker'),
        'nomic-embed-text', PARAM_TEXT));

    // --- Request tuning ---------------------------------------------------

    $settings->add(new admin_setting_heading('local_contentchecker/tuningheading',
        get_string('setting:tuningheading', 'local_contentchecker'),
        get_string('setting:tuningheading_desc', 'local_contentchecker')));

    $settings->add(new admin_setting_configtext('local_contentchecker/numctx',
        get_string('setting:numctx', 'local_contentchecker'),
        get_string('setting:numctx_desc', 'local_contentchecker'), 8192, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_contentchecker/numpredict',
        get_string('setting:numpredict', 'local_contentchecker'),
        get_string('setting:numpredict_desc', 'local_contentchecker'), 600, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_contentchecker/numpredict_atomise',
        get_string('setting:numpredict_atomise', 'local_contentchecker'),
        get_string('setting:numpredict_atomise_desc', 'local_contentchecker'),
        3000, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_contentchecker/timeout',
        get_string('setting:timeout', 'local_contentchecker'),
        get_string('setting:timeout_desc', 'local_contentchecker'), 150, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_contentchecker/retries',
        get_string('setting:retries', 'local_contentchecker'),
        get_string('setting:retries_desc', 'local_contentchecker'), 2, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_contentchecker/keepalive',
        get_string('setting:keepalive', 'local_contentchecker'),
        get_string('setting:keepalive_desc', 'local_contentchecker'), '30m', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_contentchecker/maxconcurrent',
        get_string('setting:maxconcurrent', 'local_contentchecker'),
        get_string('setting:maxconcurrent_desc', 'local_contentchecker'), 2, PARAM_INT));

    // --- Retrieval --------------------------------------------------------

    $settings->add(new admin_setting_heading('local_contentchecker/retrievalheading',
        get_string('setting:retrievalheading', 'local_contentchecker'),
        get_string('setting:retrievalheading_desc', 'local_contentchecker')));

    $settings->add(new admin_setting_configselect('local_contentchecker/fetcher',
        get_string('setting:fetcher', 'local_contentchecker'),
        get_string('setting:fetcher_desc', 'local_contentchecker'),
        'corpus', [
            'corpus' => get_string('fetcher:corpus', 'local_contentchecker'),
            'modelrag' => get_string('fetcher:modelrag', 'local_contentchecker'),
        ]));

    $settings->add(new admin_setting_configtext('local_contentchecker/topk',
        get_string('setting:topk', 'local_contentchecker'),
        get_string('setting:topk_desc', 'local_contentchecker'), 3, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_contentchecker/minscore',
        get_string('setting:minscore', 'local_contentchecker'),
        get_string('setting:minscore_desc', 'local_contentchecker'), '0.55', PARAM_RAW));

    $settings->add(new admin_setting_configtext('local_contentchecker/dedupe',
        get_string('setting:dedupe', 'local_contentchecker'),
        get_string('setting:dedupe_desc', 'local_contentchecker'), '0.95', PARAM_RAW));

    // --- Enrichment -------------------------------------------------------

    $settings->add(new admin_setting_heading('local_contentchecker/enrichheading',
        get_string('setting:enrichheading', 'local_contentchecker'),
        get_string('setting:enrichheading_desc', 'local_contentchecker')));

    // Deliberately local paths, not CDN URLs. An asset request to a third party
    // leaks which page a learner is on, and the plugin's whole premise is that
    // content does not leave the environment.
    $settings->add(new admin_setting_configtext('local_contentchecker/modelviewer_script',
        get_string('setting:modelviewer_script', 'local_contentchecker'),
        get_string('setting:modelviewer_script_desc', 'local_contentchecker'),
        '', PARAM_RAW));

    $settings->add(new admin_setting_configtext('local_contentchecker/mermaid_script',
        get_string('setting:mermaid_script', 'local_contentchecker'),
        get_string('setting:mermaid_script_desc', 'local_contentchecker'),
        '', PARAM_RAW));

    // --- Image sources ----------------------------------------------------

    $settings->add(new admin_setting_heading('local_contentchecker/imageheading',
        get_string('setting:imageheading', 'local_contentchecker'),
        get_string('setting:imageheading_desc', 'local_contentchecker')));

    $settings->add(new admin_setting_configcheckbox('local_contentchecker/image_openverse_enabled',
        get_string('setting:image_openverse_enabled', 'local_contentchecker'),
        get_string('setting:image_openverse_enabled_desc', 'local_contentchecker'), 1));

    $settings->add(new admin_setting_configtext('local_contentchecker/image_openverse_endpoint',
        get_string('setting:image_openverse_endpoint', 'local_contentchecker'),
        get_string('setting:image_openverse_endpoint_desc', 'local_contentchecker'),
        \local_contentchecker\image\openverse_source::DEFAULT_ENDPOINT, PARAM_URL));

    // Optional: Openverse answers anonymously, a key only raises the rate limit.
    $settings->add(new admin_setting_configpasswordunmask('local_contentchecker/image_openverse_key',
        get_string('setting:image_openverse_key', 'local_contentchecker'),
        get_string('setting:image_openverse_key_desc', 'local_contentchecker'), ''));

    $settings->add(new admin_setting_configcheckbox('local_contentchecker/image_openverse_commercial',
        get_string('setting:image_openverse_commercial', 'local_contentchecker'),
        get_string('setting:image_openverse_commercial_desc', 'local_contentchecker'), 0));

    $settings->add(new admin_setting_configcheckbox('local_contentchecker/image_repository_enabled',
        get_string('setting:image_repository_enabled', 'local_contentchecker'),
        get_string('setting:image_repository_enabled_desc', 'local_contentchecker'), 1));

    $settings->add(new admin_setting_configtext('local_contentchecker/image_maxsize',
        get_string('setting:image_maxsize', 'local_contentchecker'),
        get_string('setting:image_maxsize_desc', 'local_contentchecker'), 8, PARAM_INT));

    // --- Read-aloud -------------------------------------------------------

    $settings->add(new admin_setting_heading('local_contentchecker/ttsheading',
        get_string('setting:ttsheading', 'local_contentchecker'),
        get_string('setting:ttsheading_desc', 'local_contentchecker')));

    $settings->add(new admin_setting_configcheckbox('local_contentchecker/readaloud_enabled',
        get_string('setting:readaloud_enabled', 'local_contentchecker'),
        get_string('setting:readaloud_enabled_desc', 'local_contentchecker'), 1));

    // Left empty on purpose. The "High-quality voice" option is hidden from
    // learners entirely while this is blank, rather than offered and broken.
    $settings->add(new admin_setting_configtext('local_contentchecker/tts_endpoint',
        get_string('setting:tts_endpoint', 'local_contentchecker'),
        get_string('setting:tts_endpoint_desc', 'local_contentchecker'), '', PARAM_URL));

    $settings->add(new admin_setting_configpasswordunmask('local_contentchecker/tts_token',
        get_string('setting:tts_token', 'local_contentchecker'),
        get_string('setting:tts_token_desc', 'local_contentchecker'), ''));

    $settings->add(new admin_setting_configtext('local_contentchecker/tts_voice',
        get_string('setting:tts_voice', 'local_contentchecker'),
        get_string('setting:tts_voice_desc', 'local_contentchecker'),
        'en_US-lessac-medium', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_contentchecker/tts_maxchars',
        get_string('setting:tts_maxchars', 'local_contentchecker'),
        get_string('setting:tts_maxchars_desc', 'local_contentchecker'), 1200, PARAM_INT));

    // --- Follow-up questions ---------------------------------------------

    $settings->add(new admin_setting_heading('local_contentchecker/questionsheading',
        get_string('setting:questionsheading', 'local_contentchecker'),
        get_string('setting:questionsheading_desc', 'local_contentchecker')));

    $settings->add(new admin_setting_configcheckbox('local_contentchecker/questions_enabled',
        get_string('setting:questions_enabled', 'local_contentchecker'),
        get_string('setting:questions_enabled_desc', 'local_contentchecker'), 1));

    $settings->add(new admin_setting_configtext('local_contentchecker/questions_per_block',
        get_string('setting:questions_per_block', 'local_contentchecker'),
        get_string('setting:questions_per_block_desc', 'local_contentchecker'), 4, PARAM_INT));

    $settings->add(new admin_setting_configcheckbox('local_contentchecker/questions_shorttext',
        get_string('setting:questions_shorttext', 'local_contentchecker'),
        get_string('setting:questions_shorttext_desc', 'local_contentchecker'), 0));

    // --- Third-party fallback --------------------------------------------

    $settings->add(new admin_setting_heading('local_contentchecker/fallbackheading',
        get_string('setting:fallbackheading', 'local_contentchecker'),
        get_string('setting:fallbackheading_desc', 'local_contentchecker')));

    // Off by default and must stay that way. Course content is unpublished
    // medical teaching material; enabling this sends it to a third party.
    $settings->add(new admin_setting_configcheckbox('local_contentchecker/allow_thirdparty',
        get_string('setting:allow_thirdparty', 'local_contentchecker'),
        get_string('setting:allow_thirdparty_desc', 'local_contentchecker'), 0));

    $settings->add(new admin_setting_configtext('local_contentchecker/thirdparty_endpoint',
        get_string('setting:thirdparty_endpoint', 'local_contentchecker'),
        get_string('setting:thirdparty_endpoint_desc', 'local_contentchecker'), '', PARAM_URL));

    $settings->add(new admin_setting_configpasswordunmask('local_contentchecker/thirdparty_key',
        get_string('setting:thirdparty_key', 'local_contentchecker'),
        get_string('setting:thirdparty_key_desc', 'local_contentchecker'), ''));

    $settings->add(new admin_setting_configtext('local_contentchecker/thirdparty_model',
        get_string('setting:thirdparty_model', 'local_contentchecker'),
        get_string('setting:thirdparty_model_desc', 'local_contentchecker'), '', PARAM_TEXT));
}
