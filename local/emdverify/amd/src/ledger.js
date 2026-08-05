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
 * Ledger interactions: sign-off buttons and run progress polling.
 *
 * @module     local_emdverify/ledger
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

const SELECTORS = {
    LEDGER: '[data-region="emdverify-ledger"]',
    CLAIM: '[data-region="emdverify-claim"]',
    REVIEW: '[data-action="emdverify-review"]',
    STATE: '[data-region="emdverify-reviewstate"]',
    PROGRESS: '[data-region="emdverify-progress"]'
};

/** @type {number} How often to poll a running job, in milliseconds. */
const POLL_INTERVAL = 5000;

/**
 * Record a decision against a claim.
 *
 * @param {HTMLElement} button The clicked button.
 * @returns {Promise<void>}
 */
const saveReview = async(button) => {
    const card = button.closest(SELECTORS.CLAIM);
    const group = button.parentElement;
    const claimid = parseInt(card.dataset.claimid, 10);
    const decision = button.dataset.decision;

    group.querySelectorAll('button').forEach((b) => {
        b.disabled = true;
    });

    try {
        const result = await fetchMany([{
            methodname: 'local_emdverify_save_review',
            args: {claimid, decision, notes: ''}
        }])[0];

        group.querySelectorAll('button').forEach((b) => {
            b.classList.toggle('active', b === button);
        });
        const state = card.querySelector(SELECTORS.STATE);
        if (state) {
            state.textContent = result.message;
        }
    } catch (error) {
        Notification.exception(error);
    } finally {
        group.querySelectorAll('button').forEach((b) => {
            b.disabled = false;
        });
    }
};

/**
 * Poll a running job and reload once it finishes.
 *
 * A run is several hundred model calls against a GPU that serialises per
 * model, so the page must never block on it.
 *
 * @param {number} runid The run being watched.
 * @returns {void}
 */
const pollRun = (runid) => {
    const region = document.querySelector(SELECTORS.PROGRESS);
    if (!region) {
        return;
    }

    const tick = async() => {
        try {
            const status = await fetchMany([{
                methodname: 'local_emdverify_get_run_status',
                args: {runid}
            }])[0];

            if (status.status === 'complete' || status.status === 'failed') {
                window.location.reload();
                return;
            }

            const bar = region.querySelector('.progress-bar');
            if (bar) {
                bar.style.width = status.progress + '%';
                bar.setAttribute('aria-valuenow', status.progress);
            }
            const label = await getString('runrunning', 'local_emdverify', status.progress);
            const text = region.childNodes[0];
            if (text && text.nodeType === Node.TEXT_NODE) {
                text.nodeValue = label;
            }
            setTimeout(tick, POLL_INTERVAL);
        } catch (error) {
            // A transient failure should not kill the poller; try again.
            setTimeout(tick, POLL_INTERVAL * 2);
        }
    };

    setTimeout(tick, POLL_INTERVAL);
};

/**
 * Wire up the ledger page.
 *
 * @returns {void}
 */
export const init = () => {
    const ledger = document.querySelector(SELECTORS.LEDGER);
    if (!ledger) {
        return;
    }

    ledger.addEventListener('click', (e) => {
        const button = e.target.closest(SELECTORS.REVIEW);
        if (button) {
            e.preventDefault();
            saveReview(button);
        }
    });

    const runid = parseInt(ledger.dataset.runid, 10);
    if (runid) {
        pollRun(runid);
    }
};
