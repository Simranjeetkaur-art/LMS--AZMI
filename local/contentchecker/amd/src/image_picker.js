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
 * Image search and insert.
 *
 * Results are a picker, never an auto-insert: searching shows candidates and
 * nothing reaches course content until an editor clicks one and confirms. The
 * attribution shown under each thumbnail is the same text that gets stored with
 * the image, so what the editor approves is what gets published.
 *
 * @module     local_contentchecker/image_picker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str', 'core/notification'], function(Ajax, Str, Notification) {

const fetchMany = Ajax.call;
const getString = Str.get_string;

/**
 * Read the currently selected insertion target.
 *
 * The form's target option value is "cmid:recordid".
 *
 * @return {Object|null} {cmid, recordid} or null when nothing is selected.
 */
const currentTarget = () => {
    const select = document.querySelector('#id_target');
    if (!select || !select.value) {
        return null;
    }
    const [cmid, recordid] = select.value.split(':').map((v) => parseInt(v, 10));
    if (!cmid) {
        return null;
    }
    return {cmid: cmid, recordid: recordid || 0};
};

/**
 * Render one result tile.
 *
 * @param {Object} item The search result.
 * @param {Object} strings Localised labels.
 * @param {Function} onPick Called with the item when chosen.
 * @return {HTMLElement} The tile.
 */
const tile = (item, strings, onPick) => {
    const cell = document.createElement('div');
    cell.className = 'cct-image-tile';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'cct-image-thumb';
    // The accessible name has to say what the image is, not just "insert".
    button.setAttribute('aria-label', strings.insertthis + ' ' + (item.title || ''));

    const img = document.createElement('img');
    img.src = item.thumburl || item.fullurl;
    img.alt = item.title || '';
    img.loading = 'lazy';
    button.appendChild(img);

    button.addEventListener('click', () => onPick(item, button));
    cell.appendChild(button);

    const caption = document.createElement('div');
    caption.className = 'cct-image-meta';
    caption.textContent = item.attribution || item.title || '';
    cell.appendChild(caption);

    if (item.landingurl) {
        const link = document.createElement('a');
        link.href = item.landingurl;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.className = 'cct-image-origin';
        link.textContent = strings.viewsource;
        cell.appendChild(link);
    }

    return cell;
};

/**
 * Wire up the picker.
 *
 * @param {Object} config courseid and the available source list.
 * @return {void}
 */
const init = (config) => {
    const root = document.querySelector('[data-cct-image-picker]');
    if (!root) {
        return;
    }

    const sourceSelect = root.querySelector('[data-cct-image-source]');
    const input = root.querySelector('[data-cct-image-query]');
    const button = root.querySelector('[data-cct-image-search]');
    const grid = root.querySelector('[data-cct-image-results]');
    const status = root.querySelector('[data-cct-image-status]');

    if (!sourceSelect || !input || !button || !grid || !status) {
        return;
    }

    const keys = [
        'image:searching', 'image:noresults', 'image:notarget', 'image:insertthis',
        'image:viewsource', 'image:inserting', 'image:confirminsert', 'image:insert',
        'review:cancel', 'image:resultcount'
    ];

    Promise.all(keys.map((key) => getString(key, 'local_contentchecker')))
        .then((values) => {
            const strings = {
                searching: values[0],
                noresults: values[1],
                notarget: values[2],
                insertthis: values[3],
                viewsource: values[4],
                inserting: values[5],
                confirminsert: values[6],
                insert: values[7],
                cancel: values[8],
                resultcount: values[9]
            };

            const say = (message, type) => {
                status.textContent = message;
                status.className = 'cct-image-status ' + (type ? 'alert alert-' + type : '');
            };

            const pick = (item, tileButton) => {
                const target = currentTarget();
                if (!target) {
                    say(strings.notarget, 'warning');
                    return;
                }

                // Inserting writes to live course content, so it asks first and
                // shows exactly what attribution will be stored.
                Notification.confirm(
                    strings.insert,
                    strings.confirminsert + '\n\n' + (item.attribution || item.title || ''),
                    strings.insert,
                    strings.cancel,
                    () => {
                        tileButton.disabled = true;
                        say(strings.inserting, 'info');

                        fetchMany([{
                            methodname: 'local_contentchecker_insert_image',
                            args: {
                                cmid: target.cmid,
                                recordid: target.recordid,
                                sourceid: item.sourceid,
                                ref: item.ref,
                                title: item.title || '',
                                author: item.author || '',
                                license: item.license || '',
                                licenseurl: item.licenseurl || '',
                                attribution: item.attribution || '',
                                landingurl: item.landingurl || ''
                            }
                        }])[0].then((result) => {
                            tileButton.disabled = false;
                            say(result.message, result.ok ? 'success' : 'danger');
                            return null;
                        }).catch((error) => {
                            tileButton.disabled = false;
                            say('', '');
                            Notification.exception(error);
                        });
                    }
                );
            };

            const search = () => {
                const query = input.value.trim();
                if (!query) {
                    return;
                }

                button.disabled = true;
                grid.innerHTML = '';
                say(strings.searching, 'info');

                fetchMany([{
                    methodname: 'local_contentchecker_search_images',
                    args: {
                        courseid: config.courseid,
                        sourceid: sourceSelect.value,
                        query: query,
                        page: 1
                    }
                }])[0].then((response) => {
                    button.disabled = false;

                    if (!response.results.length) {
                        say(strings.noresults, 'warning');
                        return null;
                    }

                    say(strings.resultcount.replace('{$a}', response.results.length), '');
                    response.results.forEach((item) => {
                        grid.appendChild(tile(item, strings, pick));
                    });
                    return null;
                }).catch((error) => {
                    button.disabled = false;
                    say('', '');
                    Notification.exception(error);
                });
            };

            button.addEventListener('click', search);
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    // The picker sits inside the enrichment form; Enter must
                    // search, not submit the form.
                    e.preventDefault();
                    search();
                }
            });

            return null;
        }).catch(Notification.exception);
};

return {init: init};

});
