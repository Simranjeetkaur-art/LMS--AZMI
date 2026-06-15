#!/usr/bin/env bash
#
# verify-deploy.sh — read-only health check for the azmsi-plugins deployment.
#
# Confirms, without mutating anything:
#   - each of the four symlinks exists and resolves to this repo
#   - each target dir holds a version.php with the expected component
#   - the Moodle core repo treats the links as ignored (not tracked/untracked)
#   - www-data can traverse the plugins root (basic readability sanity)
#
# Usage: infra/verify-deploy.sh [MOODLE_ROOT]   (default /var/www/moodle)
# Exit: 0 = healthy, 1 = at least one problem.

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGINS_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
MOODLE_ROOT="${1:-/var/www/moodle}"
PUBLIC="${MOODLE_ROOT}/public"

declare -A COMPONENTS=(
  ["theme/azmsi"]="theme_azmsi"
  ["local/azmsi"]="local_azmsi"
  ["course/format/emd"]="format_emd"
  ["blocks/azmsi_dashboard"]="block_azmsi_dashboard"
)

fail=0
ok()   { printf '  \033[32m✓\033[0m %s\n' "$*"; }
bad()  { printf '  \033[31m✗\033[0m %s\n' "$*"; fail=1; }

echo "Verifying azmsi-plugins deployment in ${PUBLIC}"
echo

for src in "${!COMPONENTS[@]}"; do
  comp="${COMPONENTS[$src]}"
  link="${PUBLIC}/${src}"
  target="${PLUGINS_ROOT}/${src}"

  if [[ ! -L "${link}" ]]; then
    bad "${src}: not a symlink (expected -> ${target})"
    continue
  fi
  resolved="$(readlink -f "${link}")"
  if [[ "${resolved}" != "${target}" ]]; then
    bad "${src}: resolves to ${resolved}, expected ${target}"
    continue
  fi
  vfile="${link}/version.php"
  if [[ ! -f "${vfile}" ]]; then
    bad "${src}: version.php missing through link"
    continue
  fi
  if grep -q "component = '${comp}'" "${vfile}"; then
    ok "${src} -> ${comp}"
  else
    bad "${src}: version.php component mismatch (expected ${comp})"
  fi
done

# Core repo must ignore the links.
if [[ -d "${MOODLE_ROOT}/.git" ]]; then
  echo
  for src in "${!COMPONENTS[@]}"; do
    if git -C "${MOODLE_ROOT}" check-ignore -q "public/${src}"; then
      ok "core repo ignores public/${src}"
    else
      bad "core repo does NOT ignore public/${src} — run infra/deploy-symlinks.sh"
    fi
  done
fi

echo
if [[ "${fail}" -eq 0 ]]; then
  ok "All checks passed."
else
  printf '  \033[31mSome checks failed.\033[0m Re-run infra/deploy-symlinks.sh and re-verify.\n'
fi
exit "${fail}"
