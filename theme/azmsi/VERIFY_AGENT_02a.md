# Agent 02a — LMS sign-in page verification runbook

Branded split-screen `/login/index.php` for `theme_azmsi`, wrapping Moodle's
**real** login form. No site config was changed and `upgrade.php` was not run.

## What this delivers
- **`config.php`**: overrides the `login` layout → `layout/login.php`.
- **`layout/login.php`**: builds the context (editable copy from settings, crest
  logo URL, utility links) and renders `theme_azmsi/login`.
- **`templates/login.mustache`**: split-screen — left brand panel (eyebrow,
  Source-Serif headline, italic subhead, 12/48/100% stat strip, crest medallion +
  tagline), right column wraps **`{{{ output.main_content }}}`** (the real form).
  Presentational role tabs are `aria-hidden` decoration only. **No demo block.**
- **`scss/_login.scss`**: brand-panel chrome + styles Moodle's real login inputs,
  the gold sign-in button, forgot link, SSO/IdP buttons and error region.
- **`settings.php`**: `logineyebrow` / `loginheadline` / `loginsubhead` so marketing
  edits the copy without code.

## Proven offline
| Check | Result |
|---|---|
| `php -l` (layout/settings/config) | clean |
| `login.mustache` section balance | balanced |
| SCSS compiles (pre + post incl `_login`) | OK — 217 `.az-*` classes, `az-login` present |
| `phpcs --standard=moodle` (theme) | 0 / 0 |

## Why it isn't visually verified yet (and the one step you control)
The **login page always uses the *site* theme's layout** (the visitor isn't logged
in, so per-user theme doesn't apply). The site theme is still **`moove`**, so the
azmsi sign-in page only renders once `azmsi` is the active **site** theme — a
site-wide change affecting all users that I'm leaving for your go-ahead.

**To verify (your call), on staging or with a maintenance window:**
1. Site admin → Appearance → Theme selector → set **AZMSI** site-wide, then
   `php admin/cli/purge_caches.php`. *(No version bump was made, so a cache purge
   is all that's needed.)*
2. Log out and open `/login/index.php`:
   - **AC**: split-screen renders; the form is Moodle's real form — submit blank →
     styled Moodle validation; wrong password → Moodle lockout/error; "Forgot?"
     → `/login/forgot_password.php`; remember-me works.
   - **SSO**: if an OAuth2/SAML2 auth plugin is enabled, its IdP button appears
     (from Moodle's `potentialidps`) and starts the real flow; if none, no button.
   - Resize < 900px → single column; tab through inputs (real labels, AA contrast).
   - No "Quick Access · Demo" block.

## Post-login routing (AC: redirect by role, not a static URL)
No core "after login" hook exists in 5.1, so use config (the spec's documented
alternative), not a hardcoded link:
- Set **Site admin → Appearance → Navigation → Default home page for users** (or
  `$CFG->defaulthomepage`). Route students to the **student dashboard** once
  **Agent 05** ships it; until then `HOMEPAGE_MY` (/my) is the sensible default.
- Faculty/managers land on their role home via the same setting / their own
  home-page preference. The prototype's "Sign In → Dashboard" reflects this real
  redirect, configured here — never a link in the markup.

## Notes
- I deliberately **did not bump the theme version**, to avoid putting the live
  site into a pending-upgrade state while you've asked me not to run `upgrade.php`.
  Enabling only needs a cache purge.
- The crest medallion uses the theme **logo** setting (Agent 02). Upload
  `azmsi-crest.png` there; the panel falls back to the text wordmark if unset.
