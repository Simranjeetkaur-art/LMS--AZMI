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
working tree stays clean and a core update never touches them. From the Moodle
root (`/var/www/moodle`):

```bash
ln -s /var/www/azmsi-plugins/theme/azmsi            public/theme/azmsi
ln -s /var/www/azmsi-plugins/local/azmsi            public/local/azmsi
ln -s /var/www/azmsi-plugins/course/format/emd      public/course/format/emd
ln -s /var/www/azmsi-plugins/blocks/azmsi_dashboard public/blocks/azmsi_dashboard
```

The symlinks themselves are added to `.git/info/exclude` in the Moodle repo so
they show as neither tracked nor untracked, and `git clean -fd` will not remove
them. Re-run the commands above if the links are ever lost.

The web server (`www-data`) must be allowed to follow symlinks (Apache
`FollowSymLinks`, the default) and read this directory (owned `ubuntu:www-data`,
group-readable; it is intentionally outside any web docroot).

## Install

These are **not** installed until a site admin runs the Moodle upgrade
(`php public/admin/cli/upgrade.php` or Site admin → Notifications). Do this on a
**staging clone first**, never directly on production.
