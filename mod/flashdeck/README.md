# mod_flashdeck — Flashcard deck activity for Moodle

An activity module for interactive flashcard study built on **active
recall** (see a prompt → attempt the answer from memory → reveal →
self-grade) and **spaced repetition** (per-user, per-card scheduling so
hard cards return sooner). Built for the Executive MD Program but fully
content-agnostic: decks and cards are authored through the UI, and card
types are pluggable so the same engine serves medical terminology,
anatomy, genetics, or health-systems policy.

- Component: `mod_flashdeck` (name checked against the Moodle plugins
  directory 2026-07-08 — no collision; nearest neighbours are
  `mod_flashcard` and `mod_wooflash`).
- Requires: Moodle 4.4+ (developed and deployed against 5.1), PHP 8.1+.
- Licence: GPL v3 or later.

## Status — phased build

| Phase | Scope | Status |
| --- | --- | --- |
| 1 | Skeleton, schema, capabilities, Basic + Term-dissection card types, accessible flip UI, seed deck | **Done** |
| 2 | Scheduler interface, SM-2 + Leitner, AJAX study loop, four-button grading, resumable progress | **Done** |
| 3 | Image/hotspot, cloze, matching, ordering, compare/contrast, Q&A card types; File API media | Planned |
| 4 | Gradebook, completion rules, streaks/points/badges, study modes, teacher report | Planned |
| 5 | CSV/JSON/GIFT import-export, tags & duplication, backup/restore, Behat breadth, mobile support | Planned |

Backup/restore stays undeclared until Phase 5 implements it.

## The study loop (Phase 2)

`view.php` defaults to **Learn mode**: active recall with spaced
repetition. See the prompt → attempt the answer → reveal → self-grade
with **Again / Hard / Good / Easy**. Every button shows the interval it
would schedule ("1 min", "10 min", "1 day", …) so the spacing is
visible. `?mode=browse` keeps the Phase 1 sequential browser.

All scheduling is computed **server-side** — the client only ever sends
a grade. One internal class (`\mod_flashdeck\local\api`) builds the
queue and applies grades; it is shared by:

- the AJAX loop (`mod_flashdeck_get_next_due_card`,
  `mod_flashdeck_submit_review`, `mod_flashdeck_get_deck_progress`
  external functions — capability-checked, sesskey-validated, one round
  trip per review), and
- the no-JavaScript fallback: the grade bar is a real form posting to
  view.php (post/redirect/get), so the full spaced-repetition loop
  works without JS.

Queue priority per user: due learning steps → due reviews (most
overdue first) → new cards up to the deck's *new cards per day* limit →
learning steps due within a 20-minute learn-ahead window so sessions
can finish what they started. Progress is resumable by construction:
state lives in `flashdeck_review`, keyed by user and card.

### Schedulers

Selected per deck in the activity settings; both implement
`\mod_flashdeck\scheduler\scheduler` and are deterministic (no fuzz),
which keeps the interval maths unit-testable.

- **SM-2 (modified, Anki-style — default).** New cards pass 1 min /
  10 min learning steps, graduate at 1 day (Easy: 4 days). Reviews:
  Hard = interval × 1.2 and ease −0.15; Good = interval × ease;
  Easy = interval × ease × 1.3 and ease +0.15; Again lapses the card
  (ease −0.20, relearn 10 min, interval halves on graduation). Ease is
  floored at 1.30, successful intervals always grow by ≥1 day, and are
  capped at 365 days.
- **Leitner (5 boxes, simpler).** Fixed intervals 1 / 2 / 4 / 8 / 16
  days. Again → box 1 in 10 minutes, Hard repeats the box, Good moves
  up one, Easy moves up two.

## Install

1. Place this directory at `mod/flashdeck` inside the Moodle web root
   (in this deployment: symlinked from `/var/www/azmsi-plugins/mod/flashdeck`
   by `infra/deploy-symlinks.sh`).
2. Visit *Site administration → Notifications* (or run
   `php admin/cli/upgrade.php`) to install the schema.
3. Add a "Flashcard deck" activity to any course.

## Card types (Phase 1)

