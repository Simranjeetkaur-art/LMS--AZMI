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
 * Content-aware enrichment suggestions.
 *
 * Reads the activity first, then offers 3D models, diagrams and images that
 * match what it is actually teaching. Everything shown is a candidate: nothing
 * reaches the page until the editor picks one and confirms, and the placement
 * is chosen at the same time so an illustration lands beside the passage it
 * illustrates rather than at the bottom.
 *
 * @module     local_contentchecker/enrich_suggest
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str', 'core/notification'], function(Ajax, Str, Notification) {

const fetchMany = Ajax.call;
const getString = Str.get_string;

/**
 * Build the placement dropdown shared by every card in a concept.
 *
 * @param {Array} positions Placement options from the server.
 * @param {String} label Accessible label.
 * @return {HTMLElement} The select.
 */
const placementSelect = (positions, label) => {
    const select = document.createElement('select');
    select.className = 'custom-select custom-select-sm cct-suggest-pos';
    select.setAttribute('aria-label', label);
    positions.forEach((p) => {
        const opt = document.createElement('option');
        opt.value = p.key;
        opt.textContent = p.label;
        select.appendChild(opt);
    });
    return select;
};

/**
 * Render one concept and its candidates.
 *
 * @param {Object} concept The concept payload.
 * @param {Array} positions Placement options.
 * @param {Object} cfg Page config.
 * @param {Object} strings Localised labels.
 * @return {HTMLElement} The card.
 */
const renderConcept = (concept, positions, cfg, strings) => {
    const card = document.createElement('section');
    card.className = 'card mb-3 cct-suggest-concept';

    const header = document.createElement('div');
    header.className = 'card-header';
    header.innerHTML = '';

    const title = document.createElement('span');
    title.className = 'font-weight-bold mr-2';
    title.textContent = concept.concept;
    header.appendChild(title);

    const kind = document.createElement('span');
    kind.className = 'badge badge-info mr-2';
    kind.textContent = strings['media_' + concept.mediatype] || concept.mediatype;
    header.appendChild(kind);

    card.appendChild(header);

    const body = document.createElement('div');
    body.className = 'card-body';

    const why = document.createElement('p');
    why.className = 'text-muted';
    why.textContent = concept.reason;
    body.appendChild(why);

    // An image library indexes things, not ideas. When the precise phrase found
    // nothing and a looser one did, say so rather than presenting weak matches
    // as though they answered the concept.
    if (concept.broadened) {
        const loose = document.createElement('p');
        loose.className = 'alert alert-warning py-1 px-2 small';
        loose.textContent = strings.broadened.replace('{$a}', concept.searchterms);
        body.appendChild(loose);
    }

    const place = placementSelect(positions, strings.placement);
    const placeWrap = document.createElement('div');
    placeWrap.className = 'form-inline mb-2';
    const placeLabel = document.createElement('label');
    placeLabel.className = 'mr-2 small';
    placeLabel.textContent = strings.placement;
    placeWrap.appendChild(placeLabel);
    placeWrap.appendChild(place);
    body.appendChild(placeWrap);

    const say = (message, type) => {
        let status = card.querySelector('[data-cct-status]');
        if (!status) {
            status = document.createElement('div');
            status.setAttribute('data-cct-status', '1');
            status.setAttribute('role', 'status');
            status.setAttribute('aria-live', 'polite');
            body.appendChild(status);
        }
        status.textContent = message;
        status.className = 'mt-2 ' + (type ? 'alert alert-' + type : '');
    };

    // --- registered 3D models and diagrams --------------------------------
    if (concept.assets.length) {
        const h = document.createElement('h5');
        h.className = 'h6';
        h.textContent = strings.registered;
        body.appendChild(h);

        concept.assets.forEach((asset) => {
            const row = document.createElement('div');
            row.className = 'd-flex align-items-center justify-content-between '
                + 'border rounded p-2 mb-1';

            const meta = document.createElement('div');
            meta.innerHTML = '';
            const nm = document.createElement('div');
            nm.textContent = asset.name;
            meta.appendChild(nm);
            if (asset.licence) {
                const lic = document.createElement('small');
                lic.className = 'text-muted';
                lic.textContent = asset.licence;
                meta.appendChild(lic);
            }
            row.appendChild(meta);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-success';
            btn.textContent = strings.insert;
            btn.addEventListener('click', () => {
                btn.disabled = true;
                say(strings.inserting, 'info');
                fetchMany([{
                    methodname: 'local_contentchecker_insert_asset',
                    args: {cmid: cfg.cmid, assetid: asset.id, position: place.value}
                }])[0].then((r) => {
                    btn.disabled = false;
                    say(r.message, r.ok ? 'success' : 'danger');
                    return null;
                }).catch((e) => {
                    btn.disabled = false;
                    Notification.exception(e);
                });
            });
            row.appendChild(btn);
            body.appendChild(row);
        });
    }

    // --- a diagram drawn for this concept ---------------------------------
    // Shown as editable source, not a black box: the editor reads exactly what
    // will be drawn, and can correct it, before anything is stored.
    if (concept.diagram) {
        const h = document.createElement('h5');
        h.className = 'h6 mt-3';
        h.textContent = strings.diagram;
        body.appendChild(h);

        const src = document.createElement('textarea');
        src.className = 'form-control cct-diagram-source';
        src.rows = 7;
        src.value = concept.diagram;
        src.setAttribute('aria-label', strings.diagram);
        body.appendChild(src);

        const add = document.createElement('button');
        add.type = 'button';
        add.className = 'btn btn-sm btn-success mt-2';
        add.textContent = strings.insertdiagram;
        add.addEventListener('click', () => {
            add.disabled = true;
            say(strings.inserting, 'info');
            fetchMany([{
                methodname: 'local_contentchecker_insert_diagram',
                args: {
                    cmid: cfg.cmid, source: src.value,
                    title: concept.concept, position: place.value
                }
            }])[0].then((r) => {
                add.disabled = false;
                say(r.message, r.ok ? 'success' : 'danger');
                return null;
            }).catch((e) => {
                add.disabled = false;
                Notification.exception(e);
            });
        });
        body.appendChild(add);
    }

    // --- openly licensed images -------------------------------------------
    if (concept.images.length) {
        const h = document.createElement('h5');
        h.className = 'h6 mt-3';
        h.textContent = strings.images;
        body.appendChild(h);

        const grid = document.createElement('div');
        grid.className = 'cct-image-results';

        concept.images.forEach((img) => {
            const cell = document.createElement('div');
            cell.className = 'cct-image-tile';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cct-image-thumb';
            btn.setAttribute('aria-label', strings.insert + ' ' + (img.title || ''));

            const thumb = document.createElement('img');
            thumb.src = img.thumburl;
            thumb.alt = img.title || '';
            thumb.loading = 'lazy';
            btn.appendChild(thumb);

            btn.addEventListener('click', () => {
                btn.disabled = true;
                say(strings.inserting, 'info');
                fetchMany([{
                    methodname: 'local_contentchecker_insert_image',
                    args: {
                        cmid: cfg.cmid, recordid: 0,
                        sourceid: img.sourceid, ref: img.ref,
                        title: img.title || '', author: img.author || '',
                        license: img.license || '', licenseurl: img.licenseurl || '',
                        attribution: img.attribution || '',
                        landingurl: img.landingurl || ''
                    }
                }])[0].then((r) => {
                    btn.disabled = false;
                    say(r.message, r.ok ? 'success' : 'danger');
                    return null;
                }).catch((e) => {
                    btn.disabled = false;
                    Notification.exception(e);
                });
            });

            cell.appendChild(btn);

            // NC forbids commercial reuse and ND forbids adaptation. Both are
            // easy to miss inside a code like "by-nc-nd-2.0", and both matter
            // for material an institution may republish.
            if (img.restrictive) {
                const warn = document.createElement('span');
                warn.className = 'badge badge-warning d-block';
                warn.textContent = strings.restrictive;
                cell.appendChild(warn);
            }

            const cap = document.createElement('div');
            cap.className = 'cct-image-meta';
            cap.textContent = img.attribution || img.title || '';
            cell.appendChild(cap);

            grid.appendChild(cell);
        });
        body.appendChild(grid);
    }

    if (!concept.assets.length && !concept.images.length && !concept.diagram) {
        const none = document.createElement('p');
        none.className = 'text-muted mb-0';
        none.textContent = strings.nocandidates + ' (' + concept.searchterms + ')';
        body.appendChild(none);
    }

    card.appendChild(body);
    return card;
};

