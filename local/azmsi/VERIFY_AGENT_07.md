# Agent 07 — Admin console (S12) verification

Gold-accented, manager-gated console wired to live data + the scheduled-task
cache. File-only + offline validation + a read-only composition check; nothing
installed/upgraded/purged on prod.

## What this delivers
- **Pipeline write WS** — `classes/external/update_pipeline_stage.php` (typed,
  `require_capability('local/azmsi:managepipeline')` + `validate_context`,
  justification; sesskey enforced by the ajax/token transport and by the console
  POST path). Registered in `db/services.php` under `azmsi_ws`.
- **Shared `\local_azmsi\local\pipeline`** — `set_stage()` writes the stage with
  audit (`updatedby`, `timemodified`); **`stage_launch=done` flips the course
  `status` custom field to `in_progress` (via `update_course`) and queues
  `revalidate_website`**, so the public "courses built" + curriculum badges
  refresh with no code edit. `get_all()` reads the pipeline for the console.
- **`\local_azmsi\local\admin`** — KPIs read from the **cache_azmsi rollup**
  (`admin_kpis`, never computed inline), admissions funnel (live counts from
  `local_azmsi_application.stage`/`status`, bars scaled to the max), pipeline rows,
  per-role counts with capability-gated portal deep-links.
- **`admin.php` (S12)** — `require_login` + `require_capability('local/azmsi:viewadminconsole')`
  (other roles denied); gold accent (`body.azmsi-admin`). Renders KPIs, funnel,
  pipeline grid (each cell advances via a **sesskey + cap-checked POST** to
  `pipeline::set_stage`), and roles. Renderer + Mustache + theme tokens.

## Proven offline
`php -l` clean · `phpcs --standard=moodle` **0/0** · template balanced · theme SCSS
compiles (`az-admin`/`az-funnel`/`az-pipeline`) · `admin::console()` ran live:
KPIs from cache (coursesbuilt 4/48), funnel 5 stages, 48 pipeline courses, 3 roles.
(Stage labels show `[[pipeline_stage_*]]` only until the lang cache is purged.)

## How to verify on staging
1. **(approval)** `php /var/www/moodle/admin/cli/upgrade.php` — register the new
   `update_pipeline_stage` WS (version → 2026061800).
2. **(approval)** `php /var/www/moodle/admin/cli/purge_caches.php` — load new
   classes + templates + SCSS + the new lang strings.
3. As a **manager** open `/local/azmsi/admin.php` → KPIs/funnel/pipeline/roles
   render; as a **non-manager** → access denied. (Fixes the Switch-Portal "Admin
   console" link.)
4. **(approval)** `php /var/www/moodle/admin/cli/cron.php` — runs `rollup_admin_kpis`
   so the KPI cache reflects current data; add a student / application → KPIs +
   funnel change on next rollup/reload.
5. Click a pipeline stage → it advances (audited `updatedby`/`timemodified`),
   cap+sesskey enforced. Set **stage_launch=done** → the course `status` flips to
   in_progress and a `revalidate_website` task is queued (drains on cron) → the
   public site's "courses built" + curriculum badge update.

## Commands needing your approval (mutate live production)
- `php /var/www/moodle/admin/cli/upgrade.php` (register the write WS).
- `php /var/www/moodle/admin/cli/purge_caches.php` (classes/templates/SCSS/lang).
- `php /var/www/moodle/admin/cli/cron.php` (drives the KPI rollup + revalidate task).
- The revalidate webhook only fires if `local_azmsi/revalidate_url` + secret are set (AGENT_10).
