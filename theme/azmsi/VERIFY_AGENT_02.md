# Agent 02 — `theme_azmsi` verification runbook

What was built, what is already proven offline, and the exact steps to confirm
the runtime acceptance criteria. **Runtime steps mutate the live site (theme +
caches) and must NOT be run on production without your go-ahead + the safety
protocol** (maintenance mode + DB/dataroot backup, ideally on a staging clone).

---

## Already proven offline (no site changes)

| Check | Result |
|---|---|
| `php -l` on every theme PHP file | clean |
| SCSS compiles via Moodle's bundled scssphp (pre + post + 7 partials) | OK — ~18.5 KB CSS, 28 `--az-*` custom props, 110 `.az-*` classes |
| Mustache section balance (`switchportal`, overridden `drawers`) | balanced |
| Moodle code checker (`phpcs --standard=moodle`) | 0 errors / 0 warnings |
| **AC5 contrast** (computed) | body text `--az-text-2` **12.2:1** on `--az-bg`, **11.1:1** on `--az-surface` (≥4.5:1 ✓); all text tokens ≥5.9:1 |
| `azmsi → moove → boost` chain (config.php `parents`) | declared ✓ (confirm in UI per AC6) |

CI (`.github/workflows/ci.yml`) re-runs codechecker + mustache lint + grunt on push.

---

## What this agent delivers

- **Tokens → Bootstrap mapping** (`scss/pre.scss`): every 02_DESIGN_TOKENS §A token as
  `$az-*` SCSS vars **and** `:root --az-*` custom props, mapped onto Bootstrap
  vars (`$body-bg`, `$primary`, `$card-bg`, inputs, tables, dropdowns, radius 0…)
  so standard Moodle pages render Bold dark by inheritance.
- **Fonts**: Archivo + Source Serif 4 (Google Fonts in dev; self-host = Agent 11).
- **Component partials** (`_base _sidebar _topbar _cards _course _quiz _footer`):
  the recurring prototype patterns as token-driven utility classes
  (`.az-continue-banner`, `.az-kpi-strip`, `.az-pill--gold/teal/mute`,
  `.az-checklist`, `.az-timeline`, `.az-course-card`, gold "active" row…) for
  Agents 04/05 to bind real data into.
- **Chrome restyle** via SCSS on Moodle's existing **dynamic** markup: dark left
  drawer (240px), dark top bar + pill search + avatar, gold-accent footer, and
  **quiz exam chrome + question navigator coloured by real `.qnbutton` state**.
- **Theme settings** (`settings.php`): logo upload, primary accent (gold),
  faculty accent (teal) → fed into SCSS via `lib.php`.
- **Capability-gated Switch Portal** (`classes/output/switch_portal.php` +
  `core_renderer::azmsi_switch_portal()` + `templates/switchportal.mustache`),
  injected into the sidebar via a **purely additive** override of Moove's
  `drawers` template (one line: `{{{ output.azmsi_switch_portal }}}`). It shows
  **only** links the user's capabilities permit, and is guarded so it renders
  nothing (no error) if `local_azmsi` is not yet installed.

> **Scope note (no future conflict):** the prototype's *persistent* 240px left
> rail (Dashboard / My Courses / Grades…) maps to Moodle's **primary navigation**
> (top bar in Moove) + the **dashboard** surface, which is **Agent 05**. Agent 02
> deliberately does **not** rebuild Moove's whole layout to force that rail here —
> that would be the kind of wholesale Moove fork that breaks on a Moove upgrade.
> Agent 02 provides the theme system, chrome styling, tokens and the reusable
> Switch Portal component; Agent 05 composes them into the dashboard sidebar.

---

## Runtime verification (needs your go-ahead — staging first)

Pre-req (safe, already done): plugins symlinked — confirm with
`infra/verify-deploy.sh`.

### Step 0 — install on staging (mutating)
```bash
# staging clone, in maintenance mode, with a fresh DB + dataroot backup:
php public/admin/cli/maintenance.php --enable
php public/admin/cli/upgrade.php --non-interactive      # installs theme_azmsi (+ local_azmsi caps)
php public/admin/cli/purge_caches.php
php public/admin/cli/maintenance.php --disable
```

### Step 1 — AC6: theme chain
Site admin → Appearance → Themes. Confirm **AZMSI** lists parents
**moove → boost**. (Or `Site administration → Plugins → Themes → AZMSI`.)

### Step 2 — AC1: set the theme + dark render
Site admin → Appearance → Theme selector → set **AZMSI** site-wide →
`php public/admin/cli/purge_caches.php`. Load Dashboard, a course, a settings
page: background `#0B131B`, cards `#131D28`, gold primary buttons, square
corners, Archivo/Source-Serif type. No SCSS/grunt errors in
`Site admin → Reports → Performance` / browser console.

### Step 3 — AC2: chrome + dynamic nav
Top bar (search pill, avatar), left drawer (dark), footer (gold top rule) match
the Bold system. **Create a new course** → it appears in My Courses / course
index **with no code change** (nav is Moodle-generated).

### Step 4 — AC3: quiz exam chrome
Open any quiz attempt. Confirm dark chrome, the timer, and the right-side
**question navigator** whose buttons colour by state (answered = teal, current =
gold outline, flagged = gold inset) — all from the live quiz engine.

### Step 5 — AC4: Switch Portal capability gating
On a course page (left drawer visible):
- As a **student** → no Switch Portal links (or none they lack caps for).
- Grant a user `local/azmsi:viewfacultyportal` → "Faculty view" link appears.
- Grant `local/azmsi:viewadminconsole` → "Admin console" link appears.
- Remove the caps → links disappear. (Link *targets* `/local/azmsi/faculty.php`
  and `/local/azmsi/admin.php` are built by Agents 06/07; the **gating** is what
  AC4 verifies here.)

### Step 6 — AC5: contrast (already computed; spot-check in UI)
Run an axe/Lighthouse pass on the Dashboard; body text contrast ≥4.5:1 (measured
12.2:1 / 11.1:1 above).

---

## Maintenance note
`templates/theme_moove/drawers.mustache` is an **additive copy** of Moove 5.1.2's
`drawers.mustache` (identical except the one Switch Portal line). If Moove is
upgraded, re-sync this file from the new Moove `drawers.mustache` and re-add the
`{{{ output.azmsi_switch_portal }}}` line. This is the only Moove-coupled file.
