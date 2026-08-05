# local_emdverify — eMD content verifier

Frontend content verification for eMD courses. Admins, managers, faculty and
editing teachers check a week's content from inside the course. **No CLI and no
code access is needed to use it** — the only CLI script is a one-off admin
bootstrap for the evidence corpus.

## Status

Phase 1, **not yet installed**. All files are written and lint clean; the
database tables have never been created. Installing requires a Moodle upgrade on
a live production site, which needs an explicit go-ahead, maintenance mode and a
backup (see the deployment notes below).

## What it does

1. An editing teacher opens **Content verification** in the course navigation.
2. They press **Verify this week**. That queues an adhoc task and returns
   immediately — a week is several hundred model calls and must never run in a
   page request.
3. The task splits the week's page prose into atomic claims, retrieves evidence
   from the local embedded corpus, and judges each claim.
4. The ledger shows claims sorted worst-first, each with its retrieved sources,
   the verbatim supporting quote, and the machine's reasoning.
5. The reviewer records **Agree / Disagree / Unsure** on each finding. That is
   both the accreditation sign-off and the measurement of whether the verifier
   can be trusted.

The overview page reports agreement rates per verdict. **Until agreement on
`Contradicted` clears 80%, treat every finding as a suggestion.** A verifier that
red-flags correct content burns faculty trust far faster than it earns it.

## Roles

| Capability | Who | What |
|---|---|---|
| `local/emdverify:view` | teacher, editing teacher, manager | read the ledger |
| `local/emdverify:run` | editing teacher, manager | queue a run (costs GPU time) |
| `local/emdverify:adjudicate` | editing teacher, manager | record the sign-off |
| `local/emdverify:managesources` | manager (site) | manage the corpus |

The non-editing teacher can read findings but cannot sign off, because the
sign-off is the accreditation-relevant record.

## Install

```bash
/var/www/azmsi-plugins/infra/deploy-symlinks.sh     # creates public/local/emdverify
# then, with maintenance mode + DB backup, on explicit go-ahead:
sudo -u www-data php /var/www/moodle/public/admin/cli/upgrade.php
```

Then set the endpoint and bearer token at
*Site administration → Plugins → Local plugins → eMD content verifier*, and seed
the corpus:

```bash
sudo -u www-data php cli/seed_corpus.php --add=Standard_anatomical_position
sudo -u www-data php cli/seed_corpus.php --add=Anatomical_plane
sudo -u www-data php cli/seed_corpus.php --ingest
```

## Settings that are not preferences

Every default under *Request tuning* was established by measuring the live
gateway on 2026-07-31. Changing one without re-measuring reintroduces a failure
that took a day to diagnose.

| Setting | Why it exists |
|---|---|
| `think: false` (hardcoded) | qwen3.5 is a thinking model — it returns text in a `thinking` field and leaves `response` an **empty string**. Without this the atomiser silently produces nothing. |
| `stream: true` (hardcoded) | The gateway is Cloudflare-fronted and returns **HTTP 524** when the origin takes >~100s to first byte. A cold load of the 34.6 GB adjudicator exceeds that. |
| abort-on-`done` (hardcoded) | The gateway holds the connection open after the terminal object. Reading to EOF blocks until timeout — measured **0.98s aborting vs a 9-minute hang**. |
| `num_predict` | A JSON schema constrains *shape*, not *length*. An unbounded `reasoning` field ran away, returned truncated JSON, and — because Ollama serialises per model and keeps generating after a client disconnects — **blocked every claim queued behind it**. |
| `num_ctx` | The box loads models with a 262144-token context by default: 55.4 GB resident across three models, forcing evictions between them. |
| Short `timeout` | A request can sit behind a busy model with zero bytes returned. Failing fast and retrying drains past the queue; a long timeout just blocks the run. |

The same bug the `think: false` flag guards against is latent in
`local_syllabusforge`'s `ollama_client.php`, which reads `message.content`. It
works today only because that plugin is configured for `qwen2.5:14b`; pointing
it at either qwen3.5 model breaks it silently.

## Two guarantees the design makes

**Nothing is cited that was not retrieved.** The adjudicator only ever sees
passages pulled from the local corpus, and a supporting quote is checked to be
≥20 characters and present verbatim in a cited source before it is shown as
trustworthy. Model-invented citations are the characteristic failure of this
kind of tool and are unacceptable in a medical context.

**No verdict without evidence.** If nothing clears the similarity floor, the
claim is reported as `needs_source` rather than judged on weak evidence.

## The `contested` verdict

Pass 1 shows the adjudicator all retrieved passages together. Only
`contradicted` and `partially_supported` escalate to pass 2, where each passage
is judged independently; disagreement downgrades the verdict to `contested`.

This exists because of a measured false positive. Judged against one passage, the
adjudicator flagged the EMD-102 line *"standing upright with feet together"* as
CONTRADICTED at **confidence 1.0**. The reference actually reads *"feet together
(or slightly separated)"* — the course was right and the machine was certain and
wrong. Model confidence is recorded in the UI for inspection but is deliberately
never used to rank, filter or gate anything.

## Corpus tiers

Sources carry a tier: 1 licensed textbook, 2 guideline body, 3 open reference.
A tier-3-only corpus can demonstrate that the pipeline behaves; it cannot settle
a clinical question. The UI shows a standing warning while the corpus contains
nothing above tier 3.

Live keyword search against third-party APIs is deliberately **not** a retrieval
channel. Measured: PubMed relevance-searching for foundational anatomy returned
CT-gantry papers, NCBI Bookshelf restricted to StatPearls returned a video
laryngoscopy article for an anatomical-position query, two of three claims
retrieved nothing, and NCBI then began returning HTTP 429.

## Not in this phase

Enrichment (comparison tables, Mermaid diagrams, 3D models), publish templates,
read-aloud, and follow-up questions are all out of scope here. They should be
built on top of a verifier that has earned its agreement number, not alongside
one that has not.
