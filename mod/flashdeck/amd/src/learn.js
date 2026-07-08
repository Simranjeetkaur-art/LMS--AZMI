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
 * The spaced-repetition study session (Learn mode).
 *
 * Progressive enhancement over the no-JS grade form: the flip is
 * animated, grading goes through mod_flashdeck_submit_review over
 * AJAX (one round trip returns the next server-scheduled card), the
 * grade bar only appears after the reveal, and keys 1-4 grade the
 * card. All scheduling is computed server-side.
 *
 * @module     mod_flashdeck/learn
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const SELECTORS = {
    CARDAREA: '[data-region="cardarea"]',
    CARD: '[data-region="card"]',
    CARDINNER: '[data-region="cardinner"]',
    CONTROLS: '[data-region="controls"]',
    FLIP: '[data-action="flip"]',
    GRADEFORM: '[data-region="gradeform"]',
    GRADECARDID: '[data-region="gradecardid"]',
    GRADEBUTTONS: 'button[name="grade"]',
    DONEPANEL: '[data-region="donepanel"]',
    NEXTDUEROW: '[data-region="nextduerow"]',
    NEXTDUETEXT: '[data-region="nextduetext"]',
    ANNOUNCE: '[data-region="announce"]',
    HINT: '[data-region="hint"]',
};

const COUNTS = ['duenow', 'learning', 'newremaining'];
const PREVIEWS = ['again', 'hard', 'good', 'easy'];

/**
 * Initialise the learn session inside the given container.
 *
 * @param {String} rootId id of the learn page container element
 */
export const init = (rootId) => {
    const root = document.getElementById(rootId);
    if (!root) {
        return;
    }
    const cardArea = root.querySelector(SELECTORS.CARDAREA);
    if (!cardArea) {
        // Deck has no cards at all.
        return;
    }

    const card = root.querySelector(SELECTORS.CARD);
    const cardInner = root.querySelector(SELECTORS.CARDINNER);
    const controls = root.querySelector(SELECTORS.CONTROLS);
    const flipBtn = controls.querySelector(SELECTORS.FLIP);
    const gradeForm = root.querySelector(SELECTORS.GRADEFORM);
    const cardIdInput = gradeForm.querySelector(SELECTORS.GRADECARDID);
    const donePanel = root.querySelector(SELECTORS.DONEPANEL);
    const nextDueRow = root.querySelector(SELECTORS.NEXTDUEROW);
    const nextDueText = root.querySelector(SELECTORS.NEXTDUETEXT);
    const announce = root.querySelector(SELECTORS.ANNOUNCE);
    const hint = root.querySelector(SELECTORS.HINT);
    const strings = {
        answershown: root.dataset.strAnswershown,
        promptshown: root.dataset.strPromptshown,
    };

    let flipped = false;
    let busy = false;

    root.classList.add('flashdeck-js');
    controls.hidden = false;
    if (hint) {
        hint.hidden = false;
    }

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
     * Keep the back face's details permanently open; CSS does the showing.
     */
    const prepareCard = () => {
        const details = card.querySelector('details');
        if (details) {
            details.open = true;
            details.addEventListener('toggle', () => {
                details.open = true;
            });
        }
    };

    /**
     * Flip the card; the grade bar only appears once the answer shows.
     *
     * @param {Boolean} state true = answer side up
     */
    const setFlipped = (state) => {
        flipped = state;
        card.classList.toggle('flashdeck-flipped', state);
        flipBtn.setAttribute('aria-pressed', state ? 'true' : 'false');
        gradeForm.hidden = !state;
        say(state ? strings.answershown : strings.promptshown);
    };

    /**
     * Render a study payload: next card, previews, counts, or the done panel.
     *
     * @param {Object} data payload from the external functions
     */
    const applyPayload = (data) => {
        COUNTS.forEach((key) => {
            const el = root.querySelector(`[data-region="count-${key}"]`);
            if (el) {
                el.textContent = String(data.counts[key]);
            }
        });

        const ring = root.querySelector('[data-region="masteryring"]');
        if (ring) {
            ring.setAttribute('stroke-dasharray', data.mastery + ' 100');
        }
        ['masterytext', 'streak', 'points'].forEach((key) => {
            const el = root.querySelector(`[data-region="${key}"]`);
            if (el) {
                el.textContent = String(data[key === 'masterytext' ? 'mastery' : key]);
            }
        });

        if (data.done) {
            cardArea.hidden = true;
            donePanel.hidden = false;
            if (nextDueRow) {
                nextDueRow.hidden = !data.nextduelabel;
            }
            if (nextDueText) {
                nextDueText.textContent = data.nextduelabel || '';
            }
            donePanel.setAttribute('tabindex', '-1');
            donePanel.focus();
        } else {
            cardInner.innerHTML = data.cardhtml;
            cardIdInput.value = String(data.cardid);
            PREVIEWS.forEach((key) => {
                const el = root.querySelector(`[data-region="preview-${key}"]`);
                if (el) {
                    el.textContent = data.previews[key];
                }
            });
            prepareCard();
            setFlipped(false);
            flipBtn.focus();
        }
        busy = false;
    };

    /**
     * Send a self-grade; the server schedules and returns the next card.
     *
     * @param {Number} grade 0 again, 1 hard, 2 good, 3 easy
     */
    const submitGrade = (grade) => {
        if (busy || !flipped) {
            return;
        }
        busy = true;
        Ajax.call([{
            methodname: 'mod_flashdeck_submit_review',
            args: {cardid: parseInt(cardIdInput.value, 10), grade: grade},
        }])[0]
            .then(applyPayload)
            .catch((error) => {
                busy = false;
                Notification.exception(error);
            });
    };

    flipBtn.addEventListener('click', () => setFlipped(!flipped));
    card.addEventListener('click', (e) => {
        if (e.target.closest('a, button, summary, input, select, textarea, label, [data-region="hotspot"]')) {
            return;
        }
        setFlipped(!flipped);
    });

    gradeForm.addEventListener('submit', (e) => e.preventDefault());
    gradeForm.querySelectorAll(SELECTORS.GRADEBUTTONS).forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            submitGrade(parseInt(btn.value, 10));
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
            setFlipped(!flipped);
        } else if (flipped && ['1', '2', '3', '4'].includes(e.key)) {
            e.preventDefault();
            submitGrade(parseInt(e.key, 10) - 1);
        }
    });

    prepareCard();
    setFlipped(false);
};