/**
 * Wire up the suggester.
 *
 * @param {Object} cfg cmid.
 * @return {void}
 */
const init = (cfg) => {
    const button = document.querySelector('[data-cct-suggest]');
    const target = document.querySelector('[data-cct-suggestions]');
    if (!button || !target) {
        return;
    }

    const keys = [
        'suggest:analysing', 'suggest:none', 'suggest:registered', 'suggest:images',
        'suggest:insert', 'suggest:inserting', 'suggest:placement',
        'suggest:nocandidates', 'suggest:found', 'suggest:broadened',
        'suggest:restrictive', 'suggest:diagram', 'suggest:insertdiagram',
        'suggest:media_model3d', 'suggest:media_diagram', 'suggest:media_image'
    ];

    Promise.all(keys.map((k) => getString(k, 'local_contentchecker'))).then((v) => {
        const strings = {
            analysing: v[0], none: v[1], registered: v[2], images: v[3],
            insert: v[4], inserting: v[5], placement: v[6],
            nocandidates: v[7], found: v[8],
            broadened: v[9], restrictive: v[10],
            diagram: v[11], insertdiagram: v[12],
            media_model3d: v[13], media_diagram: v[14], media_image: v[15]
        };

        button.addEventListener('click', () => {
            button.disabled = true;
            target.innerHTML = '';

            const busy = document.createElement('div');
            busy.className = 'alert alert-info';
            busy.setAttribute('role', 'status');
            busy.textContent = strings.analysing;
            target.appendChild(busy);

            fetchMany([{
                methodname: 'local_contentchecker_suggest_enrichment',
                args: {cmid: cfg.cmid}
            }])[0].then((res) => {
                button.disabled = false;
                target.innerHTML = '';

                if (!res.concepts.length) {
                    const none = document.createElement('div');
                    none.className = 'alert alert-warning';
                    none.textContent = strings.none;
                    target.appendChild(none);
                    return null;
                }

                const found = document.createElement('p');
                found.className = 'text-muted';
                found.textContent = strings.found.replace('{$a}', res.concepts.length);
                target.appendChild(found);

                res.concepts.forEach((c) => {
                    target.appendChild(renderConcept(c, res.positions, cfg, strings));
                });
                return null;
            }).catch((e) => {
                button.disabled = false;
                target.innerHTML = '';
                Notification.exception(e);
            });
        });

        return null;
    }).catch(Notification.exception);
};

return {init: init};

});
