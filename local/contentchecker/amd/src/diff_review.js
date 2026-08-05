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
 * Approve, reject or edit-and-approve an AI suggestion.
 *
 * Nothing here applies anything on its own -- every action is a button a person
 * pressed, and the server records who pressed it. Edit-and-approve turns the
 * suggested text into an editable field first, so the reviewer can correct the
 * correction before it is written.
 *
 * @module     local_contentchecker/diff_review
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str', 'core/notification'], function(Ajax, Str, Notification) {

const fetchMany = Ajax.call;
const getString = Str.get_string;

/**
 * Reflect a completed decision in the card.
 *
 * @param {HTMLElement} card The suggestion card.
 * @param {Object} result The server response.
 * @return {void}
 */
const settle = (card, result) => {
    card.querySelectorAll('button, textarea').forEach((el) => {
        el.disabled = true;
    });

    const status = card.querySelector('[data-cct-decision]');
    if (status) {
        status.textContent = result.message;
        status.className = 'cct-decision alert '
            + (result.applied ? 'alert-success'
                : (result.decision === 'rejected' ? 'alert-secondary' : 'alert-warning'));
    }

    card.classList.add('cct-decided');
    card.setAttribute('data-cct-state', result.decision);
};

/**
 * Send a decision.
 *
 * @param {HTMLElement} card The suggestion card.
 * @param {String} action approve|reject|edit.
 * @param {String} edited Replacement text for edit.
 * @return {void}
 */
const decide = (card, action, edited) => {
    const id = parseInt(card.getAttribute('data-cct-suggestion'), 10);
    const notes = card.querySelector('[data-cct-notes]');

    card.querySelectorAll('button').forEach((btn) => {
        btn.disabled = true;
    });

    fetchMany([{
        methodname: 'local_contentchecker_decide_suggestion',
        args: {
            suggestionid: id,
            action: action,
            edited: edited || '',
            notes: notes ? notes.value : ''
        }
    }])[0].then((result) => {
        settle(card, result);
        return null;
    }).catch((error) => {
        card.querySelectorAll('button').forEach((btn) => {
            btn.disabled = false;
        });
        Notification.exception(error);
    });
};

/**
 * Wire up every suggestion card on the page.
 *
 * @return {void}
 */
const init = () => {
    Promise.all([
        getString('review:confirmapprove', 'local_contentchecker'),
        getString('review:saveapprove', 'local_contentchecker'),
        getString('review:cancel', 'local_contentchecker')
    ]).then(([confirmApprove, saveApprove, cancel]) => {

        document.querySelectorAll('[data-cct-suggestion]').forEach((card) => {

            const approve = card.querySelector('[data-cct-approve]');
            const reject = card.querySelector('[data-cct-reject]');
            const edit = card.querySelector('[data-cct-edit]');

            if (approve) {
                approve.addEventListener('click', () => {
                    // Approving writes to live medical content, so it asks.
                    Notification.confirm(
                        approve.textContent.trim(),
                        confirmApprove,
                        approve.textContent.trim(),
                        cancel,
                        () => decide(card, 'approve', '')
                    );
                });
            }

            if (reject) {
                reject.addEventListener('click', () => decide(card, 'reject', ''));
            }

            if (edit) {
                edit.addEventListener('click', () => {
                    const pane = card.querySelector('[data-cct-suggested]');
                    if (!pane || pane.querySelector('textarea')) {
                        return;
                    }

                    const area = document.createElement('textarea');
                    area.className = 'form-control cct-edit-area';
                    area.rows = 4;
                    area.value = pane.textContent.trim();
                    pane.textContent = '';
                    pane.appendChild(area);
                    area.focus();

                    const save = document.createElement('button');
                    save.type = 'button';
                    save.className = 'btn btn-sm btn-primary mt-2';
                    save.textContent = saveApprove;
                    save.addEventListener('click', () => {
                        const text = area.value.trim();
                        if (!text) {
                            return;
                        }
                        decide(card, 'edit', text);
                    });
                    pane.appendChild(save);

                    edit.disabled = true;
                });
            }
        });

        return null;
    }).catch(Notification.exception);
};

return {init: init};

});