Card types live in `classes/cardtype/`, extend
`\mod_flashdeck\cardtype\card_type`, and are registered in
`\mod_flashdeck\cardtype\manager::TYPES`. A type owns its authoring
form fields, its content validation, and its two rendered faces —
nothing else. The study engine never inspects card internals.

### basic
Two-sided card. Content JSON:

```json
{"front": "<p>Prompt</p>", "frontformat": 1, "back": "<p>Answer</p>", "backformat": 1}
```

### termdissection
A medical term split into colour-coded word parts — prefix, root,
suffix, and the combining-vowel **link** — each captioned with its role
and meaning, plus the full definition. Content JSON:

```json
{
  "term": "cardiology",
  "definition": "The medical specialty devoted to the study of the heart.",
  "parts": [
    {"text": "cardi", "role": "root", "meaning": "heart"},
    {"text": "o", "role": "link", "meaning": "combining vowel"},
    {"text": "logy", "role": "suffix", "meaning": "study of"}
  ]
}
```

Valid roles: `prefix`, `root`, `link`, `suffix`. At least two parts.

## Seed deck

`sample/emd101-week1.json` bundles an EMD-101 Week 1 medical-terminology
deck (12 term-dissection + 4 basic cards). Teachers load it from
*Manage cards → Load sample deck (EMD-101 Week 1)*; it appends to the
deck and validates every card before inserting anything.

## Theming

All colours — including the four word-part role colours — are CSS
custom properties declared once on `.path-mod-flashdeck` in
`styles.css` (`--flashdeck-role-prefix`, `--flashdeck-role-root`,
`--flashdeck-role-link`, `--flashdeck-role-suffix`, plus surface/shell/
accent/serif variables). Override them in a theme to re-skin; no
component references a raw hex.

## Accessibility

- Works fully without JavaScript: cards render as a list with native
  `<details>` "Show answer" reveals.
- With JavaScript: one-card stage, animated flip, keyboard support
  (Space/Enter flip, arrow keys navigate), `aria-pressed` on the flip
  control, and a polite live region announcing which face is shown.
- `prefers-reduced-motion` turns the 3D flip into a crossfade.
- Default palette meets WCAG AA on the card surface.

## Data model

- `flashdeck` — one row per activity (name, intro, scheduler choice,
  new-cards-per-day).
- `flashdeck_cards` — cards; type-specific payload in a validated JSON
  `content` column, so new card types never alter the schema.
- `flashdeck_review` — per-user per-card spaced-repetition state
  (ease factor, interval days, due date, repetitions, lapses, state,
  last grade). Created now, driven by the Phase 2 scheduler; already
  covered by the Privacy API provider (export + delete) and cleaned up
  on card/instance deletion. The column is `intervaldays` because
  `interval` is a reserved SQL word.
- Media files (Phase 3) will use the Moodle File API/`pluginfile.php`
  directly rather than a shadow `flashdeck_media` table.

## Capabilities

`mod/flashdeck:addinstance`, `:view`, `:study`, `:managecards`,
`:viewreports` — default role mappings in `db/access.php`.

## Tests

- PHPUnit: `tests/lib_test.php` (instance lifecycle),
  `tests/cardtype_test.php` (registry + validation + rendering exports),
  `tests/seeder_test.php` (sample deck integrity),
  `tests/scheduler_test.php` (exact SM-2 and Leitner interval maths:
  learning steps, growth, lapses, ease floor, cap, previews),
  `tests/external_test.php` (AJAX loop: queue order, persistence,
  events, learn-ahead, new-per-day limit, capability checks).
- Behat: `tests/behat/flashdeck.feature` (student view, teacher
  authoring, sample-deck load, delete flow, capability separation, and
  the full no-JS spaced-repetition session through the grade buttons).

Run from a Moodle dev checkout, e.g.
`vendor/bin/phpunit --testsuite mod_flashdeck_testsuite`.

## Development notes

- `amd/build/study.min.js` is hand-built (no grunt on the deploy box);
  regenerate with `grunt amd` in a dev checkout when editing
  `amd/src/study.js`.
- Language strings live only in `lang/en/flashdeck.php`; templates and
  JS contain no user-facing literals (JS reads localized strings from
  data attributes rendered server-side).
