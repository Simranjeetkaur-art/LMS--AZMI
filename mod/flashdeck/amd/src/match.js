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
 * The Match study mode: a timed pairing game.
 *
 * Click two tiles; a matching pair locks in, a mismatch flashes and
 * clears. The count-up timer stops when the board is done. Purely a
 * practice game — nothing is persisted server-side.
 *
 * @module     mod_flashdeck/match
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Format elapsed seconds as m:ss.
 *
 * @param {Number} seconds elapsed time
 * @returns {String}
 */
const formatTime = (seconds) => {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return m + ':' + String(s).padStart(2, '0');
};

/**
 * Initialise the match game inside the given container.
 *
 * @param {String} rootId id of the match page container element
 */
export const init = (rootId) => {
    const root = document.getElementById(rootId);
    if (!root) {
        return;
    }
    const tiles = Array.from(root.querySelectorAll('.flashdeck-tile'));
    if (!tiles.length) {
        return;
    }
    const timerEl = root.querySelector('[data-region="matchtimer"]');
    const countEl = root.querySelector('[data-region="matchcount"]');
    const doneEl = root.querySelector('[data-region="matchdone"]');
    const finalTimeEl = root.querySelector('[data-region="matchfinaltime"]');
    const announce = root.querySelector('[data-region="announce"]');
    const totalPairs = tiles.length / 2;

    let selected = null;
    let matched = 0;
    let seconds = 0;
    let started = false;
    let ticker = null;

    const startTimer = () => {
        if (started) {
            return;
        }
        started = true;
        ticker = window.setInterval(() => {
            seconds++;
            timerEl.textContent = formatTime(seconds);
        }, 1000);
    };

    const finish = () => {
        window.clearInterval(ticker);
        finalTimeEl.textContent = formatTime(seconds);
        doneEl.hidden = false;
        doneEl.focus();
    };

    tiles.forEach((tile) => {
        tile.addEventListener('click', () => {
            if (tile.disabled || tile === selected) {
                return;
            }
            startTimer();

            if (!selected) {
                selected = tile;
                tile.classList.add('flashdeck-tileselected');
                return;
            }

            const first = selected;
            selected = null;
            first.classList.remove('flashdeck-tileselected');

            if (first.dataset.pair === tile.dataset.pair) {
                [first, tile].forEach((t) => {
                    t.classList.add('flashdeck-tilematched');
                    t.disabled = true;
                });
                matched++;
                countEl.textContent = String(matched);
                if (announce) {
                    announce.textContent = matched + ' / ' + totalPairs;
                }
                if (matched === totalPairs) {
                    finish();
                }
            } else {
                [first, tile].forEach((t) => t.classList.add('flashdeck-tilewrong'));
                window.setTimeout(() => {
                    [first, tile].forEach((t) => t.classList.remove('flashdeck-tilewrong'));
                }, 500);
            }
        });
    });

    root.querySelector('[data-action="matchrestart"]').addEventListener('click', () => {
        window.location.reload();
    });
};
