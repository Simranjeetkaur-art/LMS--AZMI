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
 * Site-level settings for mod_flashdeck: the AI generation backend.
 *
 * Everything about the inference server is configured here — provider,
 * URL, auth, model, limits and the system prompt itself. An empty base
 * URL disables the feature everywhere.
 *
 * @package    mod_flashdeck
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('mod_flashdeck/aiheading',
        get_string('aisettingsheading', 'mod_flashdeck'),
        get_string('aisettingsheadingdesc', 'mod_flashdeck')));

    $settings->add(new admin_setting_configselect('mod_flashdeck/aiprovider',
        get_string('aiprovider', 'mod_flashdeck'),
        get_string('aiproviderdesc', 'mod_flashdeck'),
        'ollama', [
            'ollama' => get_string('aiproviderollama', 'mod_flashdeck'),
            'vllm' => get_string('aiprovidervllm', 'mod_flashdeck'),
        ]));

    $settings->add(new admin_setting_configtext('mod_flashdeck/aibaseurl',
        get_string('aibaseurl', 'mod_flashdeck'),
        get_string('aibaseurldesc', 'mod_flashdeck'),
        '', PARAM_URL));

    $settings->add(new admin_setting_configpasswordunmask('mod_flashdeck/aibearertoken',
        get_string('aibearertoken', 'mod_flashdeck'),
        get_string('aibearertokendesc', 'mod_flashdeck'),
        ''));

    $settings->add(new admin_setting_configtext('mod_flashdeck/aimodel',
        get_string('aimodel', 'mod_flashdeck'),
        get_string('aimodeldesc', 'mod_flashdeck'),
        '', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('mod_flashdeck/aitimeout',
        get_string('aitimeout', 'mod_flashdeck'),
        get_string('aitimeoutdesc', 'mod_flashdeck'),
        '120', PARAM_INT));

    $settings->add(new admin_setting_configtext('mod_flashdeck/aitemperature',
        get_string('aitemperature', 'mod_flashdeck'),
        get_string('aitemperaturedesc', 'mod_flashdeck'),
        '0.4', PARAM_FLOAT));

    $settings->add(new admin_setting_configtext('mod_flashdeck/aimaxcards',
        get_string('aimaxcards', 'mod_flashdeck'),
        get_string('aimaxcardsdesc', 'mod_flashdeck'),
        '20', PARAM_INT));

    $settings->add(new admin_setting_configtext('mod_flashdeck/aitag',
        get_string('aitag', 'mod_flashdeck'),
        get_string('aitagdesc', 'mod_flashdeck'),
        'ai-generated', PARAM_TAG));

    $settings->add(new admin_setting_configtextarea('mod_flashdeck/aisystemprompt',
        get_string('aisystemprompt', 'mod_flashdeck'),
        get_string('aisystempromptdesc', 'mod_flashdeck'),
        get_string('aisystempromptdefault', 'mod_flashdeck'), PARAM_RAW));
}
