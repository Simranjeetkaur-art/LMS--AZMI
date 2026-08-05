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
 * The editor's review screen for auto-generated follow-up questions.
 *
 * Generation, editing, approval and deletion all happen here. Nothing a model
 * produced is visible to a learner until the Approve button on this page has
 * been pressed by a person.
 *
 * @module     local_contentchecker/question_review
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str', 'core/notification'], function(Ajax, Str, Notification) {

const fetchMany = Ajax.call;
const getString = Str.get_string;

/**
 * Send a question operation.
 *
 * @param {HTMLElement} card The question card.
 * @param {String} op save|approve|reject|delete.
 * @return {Promise} Resolves with the new status.
 */
const send = (card, op) => {
    const id = parseInt(card.getAttribute('data-cct-question'), 10);
    const qtext = card.querySelector('[data-cct-qtext]');
    const explanation = card.querySelector('[data-cct-explanation]');

    return fetchMany([{
        methodname: 'local_contentchecker_save_question',
        args: {
            questionid: id,
            op: op,
            qtext: qtext ? qtext.value : '',
            options: '',
            answer: '',
            explanation: explanation ? explanation.value : ''
        }
    }])[0];
};

/**
 * Wire up the review screen.
 *
 * @param {Object} config The course id.
 * @return {void}
 */
const init = (config) => {
    Promise.all([
        getString('questions:confirmdelete', 'local_contentchecker'),
        getString('questions:delete', 'local_contentchecker'),
        getString('review:cancel', 'local_contentchecker'),
        getString('questions:generating', 'local_contentchecker'),
        getString('questions:generated', 'local_contentchecker')
    ]).then(([confirmDelete, deleteLabel, cancel, generating, generated]) => {

        document.querySelectorAll('[data-cct-question]').forEach((card) => {
            const status = card.querySelector('[data-cct-status]');

            const run = (op) => {
                send(card, op).then((result) => {
                    if (op === 'delete') {
                        card.remove();
                        return null;
                    }
                    if (status) {
                        status.textContent = result.status;
                    }
                    card.setAttribute('data-cct-state', result.status);
                    return null;
                }).catch(Notification.exception);
            };

            const bind = (attr, op) => {
                const button = card.querySelector('[data-cct-' + attr + ']');
                if (button) {
                    button.addEventListener('click', () => run(op));
                }
            };

            bind('save', 'save');
            bind('approve', 'approve');
            bind('reject', 'reject');

            const del = card.querySelector('[data-cct-delete]');
            if (del) {
                del.addEventListener('click', () => {
                    Notification.confirm(deleteLabel, confirmDelete, deleteLabel, cancel,
                        () => run('delete'));
                });
            }
        });

        document.querySelectorAll('[data-cct-generate]').forEach((button) => {
            button.addEventListener('click', () => {
                const cmid = parseInt(button.getAttribute('data-cct-generate'), 10);
                const replace = button.hasAttribute('data-cct-replace');
                const original = button.textContent;

                button.disabled = true;
                button.textContent = generating;

                fetchMany([{
                    methodname: 'local_contentchecker_generate_questions',
                    args: {cmid: cmid, replace: replace}
                }])[0].then((result) => {
                    Notification.addNotification({
                        message: generated.replace('{$a}', result.created),
                        type: result.created > 0 ? 'success' : 'warning'
                    });
                    // The new drafts need rendering, and a reload is the
                    // simplest honest way to show exactly what was stored.
                    window.location.reload();
                    return null;
                }).catch((error) => {
                    button.disabled = false;
                    button.textContent = original;
                    Notification.exception(error);
                });
            });
        });

        return config;
    }).catch(Notification.exception);
};

return {init: init};

});
