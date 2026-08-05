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
 * Progressive enhancement for embedded enrichment assets.
 *
 * Stored content contains only plain HTML -- a <model-viewer> element, a
 * <pre> of diagram source, a table. This module upgrades those in the browser
 * where the supporting library is available, and leaves the readable fallback
 * in place where it is not. Nothing here is required for the content to be
 * usable, which is why the markup is authored to stand on its own.
 *
 * Both libraries are loaded from a path an admin configures locally; no CDN is
 * contacted, so content never leaks to a third party through an asset request.
 *
 * @module     local_contentchecker/enrichment
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

/**
 * Load a script once.
 *
 * @param {String} url Script URL.
 * @return {Promise} Resolves when loaded.
 */
const loadOnce = (url) => new Promise((resolve, reject) => {
    if (!url) {
        reject(new Error('not configured'));
        return;
    }
    if (document.querySelector('script[data-cct-lib="' + url + '"]')) {
        resolve();
        return;
    }
    const script = document.createElement('script');
    script.src = url;
    script.type = 'module';
    script.setAttribute('data-cct-lib', url);
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('load failed'));
    document.head.appendChild(script);
});

/**
 * Upgrade enrichment elements on the page.
 *
 * @param {Object} config modelviewer and mermaid script URLs.
 * @return {void}
 */
const init = (config) => {
    if (document.querySelector('[data-cct-model]') && config.modelviewer) {
        // Failure is silent by design: the poster image and download link in
        // the fallback are already showing, so an error banner would only add
        // noise to a page that still works.
        loadOnce(config.modelviewer).catch(() => null);
    }

    const diagrams = document.querySelectorAll('[data-cct-mermaid]');
    if (diagrams.length && config.mermaid) {
        loadOnce(config.mermaid).then(() => {
            if (window.mermaid && typeof window.mermaid.run === 'function') {
                diagrams.forEach((el) => el.classList.add('mermaid'));
                window.mermaid.initialize({startOnLoad: false});
                window.mermaid.run({nodes: Array.from(diagrams)});
            }
            return null;
        }).catch(() => null);
    }
};

return {init: init};

});
