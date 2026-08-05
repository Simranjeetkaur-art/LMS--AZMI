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
 * Live preview for the publish layout picker.
 *
 * The preview is rendered by the server from the same Mustache template that
 * will render the published week, so what the editor sees here is what gets
 * published -- not a mock-up that can drift from the real thing.
 *
 * @module     local_contentchecker/publish_preview
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification', 'core/templates'], function(Ajax, Notification, Templates) {

const fetchMany = Ajax.call;

/** @var {Number} Debounce so typing in a slot does not fire a render per key. */
const DEBOUNCE = 400;

/**
 * Collect the slot values currently in the form.
 *
 * @param {HTMLElement} form The picker form.
 * @return {Object} Slot assignments.
 */
const readConfig = (form) => {
    const config = {};

    form.querySelectorAll('[data-cct-slot]').forEach((field) => {
        config[field.getAttribute('data-cct-slot')] = field.value;
    });

    // Repeating slots (callouts, tabs) are collected as ordered pairs.
    ['callouts', 'tabs'].forEach((slot) => {
        const rows = form.querySelectorAll('[data-cct-repeat="' + slot + '"]');
        if (!rows.length) {
            return;
        }
        config[slot] = Array.from(rows).map((row) => ({
            title: (row.querySelector('[data-cct-field="title"]') || {}).value || '',
            body: (row.querySelector('[data-cct-field="body"]') || {}).value || ''
        })).filter((entry) => entry.title || entry.body);
    });

    return config;
};

/**
 * Render the preview.
 *
 * @param {Object} config Page config.
 * @param {HTMLElement} form The picker form.
 * @param {HTMLElement} target Where to put the preview.
 * @param {Boolean} save Whether to persist the choice.
 * @return {Promise} Resolves when rendered.
 */
const render = (config, form, target, save) => {
    const select = form.querySelector('[data-cct-template]');

    return fetchMany([{
        methodname: 'local_contentchecker_set_template',
        args: {
            courseid: config.courseid,
            sectionid: config.sectionid,
            cmid: config.cmid || 0,
            templatekey: select.value,
            config: JSON.stringify(readConfig(form)),
            previewonly: !save
        }
    }])[0].then((result) => {
        Templates.replaceNodeContents(target, result.html, '');
        return result;
    });
};

/**
 * Wire up the picker.
 *
 * @param {Object} config courseid, sectionid and optional cmid.
 * @return {void}
 */
const init = (config) => {
    const form = document.querySelector('[data-cct-publish-form]');
    const target = document.querySelector('[data-cct-preview]');
    if (!form || !target) {
        return;
    }

    let timer = null;
    const schedule = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            render(config, form, target, false).catch(Notification.exception);
        }, DEBOUNCE);
    };

    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);

    const save = form.querySelector('[data-cct-save]');
    if (save) {
        save.addEventListener('click', (e) => {
            e.preventDefault();
            save.disabled = true;
            render(config, form, target, true).then(() => {
                save.disabled = false;
                Notification.addNotification({
                    message: save.getAttribute('data-cct-saved-message'),
                    type: 'success'
                });
                return null;
            }).catch((error) => {
                save.disabled = false;
                Notification.exception(error);
            });
        });
    }

    // Show the stored layout straight away rather than an empty pane.
    render(config, form, target, false).catch(Notification.exception);
};

return {init: init};

});
