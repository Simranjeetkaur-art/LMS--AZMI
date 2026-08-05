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
 * Ungraded follow-up questions embedded after each content block.
 *
 * Everything here happens in the browser and nothing is sent back. That is the
 * point: these are formative self-checks, so there is no attempt record, no
 * grade item and no gradebook write. A learner can get one wrong, see why, and
 * move on with nothing kept.
 *
 * @module     local_contentchecker/question_widget
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/str', 'core/notification'], function(Str, Notification) {

const getString = Str.get_string;

/**
 * Normalise heading text for comparison.
 *
 * @param {String} text Raw text.
 * @return {String} Comparable form.
 */
const norm = (text) => text.replace(/\s+/g, ' ').trim().toLowerCase();

/**
 * Find where a block's questions should be inserted.
 *
 * A block runs from its heading to the next heading of the same or higher
 * level, so the questions go immediately before that next heading -- which is
 * the end of the block the learner has just read.
 *
 * @param {HTMLElement} root Content region.
 * @param {String} title The block's heading text.
 * @return {Object|null} {parent, before} insertion point.
 */
const insertionPoint = (root, title) => {
    if (!title) {
        return {parent: root, before: null};
    }

    const headings = Array.from(root.querySelectorAll('h1, h2, h3, h4'));
    const heading = headings.find((h) => norm(h.textContent) === norm(title));
    if (!heading) {
        return null;
    }

    const level = parseInt(heading.tagName.substring(1), 10);
    let node = heading.nextElementSibling;
    while (node) {
        const match = node.tagName.match(/^H([1-4])$/);
        if (match && parseInt(match[1], 10) <= level) {
            return {parent: heading.parentNode, before: node};
        }
        node = node.nextElementSibling;
    }
    return {parent: heading.parentNode, before: null};
};

/**
 * Render one question and wire up its feedback.
 *
 * @param {Object} question The question.
 * @param {Object} strings Localised labels.
 * @param {Number} idx Index within its block, for unique input names.
 * @return {HTMLElement} The question element.
 */
const renderQuestion = (question, strings, idx) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'cct-question mb-3';

    const stem = document.createElement('p');
    stem.className = 'cct-question-text font-weight-bold';
    stem.textContent = question.qtext;
    wrapper.appendChild(stem);

    const name = 'cctq-' + question.id + '-' + idx;
    const multi = question.qtype === 'checkbox';
    const inputs = [];

    if (question.qtype === 'shorttext') {
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control';
        input.id = name;
        input.setAttribute('aria-label', question.qtext);
        wrapper.appendChild(input);
        inputs.push(input);
    } else {
        const group = document.createElement('div');
        group.setAttribute('role', multi ? 'group' : 'radiogroup');
        group.setAttribute('aria-label', question.qtext);

        question.options.forEach((option, i) => {
            const id = name + '-' + i;
            const row = document.createElement('div');
            row.className = 'form-check';

            const input = document.createElement('input');
            input.type = multi ? 'checkbox' : 'radio';
            input.className = 'form-check-input';
            input.name = name;
            input.id = id;
            input.value = String(i);

            const label = document.createElement('label');
            label.className = 'form-check-label';
            label.htmlFor = id;
            label.textContent = option;

            row.appendChild(input);
            row.appendChild(label);
            group.appendChild(row);
            inputs.push(input);
        });
        wrapper.appendChild(group);
    }

    const check = document.createElement('button');
    check.type = 'button';
    check.className = 'btn btn-sm btn-secondary mt-2';
    check.textContent = strings.check;
    wrapper.appendChild(check);

    const feedback = document.createElement('div');
    feedback.className = 'cct-feedback mt-2';
    feedback.setAttribute('role', 'status');
    feedback.setAttribute('aria-live', 'polite');
    wrapper.appendChild(feedback);

    check.addEventListener('click', () => {
        let correct;

        if (question.qtype === 'shorttext') {
            // Free text is never auto-marked: the expected answer is shown so
            // the learner can compare, because a string match on a clinical
            // answer would be wrong far more often than it was right.
            feedback.className = 'cct-feedback mt-2 alert alert-info';
            feedback.textContent = strings.expected + ' ' + question.explanation;
            return;
        }

        const chosen = inputs
            .map((input, i) => (input.checked ? i : -1))
            .filter((i) => i >= 0);

        if (!chosen.length) {
            feedback.className = 'cct-feedback mt-2 alert alert-warning';
            feedback.textContent = strings.choose;
            return;
        }

        const expected = question.answer.slice().sort();
        correct = chosen.length === expected.length
            && chosen.slice().sort().every((v, i) => v === expected[i]);

        feedback.className = 'cct-feedback mt-2 alert '
            + (correct ? 'alert-success' : 'alert-danger');
        feedback.textContent = (correct ? strings.correct : strings.incorrect)
            + ' ' + question.explanation;
    });

    return wrapper;
};

/**
 * Build the panel holding one block's questions.
 *
 * @param {Object} block The block payload.
 * @param {Object} strings Localised labels.
 * @return {HTMLElement} The panel.
 */
const renderBlock = (block, strings) => {
    const panel = document.createElement('section');
    panel.className = 'cct-questions card my-4';
    panel.setAttribute('data-cct-block', block.blockref);

    const header = document.createElement('div');
    header.className = 'card-header d-flex justify-content-between align-items-center';

    const title = document.createElement('h4');
    title.className = 'h6 mb-0';
    title.textContent = strings.heading;
    header.appendChild(title);

    // Said plainly and in the UI, not just in the docs: a learner should know
    // straight away that this does not count.
    const badge = document.createElement('span');
    badge.className = 'badge badge-info';
    badge.textContent = strings.ungraded;
    header.appendChild(badge);

    panel.appendChild(header);

    const body = document.createElement('div');
    body.className = 'card-body';
    block.questions.forEach((question, i) => {
        body.appendChild(renderQuestion(question, strings, i));
    });
    panel.appendChild(body);

    return panel;
};

/**
 * Attach follow-up questions to this page.
 *
 * @param {Object} config cmid and the per-block question payload.
 * @return {void}
 */
const init = (config) => {
    if (!config.blocks || !config.blocks.length) {
        return;
    }

    const root = document.querySelector('[role="main"] .box.generalbox')
        || document.querySelector('#region-main');
    if (!root) {
        return;
    }

    const keys = [
        'questions:heading', 'questions:ungraded', 'questions:check',
        'questions:correct', 'questions:incorrect', 'questions:choose',
        'questions:expected'
    ];

    Promise.all(keys.map((key) => getString(key, 'local_contentchecker')))
        .then((values) => {
            const strings = {
                heading: values[0],
                ungraded: values[1],
                check: values[2],
                correct: values[3],
                incorrect: values[4],
                choose: values[5],
                expected: values[6]
            };

            config.blocks.forEach((block) => {
                const point = insertionPoint(root, block.blocktitle);
                if (!point) {
                    // The heading this set belongs to is no longer on the page,
                    // so there is nowhere honest to put it. Dropping it beats
                    // attaching it to the wrong section.
                    return;
                }
                const panel = renderBlock(block, strings);
                point.parent.insertBefore(panel, point.before);
            });
            return null;
        }).catch(Notification.exception);
};

return {init: init};

});
