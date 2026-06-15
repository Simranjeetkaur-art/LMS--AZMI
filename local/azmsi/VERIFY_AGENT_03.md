# Agent 03 — `local_azmsi` core verification runbook

The data + integration backbone: 5 DB tables, full capability set, typed
`core_external\*` WS functions, event observers, and tasks that keep
`cache_azmsi` live. **Runtime steps install the plugin / run cron and must NOT
run on production without your go-ahead** (maintenance mode + DB & dataroot
backup, staging first).

---

## Proven offline (no site changes)

| Check | Result |
|---|---|
| `php -l` (all PHP) | clean |
| `db/install.xml` | well-formed XML |
| `phpcs --standard=moodle` (incl. tests) | **0 errors / 0 warnings** |

phpunit + WS-token + seeding need a configured DB, so they run on staging / CI
(the `.github/workflows/ci.yml` matrix runs phpunit + codechecker on branch 501).

## What this delivers

- **5 tables** (`db/install.xml` + `db/upgrade.php`): `local_azmsi_research`,
  `local_azmsi_milestones`, `local_azmsi_documents`, `local_azmsi_pipeline`,
  `local_azmsi_application` (the 5th, required now per spec).
- **Full capability set** (`db/access.php`): `viewfacultyportal`,
  `viewadminconsole`, `mentorresearch`, `managepipeline`, `reviewapplications`
  + WS read caps `ws_catalog`, `ws_student`, `ws_faculty`, `ws_admin`, `ws_apply`.
- **Domain model + seeding** (`classes/local/program.php`, `cli/seed_catalog.php`):
  8 course custom fields + the 48-course Year→Quarter→Course catalog; idempotent;
  Q1 live, Q2–Q12 planned. Tree read back from live Moodle data.
- **Typed WS** (`classes/external/*`, declared in `db/services.php`, service
  `azmsi_ws` shipped **disabled**): `get_program_catalog` (ws_catalog),
  `get_student_overview` (ws_student, cached), `get_admin_kpis` (ws_admin).
  Each enforces its capability + has a justification PHPDoc. Remaining functions
  are listed as TODO until their classes land (spec §5).
- **Observers → tasks → cache** (`classes/observer.php`, `classes/task/*`):
  enrolment / completion / quiz / assign-grade / course-complete observers stay
  thin and queue adhoc tasks; `recompute_course_progress` writes per-course
  progress into `cache_azmsi`. Scheduled rollups: `rollup_admin_kpis` (10m),
  `rollup_class_health` (15m), `refresh_overview_caches` (5m).
- **Privacy provider** (`classes/privacy/provider.php`): metadata + export +
  delete for all four personal-data tables.
- **phpunit tests** (`tests/`): catalog seed/tree/rename, recompute→cache +
  assign-grade-queues-task, privacy export/delete, WS capability enforcement.

## Runtime verification (staging; your go-ahead)

```bash
# staging, maintenance mode, DB + dataroot backup taken first:
php public/admin/cli/upgrade.php --non-interactive     # installs local_azmsi (5 tables, caps, tasks)
php public/local/azmsi/cli/seed_catalog.php            # AC1: seed 48 courses (idempotent)
php public/local/azmsi/cli/seed_catalog.php            # run again -> "Created 0, updated 48"
```

- **AC1 — catalog:** after seeding, Site admin → Courses shows the Year→Quarter
  tree + 48 courses. Rename EMD-101 in Moodle, then re-read the catalog (see WS
  below) — the name changes with no code edit.
- **AC2 — WS typed + capability:** enable web services + REST on staging, enable
  the `azmsi_ws` service, grant `ws_catalog` to the token user, mint a token:
  ```
  curl '.../public/webservice/rest/server.php?wstoken=TOKEN\
  &wsfunction=local_azmsi_get_program_catalog&moodlewsrestformat=json'
  ```
  returns the typed tree. A token without `ws_catalog` is rejected
  (`required_capability` / `webservice_access_exception`).
- **AC3 — observer → cache:** as a student, submit a quiz (or grade an assign as
  faculty) → `php public/admin/cli/cron.php` runs the queued
  `recompute_course_progress` → the `cache_azmsi` `progress_<course>_<user>` key
  updates. (`tests/progress_cache_test.php` asserts this.)
- **AC4 — install + privacy:** the 5 tables exist (Site admin → Development →
  XMLDB or DB), all caps appear under Define roles, and
  `vendor/bin/phpunit local/azmsi/tests/privacy_provider_test.php` passes.

## Notes
- `azmsi_ws` ships **disabled** with no tokens — AGENT_01 enables it + mints
  scoped service-account tokens on staging.
- `get_student_overview` / `get_admin_kpis` read `cache_azmsi`; the cache warms
  via observers (reactive) + the scheduled rollups (cron).
