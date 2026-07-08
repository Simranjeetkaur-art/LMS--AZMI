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
 * Per-card self-check interactions: cloze answer checking, matching and
 * ordering marking, and image hotspot hit-testing.
 *
 * Everything is delegated from the study page root, so it keeps working
 * when Learn mode swaps card HTML in place. These checks are purely
 * client-side self-assessment aids — the four-button self-grade remains
 * the only input to the (server-side) scheduler.
 *
 * @module     mod_flashdeck/interactions
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Light normalisation mirroring the server side: trim, collapse
 * whitespace, lowercase unless case-sensitive.
 *
 * @param {String} value raw input
 * @param {Boolean} caseSensitive whether case matters
 * @returns {String}
 */
const normalise = (value, caseSensitive) => {
    const collapsed = value.trim().replace(/\s+/g, ' ');
    return caseSensitive ? collapsed : collapsed.toLowerCase();
};

/**
 * Mark a field as correct/incorrect for styling and screen readers.
 *
 * @param {HTMLElement} field the input or select
 * @param {Boolean} ok whether the answer is correct
 */
const mark = (field, ok) => {
    field.classList.toggle('flashdeck-fieldcorrect', ok);
    field.classList.toggle('flashdeck-fieldincorrect', !ok);
    field.setAttribute('aria-invalid', ok ? 'false' : 'true');
};

/**
 * Check every cloze blank and matching/ordering select in scope.
 *
 * @param {HTMLElement} scope the card container
 */
const runCheck = (scope) => {
    let total = 0;
    let correct = 0;

    scope.querySelectorAll('input[data-answers]').forEach((input) => {
        total++;
        const answers = JSON.parse(input.dataset.answers);
        const caseSensitive = input.dataset.casesensitive === '1';
        const given = normalise(input.value, caseSensitive);
        const ok = answers.some((answer) => normalise(answer, caseSensitive) === given);
        mark(input, ok);
        if (ok) {
            correct++;
        }
    });

    scope.querySelectorAll('select[data-answer]').forEach((select) => {
        total++;
        const ok = select.value !== '' && select.value === select.dataset.answer;
        mark(select, ok);
        if (ok) {
            correct++;
        }
    });

    const feedback = scope.querySelector('[data-region="checkfeedback"]');
    if (feedback && total) {
        feedback.textContent = correct + ' / ' + total;
    }
};

/**
 * Hit-test a hotspot click against the card's target region.
 *
 * @param {HTMLElement} container the hotspot image wrapper
 * @param {MouseEvent} e the click event
 */
const handleHotspot = (container, e) => {
    const rect = container.getBoundingClientRect();
    if (!rect.width || !rect.height) {
        return;
    }
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;
    const cx = parseFloat(container.dataset.cx);
    const cy = parseFloat(container.dataset.cy);
    const r = parseFloat(container.dataset.r);
    const hit = Math.pow((x - cx) / r, 2) + Math.pow((y - cy) / r, 2) <= 1;

    let dot = container.querySelector('.flashdeck-hotspotdot');
    if (!dot) {
        dot = document.createElement('span');
        dot.className = 'flashdeck-hotspotdot';
        dot.setAttribute('aria-hidden', 'true');
        container.appendChild(dot);
    }
    dot.style.left = x + '%';
    dot.style.top = y + '%';
    dot.classList.toggle('flashdeck-fieldcorrect', hit);
    dot.classList.toggle('flashdeck-fieldincorrect', !hit);

    const card = container.closest('[data-region="card"]') || container.parentElement;
    const feedback = card.querySelector('[data-region="hotspotfeedback"]');
    if (feedback) {
        feedback.textContent = hit ? container.dataset.strHit : container.dataset.strMiss;
    }
};

/**
 * Attach the delegated interaction handlers to a study page container.
 *
 * @param {String} rootId id of the study/learn page container element
 */
export const init = (rootId) => {
    const root = document.getElementById(rootId);
    if (!root || root.dataset.flashdeckInteractions) {
        return;
    }
    root.dataset.flashdeckInteractions = '1';

    root.addEventListener('click', (e) => {
        const check = e.target.closest('[data-action="checkanswers"]');
        if (check) {
            e.preventDefault();
            runCheck(check.closest('[data-region="card"]') || root);
            return;
        }
        const hotspot = e.target.closest('[data-region="hotspot"]');
        if (hotspot) {
            handleHotspot(hotspot, e);
        }
    });
};
