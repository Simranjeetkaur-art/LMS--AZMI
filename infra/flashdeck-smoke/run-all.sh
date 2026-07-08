#!/usr/bin/env bash
#
# run-all.sh — read-only smoke suite for mod_flashdeck.
#
# Bootstraps the LIVE Moodle in each script but writes nothing: no DB
# rows, no site caches (each script sets IGNORE_COMPONENT_CACHE and
# CACHE_DISABLE_ALL, so the running site is untouched). Safe to run on
# prod at any time, before or after the plugin is installed.
#
# Usage: infra/flashdeck-smoke/run-all.sh

set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

fail=0
for script in smoke1-skeleton.php smoke2-scheduler.php smoke3-cardtypes.php \
              smoke4-gamification.php smoke5-porter-backup.php smoke6-ai.php; do
  echo "=== ${script} ==="
  if ! php "${script}"; then
    fail=1
  fi
  echo
done

if [[ ${fail} -eq 0 ]]; then
  echo "ALL SMOKE SUITES PASSED"
else
  echo "SMOKE FAILURES — see above" >&2
fi
exit ${fail}
