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
 * Click-to-read-from-position narration for learner-facing content.
 *
 * The default engine is the browser's own Web Speech API: free, instant, and
 * with no backend involved at all. The optional high-quality engine renders the
 * same segments on the self-hosted GPU voice, which pronounces medical
 * terminology far better, and is only offered when an admin has configured it.
 *
 * Reading starts from where the learner clicked rather than from the top. That
 * needs every piece of prose to be individually addressable, so the content is
 * segmented into sentence-level spans once on load and the click target maps
 * back to a segment index.
 *
 * @module     local_contentchecker/read_aloud
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str', 'core/notification'], function(Ajax, Str, Notification) {

const fetchMany = Ajax.call;
const getString = Str.get_string;

/** @var {String} Marks a segment span. */
const SEG_ATTR = 'data-cct-seg';

/** @var {String} Class applied to the segment being spoken. */
const ACTIVE_CLASS = 'cct-speaking';

/** @var {Array} Selectors tried in order to find the readable content region. */
const CONTENT_SELECTORS = [
    '[role="main"] .box.generalbox',
    '#region-main .box.generalbox',
    '.activity-description',
    '#region-main [role="main"]',
    '#region-main'
];

/** @var {Array} Elements whose text must never be segmented. */
const SKIP_TAGS = ['SCRIPT', 'STYLE', 'BUTTON', 'SELECT', 'TEXTAREA', 'INPUT',
    'CODE', 'PRE', 'NAV', 'A'];

/**
 * Narration state for one page.
 */
class Reader {

    /**
     * Build a reader over a content root.
     *
     * @param {HTMLElement} root The content region.
     * @param {Object} config cmid and highquality flag.
     */
    constructor(root, config) {
        this.root = root;
        this.config = config;
        this.segments = [];
        this.index = 0;
        this.playing = false;
        this.paused = false;
        this.mode = 'standard';
        this.audio = null;
        this.token = 0;
    }

    /**
     * Wrap every sentence in the content region in an addressable span.
     *
     * Walks text nodes rather than rewriting innerHTML, so existing markup,
     * images, embeds and event handlers inside the content are left intact.
     *
     * @return {void}
     */
    segment() {
        const walker = document.createTreeWalker(this.root, NodeFilter.SHOW_TEXT, {
            acceptNode: (node) => {
                if (!node.nodeValue || !node.nodeValue.trim()) {
                    return NodeFilter.FILTER_REJECT;
                }
                for (let el = node.parentElement; el && el !== this.root; el = el.parentElement) {
                    if (SKIP_TAGS.includes(el.tagName) || el.hasAttribute(SEG_ATTR)) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    if (el.closest('.cct-questions, .cct-readaloud-bar')) {
                        return NodeFilter.FILTER_REJECT;
                    }
                }
                return NodeFilter.FILTER_ACCEPT;
            }
        });

        const targets = [];
        let node = walker.nextNode();
        while (node) {
            targets.push(node);
            node = walker.nextNode();
        }

        targets.forEach((textNode) => this.wrapSentences(textNode));

        this.segments = Array.from(this.root.querySelectorAll('[' + SEG_ATTR + ']'));
        this.segments.forEach((span, i) => {
            span.setAttribute(SEG_ATTR, String(i));
            span.setAttribute('tabindex', '0');
        });
    }

    /**
     * Replace one text node with a span per sentence.
     *
     * @param {Text} textNode The node to split.
     * @return {void}
     */
    wrapSentences(textNode) {
        // Keeps the delimiter with the sentence it ends, so playback does not
        // drop the full stop and run two sentences together.
        const pieces = textNode.nodeValue.match(/[^.!?]+[.!?]+[\s]*|[^.!?]+$/g);
        if (!pieces || pieces.length === 0) {
            return;
        }

        const fragment = document.createDocumentFragment();
        pieces.forEach((piece) => {
            if (!piece.trim()) {
                fragment.appendChild(document.createTextNode(piece));
                return;
            }
            const span = document.createElement('span');
            span.setAttribute(SEG_ATTR, '0');
            span.className = 'cct-seg';
            span.textContent = piece;
            fragment.appendChild(span);
        });

        textNode.parentNode.replaceChild(fragment, textNode);
    }

