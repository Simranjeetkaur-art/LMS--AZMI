# local_contentchecker — content verification, enrichment and delivery

A Moodle local plugin for medical education courses. It cross-checks weekly
course content against reference material using self-hosted AI, lets editors
approve or reject every suggested correction by hand, and adds enrichment,
publish layouts, read-aloud and ungraded self-check questions on top.

Built against **Moodle 5.1.5** (branch 501).

## Status

**Built, symlinked, not installed.** The database tables have never been
created. Installing needs a Moodle upgrade on a live production site, which
needs an explicit go-ahead, maintenance mode and a database backup.

## The one rule everything else follows

**No AI output ever reaches a learner or changes live content without a person
approving it.** There is no code path that auto-applies a suggestion and no
setting that enables one. Every decision records who made it, when, and the
before/after text, in a table that survives log retention policies.

## What it does

| Module | Entry point |
|---|---|
| Verification | Course → *Content checker*, and Site administration → *Content checker overview* |
| Diff review | `week.php` — original vs suggestion, with sources, per finding |
| Enrichment | `enrich.php` — insert a registered 3D model, diagram or comparison table |
| Publish layouts | `publish.php` — pick one of four layouts, with live preview |
| Follow-up questions | `questions.php` — review, edit and approve generated self-checks |
| Read-aloud | Attached automatically to learner-facing activity pages |
| Audit trail | `audit.php` — who decided what, when, and what changed |

Run modes:

* **One activity** runs inline in the AJAX request under a GPU slot. Bounded
  work, and the editor genuinely can wait for it.
* **One week or a whole course** is queued as an adhoc task and polled. A week
  is several hundred model calls; running that inline would hit the PHP time
  limit and hand the editor a dead spinner instead of an answer.

A whole-course job fans out into one task per week, so cron makes progress in
bounded steps and one bad week cannot lose the job. The last task to finish
sends the Moodle notification.

## Capabilities

No role is ever named in plugin code. Every page, AJAX endpoint and web service
gates on a capability, so an admin can re-map them through the normal role UI.

| Capability | Default | What |
|---|---|---|
| `local/contentchecker:view` | teacher, editing teacher, manager | read dashboards and findings |
| `local/contentchecker:manage` | editing teacher, manager | run checks, enrichment, layouts, questions |
| `local/contentchecker:approve` | editing teacher, manager | decide on a suggestion, including writing to live content |
| `local/contentchecker:runbackground` | editing teacher, manager | queue a background job |
| `local/contentchecker:viewsitereports` | manager (site) | status across all courses |
| `local/contentchecker:manageconfig` | manager (site) | sources, assets, pronunciation dictionary |

`db/access.php` archetypes cover the shipped roles. `db/install.php` also grants
the editor capabilities to any **existing custom role** that already holds both
`moodle/course:manageactivities` and `moodle/course:update` — that is how a
site-defined "Faculty" role with no archetype gets picked up, without naming it.

`:approve` is deliberately separable from `:manage`: a site can grant the
dashboard without granting the authority to change what students read.

## Two guarantees the verifier makes

**Nothing is cited that was not retrieved.** The adjudicator only ever sees
passages pulled from the configured reference layer, and a supporting quote must
be ≥20 characters and present verbatim in a cited source before the UI shows it
as trustworthy. Model-invented citations are the characteristic failure of this
kind of tool and are unacceptable in a medical context.

**No verdict without evidence.** If nothing clears the similarity floor the
claim is reported as `needs_source` rather than judged on weak evidence.

## The `contested` verdict

Pass 1 shows the adjudicator all retrieved passages together. Only
`contradicted` and `partially_supported` escalate to pass 2, where each passage
is judged alone; disagreement downgrades the verdict to `contested`.

This exists because of a measured false positive. Judged against one passage,
the adjudicator flagged the EMD-102 line *"standing upright with feet together"*
as CONTRADICTED at **confidence 1.0**. The reference actually reads *"feet
together (or slightly separated)"* — the course was right and the machine was
certain and wrong.

Model confidence is recorded and displayed for inspection but is **never** used
to rank, filter or gate anything.

## Settings that are not preferences

Every default under *Request tuning* was established by measuring the live
gateway. Changing one without re-measuring reintroduces a failure that is hard
to diagnose from a dead task.

