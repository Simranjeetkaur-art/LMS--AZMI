# Agent 02a — LMS sign-in page verification runbook

Branded split-screen `/login/index.php` for `theme_azmsi`, wrapping Moodle's
**real** login form. Matches `design/AZMSI LMS Home.dc.html` within the token system.

## Acceptance criteria → status
| AC | Status |
|---|---|
| Split-screen renders Moodle's **real** login form (CSRF, lockout, errors, remember-me) | ✅ wraps `output.main_content`; `logintoken`/username/password/`#loginbtn` present |
| SSO button only when an SSO auth plugin is enabled (real IdP, never faked) | ✅ relies on core `potentialidps` inside the form; no fake button |
| Forgot-password reaches the real flow | ✅ core "Forgotten your username or password?" link → `/login/forgot_password.php` |
| Successful login redirects **by role**, not a static URL | ⚙️ via `$CFG->defaulthomepage` (see Routing) — needs your go-ahead to set |
| Responsive single-column on mobile; AA contrast; keyboard nav; real labels | ✅ `<900px` collapses, brand panel hidden; tokens give ≥12:1; real `<label>`s |
| No demo / Quick-Access block in production | ✅ omitted |
| All colour/type via tokens; no inline styles; crest from design asset | ✅ `_login.scss` tokens; crest bundled at `pix/crest.png` |

## Fixes in this pass (why it looked broken before)
- **Specificity:** the layout wrapper is `<div id="page" class="az-login">` and the
  right column `<main id="region-main" class="az-login-main">`. Boost styles `#page`
  / `#region-main` **by ID**, which beat plain `.az-login{display:grid}` →
  the columns stacked and the dark column bg was overridden (the orange showed
  through). Selectors are now `#page.az-login` and `#page .az-login-main` (ID-level).
- **Crest:** bundled the real `azmsi-crest.png` as `pix/crest.png`; the medallion
  always shows it (`$OUTPUT->image_url('crest','theme_azmsi')`).
- **Moodle form chrome:** core's own "Log in to <site>" heading, guest-access and
  cookies blurbs are demoted to small muted labels so the card stays clean.

## Proven offline
`php -l` clean · `phpcs --standard=moodle` 0/0 · `login.mustache` balanced · SCSS
compiles (2.18 MB, `#page.az-login` present) via the real Moodle `core_scss` path.

## Post-login routing (AC #4) — config, not a hook
Moodle 5.1 core has **no** post-login / redirect hook (confirmed: only
`after_config`, `navigation`, `output`, `task` hook families exist), so the
supported, non-hardcoded mechanism is `$CFG->defaulthomepage` (AGENT_02a's stated
alternative). Recommended:
- **Site admin → Appearance → Navigation → Default home page for users = Dashboard**
  (`$CFG->defaulthomepage = HOMEPAGE_MY`). Everyone lands on `/my`, which the
  student **dashboard (Agent 05)** themes per role. Faculty/managers see their
  role-appropriate dashboard content there.
This is a **site-config change** → in the approval list below.

## How to verify on the live site
The fixes are committed but **not yet live** — they need a theme cache rebuild
(themerev bump), which is a production action on your go-ahead.

1. **(needs approval)** `php admin/cli/purge_caches.php` — rebuild theme CSS so the
   specificity fix + crest are served.
2. Open `/login/index.php` (log out / private window). Confirm: true split-screen
   (brand left, sign-in card right — **no orange, not stacked**), crest medallion,
   the real form (submit blank → styled Moodle validation; wrong password →
   Moodle lockout/error; remember-me).
3. **SSO:** enable an OAuth2/SAML2 auth plugin → its IdP button appears; none
   configured → no button.
4. Resize `<900px` → single column; tab through inputs (real labels, AA contrast).
5. **Routing:** **(needs approval)** set Default home page = Dashboard, then a test
   login lands on `/my`.

## Commands that need your approval (mutate live production)
- `php /var/www/moodle/admin/cli/purge_caches.php` — to serve the CSS/crest fix.
- Setting **Default home page for users = Dashboard** (site config) — for routing.
(Neither runs `upgrade.php`; no version bump was made.)
