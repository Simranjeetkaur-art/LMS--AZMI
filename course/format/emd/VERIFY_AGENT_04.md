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
