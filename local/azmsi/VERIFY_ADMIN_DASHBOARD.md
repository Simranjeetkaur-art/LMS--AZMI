# Admin dashboard (S12) — prototype build + cron/event refresh

Expands the Agent 07 admin console to match the admin prototype and moves **all**
heavy aggregation off the page and into a CRON + event-driven rollup. Every widget
is data-bound; nothing is hardcoded. File-only + offline validation + a read-only
live compute check — nothing installed/upgraded/purged on prod.

## What this delivers
- **`\local_azmsi\local\admin_rollup`** — one read pass over every AZMSI course +
  role/enrolment/completion data that produces the whole dashboard dataset:
  KPIs, **courses-by-status** (running/scheduled/draft/archived, derived from the
  `status` custom field + visibility + dates), **admissions funnel**
  (applications → AQE → admitted → enrolled → active-this-week), **system health**
  (cron freshness from `task_scheduled.lastruntime` + dataroot storage % — sources
  with no data, e.g. live sessions/tickets, are *omitted*, not faked),
  **course operations** (per course: lead faculty, students, modules, activities,
  status, avg activity-completion), **faculty load**, **users-by-role**.
  `rebuild()` writes `cache_azmsi` keys `admin_console` (full) **and** `admin_kpis`
  (so the existing `get_admin_kpis` WS is unchanged).
- **CRON** — `rollup_admin_kpis` (every 10 min) now calls `admin_rollup::rebuild()`
  (the heartbeat that guarantees freshness with no events).
- **Events** — new adhoc task `refresh_admin_console` rebuilds the dataset; the
  observer queues it **deduplicated** on `course_created/updated/deleted`,
  `role_assigned/unassigned`, `user_enrolment_created/deleted`, `course_completed`.
  Dedup → at most one rebuild pending no matter how many events fire, so the
  console tracks live activity with no per-event cost.
- **`\local_azmsi\local\admin::console()`** — reads the cached dataset (pure cache
  read); bootstrap-computes **once** if the cache is cold (e.g. first load after a
  purge). The only live reads on the page are the production **pipeline** (so a
  stage advance shows immediately) and the viewer-gated **portal** links.
- **Template + theme** — `admin_console.mustache` rebuilt to the prototype layout
  (KPI strip · 3-up: courses-by-status donut / funnel / system health · course
  operations table · 2-up: faculty load / users-by-role · pipeline grid · portal
  switch). `_admin.scss` adds the conic donut, legend, health rows, status pills,
  completion meters — all widths via `--az-pct` (no inline cosmetics).

## Proven offline
`php -l` clean (9 files) · `phpcs --standard=moodle` **0/0** (after phpcbf) ·
mustache **section-balanced**, all 25 `{{#str}}` keys defined · theme SCSS
compiles end-to-end in the real pipeline order (pre + partials + post; `.az-donut`
present) · **`admin_rollup::compute()` ran live read-only in 268 ms**:
running **4/48**, draft 44; cron health OK + storage 11 %; 48 course-ops rows;
funnel + users-by-role populated. (Counts that are zero — students/enrolments —
are *real* zeros: no students enrolled yet. Labels show `[[…]]` only until the
lang cache is purged; the strings are in the file.)

## How to verify on staging
1. **(approval)** `php /var/www/moodle/admin/cli/upgrade.php` — register the new
   `refresh_admin_console` task (version → 2026061801).
2. **(approval)** `php /var/www/moodle/admin/cli/purge_caches.php` — load the new
   class + adhoc task + template + SCSS + lang strings (resolves the `[[…]]`).
3. Open `/local/azmsi/admin.php` as a **manager** → full dashboard renders from the
   cache; as a **non-manager** → access denied.
4. **(approval)** `php /var/www/moodle/admin/cli/cron.php` — runs `rollup_admin_kpis`
   (warms the dataset) and drains any queued `refresh_admin_console`.
5. **Event refresh:** enrol a student / assign a teacher / flip a course status →
   a single `refresh_admin_console` is queued (dedup) → after the next cron run the
   KPI, courses-by-status, faculty-load and users-by-role widgets reflect it with
   no page edit.

## Prototype reconcile (matches the admin design reference)
- **Removed** the redundant KPI strip — the page now opens on the 3-up widget row
  exactly like the prototype (courses-by-status donut / admissions funnel / system
  health with an **OPERATIONAL / DEGRADED** badge derived from cron + storage).
- **System health** now shows cron freshness, human-readable storage
  (`5.2 GB / 47.3 GB (11%)`) and **users online now** (real; live sessions /
  tickets omitted — no source on this site).
- **Course operations** shows the most relevant slice (running-first, top 8) with a
  live "Showing 8 of 48" header.
- Bottom row is now **3-up**: **Faculty load** (avatar initials + department + per
  -faculty course/student counts), **Users by role**, and a new **Announcements on
  Home** widget (live from the site news forum; `+ New` gated on
  `mod/forum:addnews`). Empty states are real, not placeholders.
- The **production pipeline** (with the cap+sesskey write) moved below the overview
  so the top matches the prototype while keeping the build workflow.

## Commands needing your approval (mutate live production)
- `php /var/www/moodle/admin/cli/upgrade.php` (register the adhoc task; versions
  local_azmsi 0.5.2 / theme_azmsi 0.2.2).
- `php /var/www/moodle/admin/cli/purge_caches.php` (class/task/template/SCSS/lang).
- `php /var/www/moodle/admin/cli/cron.php` (drives the rollup + drains adhoc refreshes).