| Setting | Why it exists |
|---|---|
| `think: false` (hardcoded) | The adjudicator is a thinking model — it returns text in a `thinking` field and leaves `response` an **empty string**. Without this the claim extractor silently produces nothing. |
| `stream: true` (hardcoded) | The gateway is Cloudflare-fronted and returns **HTTP 524** when the origin takes >~100s to first byte. A cold load of the 35b adjudicator exceeds that. |
| abort-on-`done` (hardcoded) | The gateway holds the connection open after the terminal object. Reading to EOF blocks until timeout — measured **0.98s aborting vs a 9-minute hang**. |
| `num_predict` | A JSON schema constrains *shape*, not *length*. An unbounded `reasoning` field ran away, returned truncated JSON, and — because Ollama serialises per model and keeps generating after a client disconnects — **blocked every claim queued behind it**. |
| `num_ctx` | The box loads models with a 262144-token context by default: 55.4 GB resident across three models, forcing evictions between them. |
| Short `timeout` | A request can sit behind a busy model with zero bytes returned. Failing fast and retrying drains past the queue; a long timeout just blocks the run. |
| `maxconcurrent` | One GPU, serialised internally. A high number does not buy parallelism, it buys queueing inside requests that are already waiting. |

## Reference material

Live keyword search against third-party APIs is deliberately **not** a retrieval
channel. Measured: PubMed relevance-searching for foundational anatomy returned
CT-gantry papers, NCBI Bookshelf restricted to StatPearls returned a video
laryngoscopy article for an anatomical-position query, two of three claims
retrieved nothing, and NCBI then began returning HTTP 429.

Instead, an admin allowlists sources under *Reference sources*; they are fetched,
chunked on sentence boundaries and embedded, and retrieval is cosine similarity
over those passages.

Sources carry a tier: 1 licensed textbook, 2 guideline body, 3 open reference.
**A tier-3-only corpus can demonstrate that the pipeline behaves; it cannot
settle a clinical question.** The UI shows a standing warning while that is the
case.

If a future GPU model gains its own web/RAG access, switch *Retrieval strategy*
to "The model retrieves its own sources". Both strategies implement
`\local_contentchecker\reference\fetcher`, so that is a config change.

## Applying a correction

An approved correction is written by locating the **original sentence** in the
stored HTML and replacing just that, so every other byte of the page — images,
embeds, markup, adjacent paragraphs — is untouched. Two attempts are made: a
literal match, then a tag-tolerant match that allows tags and entities *between*
words but never inside one.

If the sentence cannot be located unambiguously the write is **refused** and the
editor is told to make the change by hand. Refusing is correct: a fuzzy match
that lands in the wrong sentence of a clinical page is far worse than a manual
edit.

## Follow-up questions are not quiz questions

They are a table and a renderer of their own, not a wrapper over `mod_quiz`.
Quiz attempts create grade items, feed course completion and appear in the
gradebook; the requirement here is the opposite — a self-check a learner can get
wrong with no consequence and no record. Marking happens in the browser and
nothing is sent back.

Generated questions are validated before storage: an answer key indexing past
the end of its options list is dropped rather than shown, because it would tell
a learner a correct answer is wrong.

## Read-aloud

Default engine is the browser's **Web Speech API** — free, instant, no backend.
Content is segmented into sentence-level spans on load, so clicking any sentence
starts narration *from there* rather than from the top.

The optional high-quality voice routes the same segments to a self-hosted TTS
server. While no endpoint is configured the option is **hidden entirely** rather
than offered and broken. A pronunciation dictionary is applied server-side, so a
medical term is said the same way on every page.

## Install

```bash
/var/www/azmsi-plugins/infra/deploy-symlinks.sh    # creates public/local/contentchecker
# then, with maintenance mode + a DB backup, on explicit go-ahead:
sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php
sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php
```

Then configure the endpoint and token at *Site administration → Plugins → Local
plugins → Content checker → Settings*, and add at least one reference source.

Note that on this Moodle's `public/` layout, CLI scripts live at
`/var/www/moodle/admin/cli/`, **outside** the webroot — not under `public/`.

## Development notes

