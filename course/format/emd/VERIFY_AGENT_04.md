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

## Master template auto-population + "Add a week" (2026-06-18)

The format now **stamps the master template onto every new eMD course** and adds an
**"Add a week"** action — both create real Moodle activities via the core
`add_moduleinfo()` path (completion/grades/gradebook/events stay native; no content
stored in the format).

**New/changed files (all in `format_emd`):**
- `classes/local/master_template.php` — the engine. `apply_to_course()` (idempotent)
  builds: section 0 = Course Overview + Welcome Video + Syllabus (Pages); Week 1 = the
  7-item sequence; a course-level Final Exam (Quiz) in its own last section. `add_week()`
  creates a new populated week **before** the Final Exam section. Per-module default field
  sets mirror core's test generators (page/forum/quiz/assign).
- `classes/observer.php` + `db/events.php` — observe `\core\event\course_created`; on a
  **new, empty** eMD course, apply the template. Guarded (format check + "no modules yet")
  and fully `try/catch`-wrapped so it never blocks course creation.
- `addweek.php` — endpoint behind the button: `require_login` + sesskey + `moodle/course:update`
  + `moodle/course:manageactivities`, calls `master_template::add_week()`, redirects to the
  new section. Uses the repo's symlink-safe config include (`__DIR__/../../../config.php`
  resolves into the plugins repo through the symlink, so it falls back to `/var/www/moodle/config.php`).
- `classes/output/course_header.php` + `templates/course_header.mustache` — render an
  **"Add a week"** button in the course-home header, **edit mode + capability gated**
  (core `btn btn-primary`, no theme recompile needed).
- `lang/en/format_emd.php` — template strings; native add-section relabelled "Add empty week".
- `version.php` → `2026061800` (registers the new observer on upgrade).

**Weekly sequence (7 items):** Video, Readings, H5P Lab, Discussion, Quiz, Assignment, Reflection.
- Video / Readings → **Page**; H5P Lab → **Page placeholder** (an `h5pactivity` cannot be
  created empty — it requires a content package; the placeholder tells the author to
  replace it with a real H5P activity or embed H5P via the editor); Discussion → **Forum**;
  Quiz → **Quiz**; Assignment / Reflection → **Assignment** (online-text submission enabled).

**Known limitation — restore/copy:** the observer fires on `course_created`, which also
fires when restoring/copying into a *new* course before its content is added. The
"no modules yet" guard cannot distinguish that case, so a restore/copy into a fresh eMD
course may get duplicate template items. Recommended: restore/copy into a non-eMD course
then switch format, or we add a setting to disable auto-apply. Normal "Add new course"
and the production pipeline are unaffected.

**Offline validation done:** `php -l` clean on all new/changed PHP; Mustache balanced;
all core functions used confirmed present in 5.1 with matching signatures
(`add_moduleinfo`, `course_create_section`, `course_update_section`, `course_delete_section`,
`course_create_sections_if_missing`, `course_get_url`); `add_moduleinfo` runs
`set_moduleinfo_defaults()` so the generator-mirrored field sets are sufficient.

**Runtime verification (staging — needs your approval, see below):**
1. Run `upgrade.php` to register the `course_created` observer + new version.
2. Purge caches to serve the new header button + strings.
3. Create a **new** course with format **eMD** → confirm it auto-populates:
   Course Introduction (3 pages) → Week 1 (7 activities) → Final Exam (quiz).
4. With edit mode on, click **"Add a week"** → a new populated week appears **before** the
   Final Exam; repeat to confirm numbering and ordering.
5. Confirm each created activity opens and is editable (author can add content).

## Commands that need your approval (mutate live production)
1. `php /var/www/moodle/public/admin/cli/upgrade.php` — registers the new
   `course_created` observer and the version bump (`2026061800`). **Required** for the
   master-template auto-population to fire. Staging/branch + backup first.
2. `php /var/www/moodle/public/admin/cli/purge_caches.php` — to serve the new header
   button (and the earlier rail template + SCSS).
3. **Create a new `format_emd` course** on staging and confirm auto-population, then use
   **"Add a week"** (see the verification steps above). Optionally add an **availability**
   rule (quiz restricted until the H5P lab is complete) to confirm the native lock.
   Do this on a staging clone (or with explicit go-ahead), not bare production.