    /**
     * The plain text of one segment.
     *
     * @param {Number} i Segment index.
     * @return {String} The text.
     */
    textAt(i) {
        return this.segments[i] ? this.segments[i].textContent.trim() : '';
    }

    /**
     * Highlight the segment being spoken and scroll it into view.
     *
     * @param {Number} i Segment index.
     * @return {void}
     */
    highlight(i) {
        this.segments.forEach((span) => span.classList.remove(ACTIVE_CLASS));
        const span = this.segments[i];
        if (!span) {
            return;
        }
        span.classList.add(ACTIVE_CLASS);
        const box = span.getBoundingClientRect();
        if (box.top < 0 || box.bottom > window.innerHeight) {
            span.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
    }

    /**
     * Start reading from a segment.
     *
     * @param {Number} start Segment index to start at.
     * @return {void}
     */
    play(start) {
        this.stop();
        this.index = Math.max(0, Math.min(start, this.segments.length - 1));
        this.playing = true;
        this.paused = false;
        this.token += 1;
        this.speak(this.token);
    }

    /**
     * Speak the current segment and queue the next.
     *
     * The token guards against a stale callback: stopping and restarting must
     * not leave the previous chain advancing the index underneath the new one.
     *
     * @param {Number} token The generation this call belongs to.
     * @return {void}
     */
    speak(token) {
        if (!this.playing || token !== this.token) {
            return;
        }
        if (this.index >= this.segments.length) {
            this.stop();
            return;
        }

        const text = this.textAt(this.index);
        if (!text) {
            this.index += 1;
            this.speak(token);
            return;
        }

        this.highlight(this.index);

        const next = () => {
            if (token !== this.token || !this.playing) {
                return;
            }
            this.index += 1;
            this.speak(token);
        };

        if (this.mode === 'highquality') {
            this.speakRemote(text, token, next);
        } else {
            this.speakBrowser(text, next);
        }
    }

    /**
     * Speak with the browser's own voice.
     *
     * @param {String} text The passage.
     * @param {Function} next Called when it finishes.
     * @return {void}
     */
    speakBrowser(text, next) {
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = document.documentElement.lang || 'en';
        utterance.onend = next;
        // A synthesis error must not leave the reader stuck on one sentence.
        utterance.onerror = next;
        window.speechSynthesis.speak(utterance);
    }

    /**
     * Speak with the self-hosted GPU voice.
     *
     * @param {String} text The passage.
     * @param {Number} token The generation this call belongs to.
     * @param {Function} next Called when it finishes.
     * @return {void}
     */
    speakRemote(text, token, next) {
        fetchMany([{
            methodname: 'local_contentchecker_synthesize',
            args: {cmid: this.config.cmid, text: text}
        }])[0].then((response) => {
            if (token !== this.token || !this.playing) {
                return null;
            }
            this.audio = new Audio('data:' + response.mimetype + ';base64,' + response.audio);
            this.audio.onended = next;
            this.audio.onerror = next;
            return this.audio.play();
        }).catch(() => {
            // Falling back keeps the learner reading rather than stranding
            // them when the GPU voice is unavailable mid-page.
            this.mode = 'standard';
            this.speakBrowser(text, next);
        });
    }

    /**
     * Pause or resume.
     *
     * @return {Boolean} True when now paused.
     */
    togglePause() {
        if (!this.playing) {
            return false;
        }
        this.paused = !this.paused;
        if (this.mode === 'highquality' && this.audio) {
            if (this.paused) {
                this.audio.pause();
            } else {
                this.audio.play();
            }
        } else if (this.paused) {
            window.speechSynthesis.pause();
        } else {
            window.speechSynthesis.resume();
        }
        return this.paused;
    }

    /**
     * Stop and clear the highlight.
     *
     * @return {void}
     */
    stop() {
        this.playing = false;
        this.paused = false;
        this.token += 1;
        window.speechSynthesis.cancel();
        if (this.audio) {
            this.audio.pause();
            this.audio = null;
        }
        this.segments.forEach((span) => span.classList.remove(ACTIVE_CLASS));
    }
}

/**
 * Build the control bar.
 *
 * @param {Reader} reader The reader.
 * @param {Object} strings Localised labels.
 * @return {HTMLElement} The bar.
 */
const buildControls = (reader, strings) => {
    const bar = document.createElement('div');
    bar.className = 'cct-readaloud-bar';
    bar.setAttribute('role', 'group');
    bar.setAttribute('aria-label', strings.readaloud);

    const button = (label, icon) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary';
        btn.innerHTML = '<i class="fa ' + icon + '" aria-hidden="true"></i> ';
        btn.appendChild(document.createTextNode(label));
        return btn;
    };

