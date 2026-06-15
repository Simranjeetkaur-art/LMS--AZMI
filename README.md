# azmsi-plugins

Custom Moodle plugins for the **AZMSI platform** (Arizona Medical Sciences Institute),
kept in their own repository so they survive Moodle core updates and `git clean`.

Target: **Moodle 5.1** (branch 501), `public/` directory layout, PHP 8.x.
See the design handoff (`AGENT_00` + `AGENT_00a`) for conventions.

## Contents

| Path in this repo | Moodle component | Deploys to |
|---|---|---|
| `theme/azmsi` | `theme_azmsi` (Moove child) | `public/theme/azmsi` |
| `local/azmsi` | `local_azmsi` (WS, events, DB, tasks) | `public/local/azmsi` |
| `course/format/emd` | `format_emd` (weekly master template) | `public/course/format/emd` |
| `blocks/azmsi_dashboard` | `block_azmsi_dashboard` | `public/blocks/azmsi_dashboard` |

> Status: **alpha skeletons** (`0.1.0-skeleton`). No business logic yet — every
> external function returns an empty structure and observers/tasks are no-ops.

## Deploy (symlink) step

The plugins live here and are symlinked into the Moodle tree, so the core repo
working tree stays clean and a core update never touches them. **Do not create
the links by hand** — use the idempotent, re-runnable deploy script, which is the
single source of truth for both the symlinks *and* the core `.git/info/exclude`
entries:

```bash
# defaults to MOODLE_ROOT=/var/www/moodle (pass a different root as $1)
/var/www/azmsi-plugins/infra/deploy-symlinks.sh     # (re)create links + excludes
/var/www/azmsi-plugins/infra/verify-deploy.sh       # read-only health check
```

`deploy-symlinks.sh` is safe to run any number of times: it creates each of the
four symlinks (relinking if a target moved), refuses to clobber a real
non-symlink path, and re-adds the four paths to the Moodle repo's
`.git/info/exclude`. Because `info/exclude` is **local-only** (not versioned, and
lost on a fresh `git clone` of Moodle core), re-running this script is exactly how
you restore a correct, conflict-free deployment after a core re-clone. Neither
script ever mutates the running site (no DB, no `upgrade.php`, no web services).

The web server (`www-data`) must be allowed to follow symlinks (Apache
`FollowSymLinks`, the default) and read this directory (owned `ubuntu:www-data`,
group-readable; it is intentionally outside any web docroot).

## `infra/`

Deployment & ops tooling that travels with the plugins (versioned here, not in
the Moodle core repo, so it survives core updates):

| File | Purpose |
|---|---|
| `infra/deploy-symlinks.sh` | Idempotent deploy: symlinks + core `.git/info/exclude`. |
| `infra/verify-deploy.sh` | Read-only health check of the deployment. |
| `infra/MOOVE_NOTES.md` | Inventory of installed Moove 5.1.2 (parents, layouts, SCSS callbacks) — Agent 02's reference for overriding Moove cleanly. |

CI lives in `.github/workflows/ci.yml` (moodle-plugin-ci matrix: four plugins ×
Moodle branch 501 × PHP 8.4/8.5).

## Install

These are **not** installed until a site admin runs the Moodle upgrade
(`php public/admin/cli/upgrade.php` or Site admin → Notifications). Do this on a
**staging clone first**, never directly on production.
