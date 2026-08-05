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
    // Deliberately NOT type="module". The vendored Mermaid build is UMD and
    // attaches window.mermaid; an ES module defines no global, so loading it
    // as a module leaves window.mermaid undefined and the diagram silently
    // stays as raw source.
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

    const found = Array.from(document.querySelectorAll('[data-cct-mermaid]'));
    if (!found.length || !config.mermaid) {
        return;
    }

    loadOnce(config.mermaid).then(() => {
        if (!window.mermaid || typeof window.mermaid.run !== 'function') {
            throw new Error('mermaid loaded but exposes no run(); is it an ESM build?');
        }

        // Mermaid replaces the node's innerHTML with an <svg>. A <pre> is the
        // wrong host for that -- it is styled for preformatted TEXT, so the
        // diagram either fails to lay out or stays looking like source. Swap
        // any <pre> for a <div>, which is what Mermaid documents as its target.
        // Existing content already stored as <pre> is normalised here rather
        // than needing a content migration.
        const targets = found.map((node) => {
            if (node.tagName !== 'PRE') {
                return node;
            }
            const div = document.createElement('div');
            div.className = node.className;
            div.setAttribute('data-cct-mermaid', '1');
            // textContent, so the stored HTML entities come back as characters.
            div.textContent = node.textContent;
            node.parentNode.replaceChild(div, node);
            return div;
        });

        targets.forEach((el) => el.classList.add('mermaid'));

        window.mermaid.initialize({
            startOnLoad: false,
            // Diagram source is model-generated and editor-edited, then stored
            // in course content. 'strict' keeps Mermaid from rendering raw HTML
            // or wiring click handlers out of it, so a diagram cannot become a
            // script-injection vector.
            securityLevel: 'strict'
        });

        return window.mermaid.run({nodes: targets});
    }).catch((error) => {
        // Deliberately NOT silent. The readable source stays on the page either
        // way, but swallowing this made a broken renderer indistinguishable
        // from a working fallback and cost a diagnosis cycle.
        if (window.console && window.console.warn) {
            window.console.warn('local_contentchecker: diagram rendering failed', error);
        }
    });
};

return {init: init};

});
