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
 * Live auto-refresh for the AZMSI admin console.
 *
 * Polls local_azmsi_get_admin_console on an interval and swaps the data-widget
 * region in place with the freshly rendered markup from the cron/event-driven
 * rollup. Polling pauses while the tab is hidden and resumes (with an immediate
 * refresh) when it becomes visible again, so an idle tab costs nothing.
 *
 * @module     local_azmsi/admin_console_live
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/log'], function(Ajax, Log) {
    'use strict';

    var SELECTOR = '[data-region="azmsi-admin-live"]';
    var timer = null;
    var lastGenerated = 0;
    var inflight = false;

    /**
     * Fetch the latest rendered region and swap it in if it is newer.
     *
     * @return {void}
     */
    var refresh = function() {
        if (inflight || document.hidden) {
            return;
        }
        var region = document.querySelector(SELECTOR);
        if (!region) {
            return;
        }
        inflight = true;
        Ajax.call([{
            methodname: 'local_azmsi_get_admin_console',
            args: {}
        }])[0].then(function(response) {
            // Only repaint when the rollup is at least as fresh as what we show,
            // and only when the markup actually changed (avoids flicker).
            if (response && typeof response.html === 'string'
                    && response.generatedon >= lastGenerated) {
                lastGenerated = response.generatedon;
                if (region.innerHTML !== response.html) {
                    region.innerHTML = response.html;
                }
            }
            return response;
        }).catch(function(error) {
            Log.debug('local_azmsi/admin_console_live: refresh failed');
            Log.debug(error);
            return error;
        }).always(function() {
            inflight = false;
        });
    };

    return {
        /**
         * Start the poller.
         *
         * @param {Number} intervalSeconds seconds between refreshes (min 15)
         * @return {void}
         */
        init: function(intervalSeconds) {
            var seconds = parseInt(intervalSeconds, 10);
            if (isNaN(seconds) || seconds < 15) {
                seconds = 60;
            }
            var region = document.querySelector(SELECTOR);
            if (region) {
                lastGenerated = parseInt(region.getAttribute('data-generatedon'), 10) || 0;
            }
            if (timer) {
                window.clearInterval(timer);
            }
            timer = window.setInterval(refresh, seconds * 1000);
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    refresh();
                }
            });
        }
    };
});
