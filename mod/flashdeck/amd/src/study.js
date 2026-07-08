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
 * Accessible flip UI for the flashdeck study page.
 *
 * Progressive enhancement over the no-JS markup: the card list becomes
 * a one-card-at-a-time stage, the native details reveal becomes an
 * animated flip (a crossfade under prefers-reduced-motion, via CSS),
 * and keyboard navigation is enabled. No scheduling happens here; the
 * server owns all scheduling state.
 *
 * @module     mod_flashdeck/study
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    CARD: '[data-region="card"]',
    CONTROLS: '[data-region="controls"]',
    HINT: '[data-region="hint"]',
    ANNOUNCE: '[data-region="announce"]',
    CURRENT: '[data-region="current"]',
    FLIP: '[data-action="flip"]',
    PREV: '[data-action="prev"]',
    NEXT: '[data-action="next"]',
};

/**
 * Initialise the study UI inside the given container.
 *
 * @param {String} rootId id of the study page container element
 */
export const init = (rootId) => {
    const root = document.getElementById(rootId);
    if (!root) {
        return;
    }
    const cards = Array.from(root.querySelectorAll(SELECTORS.CARD));
    if (!cards.length) {
        return;
    }

    const controls = root.querySelector(SELECTORS.CONTROLS);
    const hint = root.querySelector(SELECTORS.HINT);
    const announce = root.querySelector(SELECTORS.ANNOUNCE);
    const currentEl = root.querySelector(SELECTORS.CURRENT);
    const flipBtn = controls.querySelector(SELECTORS.FLIP);
    const prevBtn = controls.querySelector(SELECTORS.PREV);
    const nextBtn = controls.querySelector(SELECTORS.NEXT);
    const strings = {
        answershown: root.dataset.strAnswershown,
        promptshown: root.dataset.strPromptshown,
    };

    let current = 0;

    root.classList.add('flashdeck-js');
    controls.hidden = false;
    if (hint) {
        hint.hidden = false;
    }

    // The flip replaces the native no-JS reveal: keep every back face's
    // details permanently open and let the CSS transform do the showing.
    cards.forEach((card) => {
        const details = card.querySelector('details');
        if (details) {
            details.open = true;
            details.addEventListener('toggle', () => {
                details.open = true;
            });
        }
    });

    /**
     * Update the polite live region for screen readers.
     *
     * @param {String} text message to announce
     */
    const say = (text) => {
        if (announce) {
            announce.textContent = text || '';
        }
    };

    /**
     * Show one card, prompt side up, and sync the controls.
     *
     * @param {Number} index card index to show
     */
    const show = (index) => {
        current = index;
        cards.forEach((card, i) => {
            card.classList.toggle('flashdeck-active', i === index);
            card.classList.remove('flashdeck-flipped');
        });
        flipBtn.setAttribute('aria-pressed', 'false');
        prevBtn.disabled = (index === 0);
        nextBtn.disabled = (index === cards.length - 1);
        if (currentEl) {
            currentEl.textContent = String(index + 1);
        }
    };

    /**
     * Flip the current card and announce which face is showing.
     */
    const flip = () => {
        const flipped = cards[current].classList.toggle('flashdeck-flipped');
        flipBtn.setAttribute('aria-pressed', flipped ? 'true' : 'false');
        say(flipped ? strings.answershown : strings.promptshown);
    };

    flipBtn.addEventListener('click', flip);
    prevBtn.addEventListener('click', () => {
        if (current > 0) {
            show(current - 1);
            say(strings.promptshown);
        }
    });
    nextBtn.addEventListener('click', () => {
        if (current < cards.length - 1) {
            show(current + 1);
            say(strings.promptshown);
        }
    });

    // Mouse convenience: clicking the card body flips it — but never
    // when the click lands on interactive content (links, buttons,
    // cloze inputs, matching selects, hotspot images).
    cards.forEach((card) => {
        card.addEventListener('click', (e) => {
            if (e.target.closest('a, button, summary, input, select, textarea, label, [data-region="hotspot"]')) {
                return;
            }
            flip();
        });
    });

    root.addEventListener('keydown', (e) => {
        if (e.target.closest('input, textarea, select')) {
            return;
        }
        if (e.key === ' ' || e.key === 'Enter') {
            if (e.target.closest('a, button, summary')) {
                return;
            }
            e.preventDefault();
            flip();
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            if (current < cards.length - 1) {
                show(current + 1);
            }
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            if (current > 0) {
                show(current - 1);
            }
        }
    });

    show(0);
};
