# Agent 04 — `format_emd` verification runbook

The eMD weekly-module course format + master-template header (S5). Built to keep
completion/grades/availability **native** — the format adds structure + the
course-home header, never reimplements core.

## Design (why it's low-risk)
- The format renders via **core's reactive content** (like `weeks`): a thin
  `renderer.php` + a minimal `content` output override. So section/activity
  rendering, completion, and the **availability**-based quiz lock are all native
  — AC2 ("marking complete moves progress") and AC3 ("quiz locks until lab") work
  with zero custom logic, as long as the course author sets the activity's
  *Restrict access* rule.
- The S5 master-template **header** is added via `course_content_header()` (a core
  hook rendered above the content — no reactive-template fork), implemented as a
  `named_templatable` renderable → `templates/course_header.mustache`. Structure +
  `.az-*` classes only; colour comes from `theme_azmsi/_course.scss` (`.az-coursehead`).

## Proven offline + live
| Check | Result |
|---|---|
| `php -l` all PHP | clean |
| `phpcs --standard=moodle` | 0 / 0 |
| `course_header.mustache` balance | balanced |
| theme SCSS compiles (with `.az-coursehead`) | OK (118 `.az-*` classes) |
| Installed on live site | `format_emd` v2026061500, no pending upgrade |
| **Live header render** (EMD-101 set to `format_emd`) | ✅ `code=EMD-101, credits=4`, title clean, HTML renders; completion/grade guarded when absent |

## Header data sources (all live, none hardcoded)
- title → course fullname; code/credits/faculty → course **custom fields**
  (seeded by `local_azmsi`); progress → `core_completion\progress`; grade →
  gradebook course item (`grade_item`/`grade_grade`, formatted %).

## Remaining runtime verification (needs a populated course)
The seeded courses are shells (no activities yet). To verify full **S5/S6**
fidelity + the live numbers and the lock:
1. In `EMD-101` (now `format_emd`), add the master weekly activities (video,
   readings, H5P lab, discussion, quiz, assignment) to a week section.
2. Add an **availability** rule on the quiz: *restrict until* the H5P lab is
   marked complete → confirm the quiz shows locked until the lab is done (AC3).
3. As an enrolled student, complete an activity → the course % in the header and
   the week progress move with no code change (AC2).
4. Confirm the page renders under `theme_azmsi` (dark) and degrades under Boost.

## Notes
- `EMD-101` was switched to `format_emd` during verification (intended — all eMD
  courses use this format). Other seeded courses remain on the site default until
  changed (e.g. by the production pipeline / a bulk update).
- The format intentionally stores **no content** — every item is a real Moodle
  activity.

---

## Reconciliation pass (2026-06-16) — S5 right rail + S6 status

**Added (live data, guarded, offline-validated):** the S5 right rail on the
course-home header (`course_header` renderable + `course_header.mustache` +
theme `.az-course-rail`):
- **Instructor** — `faculty_name` custom field.
- **Grading & rubric** — gradebook top-level **category weights**
  (`grade_category::fetch_all` → `aggregationcoef2`), read live.
- **Objectives** — the course **summary** (`format_text`).
- **Latest feedback card** — the most recent graded item with feedback for the
  viewing user (`grade_grades.feedback`), read live.

Each card renders **only when it has data**; all gradebook/feedback reads are
`try/catch`-guarded, so an empty/new course (e.g. EMD-101) renders cleanly —
verified by a read-only render on EMD-101 (export ok, no error, rail present).

**S6 weekly module — division of labour (no reactive-template fork):**
- The numbered **activity sequence with real state** (watched/complete/due/locked)
  is rendered **natively** by core's reactive course format (cmlist) — completion
  badges, availability lock info and due dates all come from the completion +
  availability APIs. The format keeps this native (AC2/AC3 work with no custom
  logic) and `theme_azmsi/_course.scss` styles it to the Bold look.
- The **faculty-feedback card** appears via the shared header rail (it renders on
  the section/week page too).
- The grouped-by-type pixel layout is a **styling layer** over the native cmlist;
  it can only be tuned/verified against a **populated** course (activities present).

## Commands that need your approval (mutate live production)
1. `php /var/www/moodle/admin/cli/purge_caches.php` — to serve the new rail
   template + SCSS. (No `upgrade.php`; no version bump.)
2. **Create/convert a course to `format_emd` with the master activities** on
   staging (video, readings, H5P lab, discussion, quiz, assignment, reflection),
   add an **availability** rule (quiz restricted until the H5P lab is complete) —
   so S5/S6 render with live numbers and the lock can be confirmed. Do this on a
   staging clone (or with explicit go-ahead), not bare production.
