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
| 2 | Scheduler interface, SM-2 + Leitner, AJAX study loop, four-button grading, resumable progress | Planned |
| 3 | Image/hotspot, cloze, matching, ordering, compare/contrast, Q&A card types; File API media | Planned |
| 4 | Gradebook, completion rules, streaks/points/badges, study modes, teacher report | Planned |
| 5 | CSV/JSON/GIFT import-export, tags & duplication, backup/restore, Behat breadth, mobile support | Planned |

Phase 1 intentionally does **not** yet show the four-button grade bar:
grades that silently persist nothing would be dishonest UI. The bar
lands with the real scheduler in Phase 2. Backup/restore is likewise
undeclared until Phase 5 implements it.

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
  `tests/seeder_test.php` (sample deck integrity).
- Behat: `tests/behat/flashdeck.feature` (student view, teacher
  authoring, sample-deck load, delete flow, capability separation).

Run from a Moodle dev checkout, e.g.
`vendor/bin/phpunit --testsuite mod_flashdeck_testsuite`.

## Development notes

- `amd/build/study.min.js` is hand-built (no grunt on the deploy box);
  regenerate with `grunt amd` in a dev checkout when editing
  `amd/src/study.js`.
- Language strings live only in `lang/en/flashdeck.php`; templates and
  JS contain no user-facing literals (JS reads localized strings from
  data attributes rendered server-side).
