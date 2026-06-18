# Agent 06 — Faculty / Instructor views (S10, S11) verification

Role-gated, teal-accented teacher surfaces, wired to live data. File-only +
offline validation + a read-only composition check — nothing installed/upgraded/
purged on the live site.

## What this delivers
- **WS prerequisite** — `classes/external/get_faculty_overview.php` (typed
  `core_external\*`, `require_capability('local/azmsi:viewfacultyportal')` +
  `validate_context` + justification PHPDoc), registered in `db/services.php`
  under `azmsi_ws` (cap `local/azmsi:ws_faculty`). Both the cap and
  `viewfacultyportal` already exist in `db/access.php`.
- **Shared composition** — `\local_azmsi\local\faculty` (S10) and
  `\local_azmsi\local\instructor` (S11); reused by the WS + the in-LMS pages so
  every count is **one live query**, never hardcoded.
- **S10 faculty dashboard** — `faculty.php`: `require_login` +
  `require_capability('local/azmsi:viewfacultyportal')` (students denied), teal
  accent (`body.azmsi-faculty`). KPIs (courses/students/grading-queue/on-track/
  at-risk), courses-taught cards (student count, ungraded count, gradebook class
  avg), agenda (calendar). "Manage" → `instructor.php?courseid=`.
- **S11 instructor course** — `instructor.php?courseid=`: `require_capability`
  `viewfacultyportal` + `mod/assign:grade` in the course. Submissions-to-grade
  table **deep-links to the native grader** (`/mod/assign/view.php?action=grader`)
  — grader not rebuilt. At-risk list (low grade / inactive, query-driven) with a
  **Message** deep-link (`/message/index.php`). Roster from the enrolled-users API.
  Rubric panel **reads the course's active advanced-grading (rubric) definition**
  (`get_grading_manager` → criteria) and deep-links to the native grader to grade.

## Proven offline
`php -l` clean · `phpcs --standard=moodle` **0/0** · both templates balanced ·
theme SCSS compiles (`az-faculty`/`azmsi-faculty`/`az-table`) · `faculty::overview_for`
+ `instructor::for_course` ran clean for a sparse account (all zeros, guarded, no error).

## Honest scope note
The spec's **quick-rubric WRITE form** (submitting grades through the grading API
from this panel) is **deferred**: writing grades via the advanced-grading API is
untestable on bare production and the native grader (deep-linked here, per
"don't rebuild the grader") already writes rubric grades. The panel currently
**reads + shows the real criteria** and routes grading to the native grader.

## How to verify on staging
1. **(approval)** `php /var/www/moodle/admin/cli/purge_caches.php` — load the new
   classes + serve templates/SCSS (makes `faculty.php`/`instructor.php` usable).
2. **(approval)** `php /var/www/moodle/admin/cli/upgrade.php` — register the new
   `local_azmsi_get_faculty_overview` WS function (version bumped to 2026061700).
3. As a **teacher** (editingteacher in ≥1 course): open `/local/azmsi/faculty.php`
   → KPIs + course cards + agenda render with live numbers; "Manage" opens
   `/local/azmsi/instructor.php?courseid=`. As a **student** → access denied.
4. **Grading queue:** the instructor submissions table lists real ungraded
   submissions; grade one in the native grader → it leaves the queue on reload
   (the queue is a live query, not a stored count).
5. **Rubric:** on a course whose assignment uses a rubric grading method, the
   panel shows that rubric's actual criteria; "Grade with rubric" opens the
   native grader.
6. **At-risk:** appears for students with <60% course grade or 14+ days inactive;
   updates as data changes.

## Commands needing your approval (mutate live production)
- `php /var/www/moodle/admin/cli/purge_caches.php` (pages/classes/assets).
- `php /var/www/moodle/admin/cli/upgrade.php` (register the faculty WS function).
- Enrolling a test teacher/student + grading — on a staging clone, not bare prod.
