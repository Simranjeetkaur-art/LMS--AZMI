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
 * The course content-checker dashboard.
 *
 * Starting a check returns immediately with a check id; progress is then polled
 * on the same endpoint whether the work ran inline (a single activity) or was
 * queued for cron (a whole week). The UI does not need to know which happened.
 *
 * @module     local_contentchecker/verification_dashboard
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str', 'core/notification'], function(Ajax, Str, Notification) {

const fetchMany = Ajax.call;
const getString = Str.get_string;

/** @var {Number} Milliseconds between progress polls. */
const POLL_INTERVAL = 3000;

/** @var {Number} Give up polling after this long, so a dead job stops spinning. */
const POLL_TIMEOUT = 45 * 60 * 1000;

/**
 * Poll a check until it finishes.
 *
 * @param {Number} checkid The check.
 * @param {Function} onProgress Called with each status response.
 * @return {Promise} Resolves with the final status.
 */
const poll = (checkid, onProgress) => {
    const started = Date.now();

    return new Promise((resolve, reject) => {
        const tick = () => {
            fetchMany([{
                methodname: 'local_contentchecker_get_check_status',
                args: {checkid: checkid}
            }])[0].then((status) => {
                onProgress(status);

                if (status.status === 'complete' || status.status === 'failed') {
                    resolve(status);
                    return null;
                }
                if (Date.now() - started > POLL_TIMEOUT) {
                    reject(new Error('timeout'));
                    return null;
                }
                window.setTimeout(tick, POLL_INTERVAL);
                return null;
            }).catch(reject);
        };
        tick();
    });
};

/**
 * Update a row's badge and progress display.
 *
 * @param {HTMLElement} row The section row.
 * @param {Object} status The status response.
 * @param {Object} strings Localised labels.
 * @return {void}
 */
const paint = (row, status, strings) => {
    const badge = row.querySelector('[data-cct-badge]');
    const progress = row.querySelector('[data-cct-progress]');

    if (progress) {
        progress.textContent = status.status === 'running'
            ? status.progress + '%'
            : '';
    }
    if (!badge) {
        return;
    }

    let label = strings.running;
    let cls = 'badge badge-secondary';

    if (status.status === 'failed') {
        label = strings.failed;
        cls = 'badge badge-danger';
    } else if (status.status === 'complete') {
        if (status.numflagged > 0) {
            label = strings.needsreview;
            cls = 'badge badge-warning';
        } else {
            label = strings.ok;
            cls = 'badge badge-success';
        }
    }

    badge.className = cls;
    badge.textContent = label;
};

/**
 * Wire up the dashboard.
 *
 * @param {Object} config courseid and whether background jobs are allowed.
 * @return {void}
 */
const init = (config) => {
    const keys = [
        'status:running', 'status:ok', 'status:needsreview', 'status:failed',
        'dashboard:queued', 'dashboard:jobqueued'
    ];

    Promise.all(keys.map((key) => getString(key, 'local_contentchecker')))
        .then((values) => {
            const strings = {
                running: values[0],
                ok: values[1],
                needsreview: values[2],
                failed: values[3],
                queued: values[4],
                jobqueued: values[5]
            };

            document.querySelectorAll('[data-cct-verify]').forEach((button) => {
                button.addEventListener('click', () => {
                    const row = button.closest('[data-cct-row]');
                    const sectionnum = parseInt(button.getAttribute('data-cct-section'), 10);
                    const cmid = parseInt(button.getAttribute('data-cct-cmid') || '0', 10);

                    button.disabled = true;
                    paint(row, {status: 'running', progress: 0}, strings);

                    fetchMany([{
                        methodname: 'local_contentchecker_run_check',
                        args: {
                            courseid: config.courseid,
                            sectionnum: isNaN(sectionnum) ? -1 : sectionnum,
                            cmid: cmid
                        }
                    }])[0].then((started) => {
                        return poll(started.checkid, (status) => paint(row, status, strings));
                    }).then((status) => {
                        button.disabled = false;
                        // Reloading is the honest way to show the new findings:
                        // the ledger link, counts and badges all change.
                        if (status.status === 'complete' && status.numflagged > 0) {
                            window.location.reload();
                        }
                        return null;
                    }).catch((error) => {
                        button.disabled = false;
                        paint(row, {status: 'failed'}, strings);
                        Notification.exception(error);
                    });
                });
            });

            const jobButton = document.querySelector('[data-cct-queue-job]');
            if (jobButton) {
                jobButton.addEventListener('click', () => {
                    jobButton.disabled = true;
                    fetchMany([{
                        methodname: 'local_contentchecker_queue_job',
                        args: {courseids: [config.courseid]}
                    }])[0].then((job) => {
                        Notification.addNotification({
                            message: strings.jobqueued.replace('{$a}', job.numchecks),
                            type: 'info'
                        });
                        jobButton.disabled = false;
                        return null;
                    }).catch((error) => {
                        jobButton.disabled = false;
                        Notification.exception(error);
                    });
                });
            }

            return null;
        }).catch(Notification.exception);
};

return {init: init};

});
