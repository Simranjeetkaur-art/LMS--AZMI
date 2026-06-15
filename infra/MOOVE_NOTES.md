# Moove 5.1.2 inventory (parent theme of `theme_azmsi`)

Recorded by Agent 00 from the **installed** theme at `public/theme/moove/` so that
Agent 02 (design tokens / SCSS) overrides Moove cleanly via the child theme and
never edits Moove directly.

- **Component / release:** `theme_moove` — `release = '5.1.2'`, `version = 2025093001`.
- **Source of truth inspected:** `public/theme/moove/config.php`, `public/theme/moove/lib.php`.
- **Theme chain:** `azmsi → moove → boost`. Moove declares `parents = ['boost']`;
  `theme_azmsi` declares `parents = ['moove', 'boost']` (confirmed in
  `theme/azmsi/config.php`).

> ⚠️ Do **not** edit any file under `public/theme/moove/`. Everything below is
> the surface `theme_azmsi` extends. Overrides go in `theme/azmsi/scss/*`,
> `theme/azmsi/lib.php`, and `theme/azmsi/templates/*`.

## config.php — what Moove sets

| Setting | Value | Note for `theme_azmsi` |
|---|---|---|
| `parents` | `['boost']` | We sit above this: `['moove','boost']`. |
| `sheets` | `[]` | No static CSS sheets — all CSS via SCSS callbacks. Mirror this (we set `sheets = []`). |
| `editor_sheets` | `[]` | — |
| `editor_scss` | `['editor']` | Moove ships `scss/editor.scss`. Inherited unless we override. |
| `usefallback` | `true` | Keep `true` in child. |
| `scss` | closure → `theme_moove_get_main_scss_content($theme)` | We replace with `theme_azmsi_get_main_scss_content` (see SCSS chain below). |
| `extrascsscallback` | `theme_moove_get_extra_scss` | We point at `theme_azmsi_get_extra_scss`. |
| `prescsscallback` | `theme_moove_get_pre_scss` | We point at `theme_azmsi_get_pre_scss`. |
| `precompiledcsscallback` | `theme_moove_get_precompiled_css` | Returns `theme/moove/style/moodle.css`. Used as a perf fallback. |
| `enable_dock` | `false` | — |
| `rendererfactory` | `theme_overridden_renderer_factory` | Required so our renderers override core/Moove. We set the same. |
| `requiredblocks` | `''` | — |
| `yuicssmodules` | `[]` | — |

### Layouts Moove overrides (config.php `$THEME->layouts`)

Moove only overrides **two** layouts; everything else falls through to **Boost**:

| Layout | `file` | Regions | Options |
|---|---|---|---|
| `base` | `drawers.php` | (none) | — |
| `frontpage` | `frontpage.php` | `side-pre` (default region) | `nonavbar => true` |

Implication for Agent 02: to restyle any layout **other than** `base`/`frontpage`
(e.g. `standard`, `course`, `mydashboard`, `login`, `admin`) you are overriding a
**Boost** layout, not a Moove one. `theme_azmsi/config.php` currently sets
`layouts = []` (inherits the parent chain) — add entries only when a layout’s
markup must change.

## lib.php — the SCSS callback chain (this is what Agent 02 hooks)

Functions defined by Moove (`public/theme/moove/lib.php`):

1. **`theme_moove_get_main_scss_content($theme)`** — builds the main SCSS string,
   concatenated in this order:
   ```
   moove/_variables.scss
   + <boost preset>           (default.scss | plain.scss, per `preset` setting; default.scss fallback)
   + moove/default.scss
   + <uploaded preset file>   (theme_moove 'preset' filearea, if any)
   + moove/_security.scss
   ```
   → To extend, `theme_azmsi_get_main_scss_content` should call this (or replicate
   the order) and **append** the azmsi layer last so our tokens win. Current
   child stub: `theme/azmsi/scss/pre.scss` + `post.scss`.

2. **`theme_moove_get_extra_scss($theme)`** — appends the login background image
   rule + the admin-entered `scss` setting. Returns `''` if no login bg set.

3. **`theme_moove_get_pre_scss($theme)`** — maps Moove admin settings to SCSS
   variables **before** compilation:
   - `brandcolor`        → `$brand-primary`
   - `secondarymenucolor`→ `$secondary-menu-color`
   - `fontsite`          → `$font-family-sans-serif` (skipped when value is `Moodle`)
   - then appends the admin `scsspre` setting.
   → Agent 02’s design tokens should be injected at the **pre** stage (so they’re
   available as `$variables` to the whole cascade), with component/utility CSS at
   the **main/extra** stage.

4. **`theme_moove_get_precompiled_css()`** — returns `theme/moove/style/moodle.css`
   (a perf fallback for un-compiled installs).

### Other Moove lib functions (not SCSS, noted for completeness)
- `theme_moove_pluginfile(...)` — serves theme file areas (logo, login bg, preset, etc.).
- `theme_moove_serve_hvp_css(...)` — H5P CSS integration.

## Override strategy for `theme_azmsi` (summary for Agent 02)
- **Tokens / variables** → emit at the `pre_scss` stage (`theme_azmsi_get_pre_scss`).
- **Component + utility CSS** → append at the end of `main_scss` (after Moove’s
  cascade) via `theme/azmsi/scss/post.scss`, so azmsi rules have last-word specificity.
- **Login background / one-off admin SCSS** → keep Moove’s `extra_scss` behaviour;
  extend in `theme_azmsi_get_extra_scss` only if needed.
- **Templates** → override by mirroring the Mustache path under
  `theme/azmsi/templates/` (Moodle resolves child-theme templates first).
- **Layouts** → add to `theme_azmsi/config.php` `layouts` only for layouts whose
  PHP markup must change; otherwise inherit moove→boost.

_Last inventoried: 2026-06-15 against installed Moove 5.1.2._
