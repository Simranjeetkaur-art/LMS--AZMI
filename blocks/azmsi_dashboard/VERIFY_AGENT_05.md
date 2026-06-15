# Agent 05 — Student experience (S4 dashboard, S7 gradebook, S8 quiz) verification

Wired entirely to live Moodle data; no hardcoded numbers. Nothing was installed,
upgraded, purged, or placed on the live dashboard — file-only + offline validation
+ a read-only composition check.

## S4 — Student dashboard (`block_azmsi_dashboard`) — BUILT
- **Shared composition:** `local_azmsi\local\overview::for_user()` is the single
  source for both the WS (`local_azmsi_get_student_overview`, website) and this
  block — so numbers are computed once, never recomputed/hardcoded, and cached in
  `cache_azmsi` (invalidated by the Agent 03 observers/tasks).
- **Sections (all live):** greeting (real `firstname`), continue card (last-viewed
  activity via logstore → fallback last-accessed course), in-progress course cards
  (per-course **completion %**, status badge, credits/code from custom fields,
  real course URL), Due This Week (**calendar action events**), program map Q1–Q12
  (status from enrolment + completion of the quarter custom field), KPIs (modules
  completed, due count, current average from the **gradebook**).
- **Switch Portal** reused from `theme_azmsi` (capability-gated; only permitted links).
- Output via renderer + `block_azmsi_dashboard/dashboard` Mustache + `theme_azmsi`
  tokens (`.az-dashboard`, `.az-progmap`, reused `.az-continue-banner`/`.az-course-card`/
  `.az-kpi`). Progress bar width is data-bound via a `--az-pct` CSS var (no inline cosmetics).
- Validated: `php -l` clean, `phpcs --standard=moodle` **0/0**, mustache balanced,
  theme SCSS compiles, and `overview::for_user()` ran clean for a sparse account
  (0 courses → 12-quarter map, guarded, no error).

## S7 — Gradebook — THEMED (native report) + flagged extras
- `theme_azmsi/scss/_gradebook.scss` themes Moodle's **native** user grade report
  (`gradereport_user`) to the Bold dark system — the report data, statuses and
  feedback are all native/live.
- **Deferred (own pass):** the bespoke course-cards + rubric-weighting panel +
  "Play recorded feedback" (streaming the real assign feedback file URL). These are
  a custom surface beyond theming and need graded data to build/verify.

## S8 — Quiz / AQE — covered by Agent 02 chrome
- `theme_azmsi/scss/_quiz.scss` (Agent 02) styles the **native** quiz attempt page:
  the question navigator coloured **by real `.qnbutton` state** (answered/current/
  flagged/correct…), the timer, and the submit panel. Same UI serves weekly quizzes
  and the AQE; Save & Submit are native quiz actions; SEB is honoured by core.
- **Optional future:** dedicated `mod_quiz` *template* overrides for a richer exam
  top-bar. Not required for navigator/timer state (those are native + styled).

## How to verify on staging (needs your approval — mutates prod)
1. `php /var/www/moodle/admin/cli/purge_caches.php` — **required**: registers the new
   classes (`overview`, `dashboard`) in the autoload classmap and serves the new
   templates/SCSS. (No `upgrade.php`; no version bump.)
2. Add **block_azmsi_dashboard** to the **default Dashboard** (`/my`): Site admin →
   Appearance → Default Dashboard page → add the block (full width). Optionally set
   Default home page = Dashboard (Agent 02a routing) so students land here.
3. **Enrol a student** in an `format_emd` course with activities; as that student
   open `/my` → greeting, course card %, due-this-week, program map, average all
   show live values. **Complete an activity** → the card % + KPIs change with no
   code edit. **Grade an assignment** → current average changes (after cron runs
   `recompute_*`, which invalidates `cache_azmsi`).
4. Open a quiz attempt → navigator/timer reflect the real attempt; the AQE uses the
   same UI. Grade with a recorded-feedback file → it appears in the native report.

## Commands needing your approval (mutate live production)
- `php /var/www/moodle/admin/cli/purge_caches.php` (load new classes + assets).
- Adding the block to the default `/my` dashboard + (optional) `defaulthomepage`.
- Enrolling a test student / creating activities — on a staging clone, not bare prod.