    const playBtn = button(strings.play, 'fa-play');
    const pauseBtn = button(strings.pause, 'fa-pause');
    const stopBtn = button(strings.stop, 'fa-stop');

    playBtn.addEventListener('click', () => reader.play(0));
    stopBtn.addEventListener('click', () => reader.stop());
    pauseBtn.addEventListener('click', () => {
        const paused = reader.togglePause();
        pauseBtn.lastChild.nodeValue = paused ? strings.resume : strings.pause;
    });

    bar.appendChild(playBtn);
    bar.appendChild(pauseBtn);
    bar.appendChild(stopBtn);

    // The voice selector only exists when a high-quality voice is configured.
    if (reader.config.highquality) {
        const select = document.createElement('select');
        select.className = 'custom-select custom-select-sm ml-2';
        select.setAttribute('aria-label', strings.voice);
        [['standard', strings.voicestandard], ['highquality', strings.voicehigh]]
            .forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                select.appendChild(option);
            });
        select.addEventListener('change', () => {
            reader.mode = select.value;
            if (reader.playing) {
                reader.play(reader.index);
            }
        });
        bar.appendChild(select);
    }

    const hint = document.createElement('span');
    hint.className = 'cct-readaloud-hint text-muted ml-2';
    hint.textContent = strings.hint;
    bar.appendChild(hint);

    return bar;
};

/**
 * Set up read-aloud on this page.
 *
 * @param {Object} config cmid and highquality flag.
 * @return {void}
 */
const init = (config) => {
    if (!('speechSynthesis' in window)) {
        // Without the Web Speech API there is no default engine, and offering
        // only the optional one would be a worse experience than staying quiet.
        return;
    }

    let root = null;
    for (const selector of CONTENT_SELECTORS) {
        root = document.querySelector(selector);
        if (root) {
            break;
        }
    }
    if (!root) {
        return;
    }

    const keys = [
        'readaloud', 'readaloud:play', 'readaloud:pause', 'readaloud:resume',
        'readaloud:stop', 'readaloud:voice', 'readaloud:voicestandard',
        'readaloud:voicehigh', 'readaloud:hint'
    ].map((key) => ({key: key, component: 'local_contentchecker'}));

    Promise.all(keys.map((k) => getString(k.key, k.component))).then((values) => {
        const strings = {
            readaloud: values[0],
            play: values[1],
            pause: values[2],
            resume: values[3],
            stop: values[4],
            voice: values[5],
            voicestandard: values[6],
            voicehigh: values[7],
            hint: values[8]
        };

        const reader = new Reader(root, config);
        reader.segment();
        if (!reader.segments.length) {
            return null;
        }

        root.parentNode.insertBefore(buildControls(reader, strings), root);

        // This is the click-to-read-from-position behaviour: the clicked
        // segment becomes the starting index rather than always starting at 0.
        root.addEventListener('click', (e) => {
            const span = e.target.closest('[' + SEG_ATTR + ']');
            if (!span || !root.contains(span)) {
                return;
            }
            reader.play(parseInt(span.getAttribute(SEG_ATTR), 10));
        });

        // Keyboard equivalent, so the feature is not mouse-only.
        root.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            const span = e.target.closest('[' + SEG_ATTR + ']');
            if (!span) {
                return;
            }
            e.preventDefault();
            reader.play(parseInt(span.getAttribute(SEG_ATTR), 10));
        });

        window.addEventListener('beforeunload', () => reader.stop());
        return null;
    }).catch(Notification.exception);
};

return {init: init};

});