`amd/src` is written in AMD `define()` form rather than ES6 modules, and
`amd/build/*.min.js` are byte-identical copies. This is deliberate: no Node or
npm is installed on this server, so `grunt amd` cannot run, and Moodle's dev-mode
loader serves `amd/src` verbatim when a build file has no source map. Keeping one
source of truth in a syntax both paths can serve means the plugin works without a
build step. If Node is installed later, `grunt amd` will regenerate `amd/build`
from `amd/src` normally.

Table names use the `local_cchecker_` prefix rather than `local_contentchecker_`
because Moodle caps an XMLDB table name at 28 characters and
`local_contentchecker_suggestions` is 32.

## Tests

PHPUnit is initialised on this server. Run the suite with:

```bash
cd /var/www/moodle
sudo -u www-data vendor/bin/phpunit --testsuite local_contentchecker_testsuite
```

Last run: **29 tests, 45 assertions, 0 failures, 0 errors** on PHPUnit 11.5.55 /
PHP 8.5.4 / PostgreSQL 18.4.

| File | Covers |
|---|---|
| `applier_test.php` (8) | locating and replacing a sentence in stored HTML — mostly the cases where it must *refuse* |
| `blocks_test.php` (6) | block splitting and reference stability across edits |
| `pronunciation_test.php` (6) | dictionary matching, word vs substring, ordering |
| `questions_test.php` (9) | rejecting malformed generated questions; layout slot handling |

Tests inject `tests/fixtures/stub_backend.php` rather than calling the GPU: a
live model is slow, non-deterministic, and would fail the suite whenever the box
is busy. That is what the `ai_backend` interface is for.

The run reports 4 *PHPUnit test-runner deprecations* about `@covers` metadata in
doc-comments. These are not failures and are not specific to this plugin —
Moodle 5.1 core uses doc-comment metadata in 219 test files and PHP attributes in
zero, and a core file such as `public/lib/tests/setuplib_test.php` emits the same
warning. This plugin follows core's convention deliberately; migrating is a
Moodle-wide change for whenever core moves to PHPUnit 12.

### Setting PHPUnit up (already done here, recorded for a rebuild)

```bash
# config.php gains, before require_once(.../lib/setup.php):
#   $CFG->phpunit_prefix = 'phpu_';
#   $CFG->phpunit_dataroot = '/var/moodledata_phpu';
# Both MUST differ from $CFG->prefix and $CFG->dataroot — PHPUnit drops every
# table carrying phpunit_prefix.
sudo locale-gen en_AU.UTF-8            # Moodle requires this locale
php /tmp/composer install              # populates vendor/
sudo -u www-data php public/admin/tool/phpunit/cli/init.php --disable-composer
```

`--disable-composer` is needed because init's `composer self-update` fails as
`www-data` (no writable `$HOME`); dependencies are installed separately above.

## Bugs this work surfaced and fixed

* **`db/install.php` assigned capabilities before they existed.** Moodle runs
  `xmldb_<plugin>_install()` *before* `upgrade_component_updated()` loads
  `db/access.php`, so `assign_capability()` threw "capability not found" and
  aborted the install. Fixed by calling `update_capabilities()` first.
* **`local_officeviewer` broke any DB-less bootstrap.** Its `after_config` hook
  called `get_config()` unconditionally, which fails during initial install,
  upgrade and the PHPUnit bootstrap where `{config}` does not exist. It also
  meant a DB read on *every* request. Fixed by moving the cheap script-path
  checks ahead of the DB access. This is outside this plugin, at
  `public/local/officeviewer/classes/hook_callbacks.php`.

## Not done

* No Behat coverage. The browser-side flows -- click-to-read-from-position,
  the spoken-segment highlight, and the image picker grid -- are verified by
  code inspection and unit tests, but have not been clicked through. Treat them
  as unproven until the manual walkthrough covers them.
* The high-quality (Piper) read-aloud voice has never been contacted. No
  `tts_endpoint` is configured, so `tts_client` is untested against a real
  server and the pronunciation dictionary's effect on synthesis is unverified.
  The dictionary's text substitution itself is covered by tests.

## History: local_emdverify

The verification engine came from `local_emdverify`, which was written but never
installed. It was archived and removed in two commits so the original work stays
recoverable:

```bash
git log --oneline --diff-filter=A -- local/emdverify   # the archive commit
git show <archive-commit>:local/emdverify/README.md    # read it back
```
