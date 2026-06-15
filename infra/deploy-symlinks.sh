#!/usr/bin/env bash
#
# deploy-symlinks.sh — deploy the azmsi-plugins into a Moodle public/ tree.
#
# Idempotent and safe to re-run. It does two things:
#   1. (Re)creates the four plugin symlinks under <moodle>/public/...
#   2. (Re)adds those symlink paths to <moodle>/.git/info/exclude so the
#      Moodle CORE repo never tracks them and `git clean -fd` never removes
#      them. This is the piece that survives a core re-clone — info/exclude is
#      local-only, so this script is the single source of truth for it.
#
# It NEVER mutates the running site (no DB, no upgrade.php, no web services).
# Installing the plugins is a separate, explicit step (see README "Install").
#
# Usage:
#   infra/deploy-symlinks.sh [MOODLE_ROOT]
#
#   MOODLE_ROOT defaults to /var/www/moodle.
#
# Exit codes: 0 = all links + excludes in place, non-zero = something is wrong.

set -euo pipefail

# Resolve this repo's absolute root (parent of the dir holding this script).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGINS_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

MOODLE_ROOT="${1:-/var/www/moodle}"
PUBLIC="${MOODLE_ROOT}/public"

# component repo path  ->  path under public/
# (associative arrays keep source-of-truth in one place)
declare -A LINKS=(
  ["theme/azmsi"]="theme/azmsi"
  ["local/azmsi"]="local/azmsi"
  ["course/format/emd"]="course/format/emd"
  ["blocks/azmsi_dashboard"]="blocks/azmsi_dashboard"
)

log()  { printf '  %s\n' "$*"; }
ok()   { printf '  \033[32m✓\033[0m %s\n' "$*"; }
warn() { printf '  \033[33m!\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

[[ -d "${MOODLE_ROOT}" ]]            || die "Moodle root not found: ${MOODLE_ROOT}"
[[ -d "${PUBLIC}" ]]                 || die "No public/ dir at ${PUBLIC} (is this a public/-layout Moodle?)"
[[ -d "${MOODLE_ROOT}/.git" ]]       || warn "${MOODLE_ROOT}/.git not found — skipping core .git/info/exclude step"

echo "Deploying azmsi-plugins"
echo "  from : ${PLUGINS_ROOT}"
echo "  into : ${PUBLIC}"
echo

# --- 1. symlinks ----------------------------------------------------------
for src in "${!LINKS[@]}"; do
  target="${PLUGINS_ROOT}/${src}"
  link="${PUBLIC}/${LINKS[$src]}"

  [[ -d "${target}" ]] || die "Source component missing: ${target}"

  # Make sure the parent dir of the link exists (e.g. public/course/format).
  mkdir -p "$(dirname "${link}")"

  if [[ -L "${link}" ]]; then
    current="$(readlink "${link}")"
    if [[ "${current}" == "${target}" ]]; then
      ok "link ok        public/${LINKS[$src]}"
      continue
    fi
    warn "relinking (was -> ${current})"
    rm "${link}"
  elif [[ -e "${link}" ]]; then
    die "public/${LINKS[$src]} exists and is NOT a symlink — refusing to overwrite a real path"
  fi

  ln -s "${target}" "${link}"
  ok "linked         public/${LINKS[$src]} -> ${target}"
done

# --- 2. core .git/info/exclude -------------------------------------------
if [[ -d "${MOODLE_ROOT}/.git" ]]; then
  echo
  echo "Ensuring core .git/info/exclude entries"
  exclude_file="${MOODLE_ROOT}/.git/info/exclude"
  mkdir -p "$(dirname "${exclude_file}")"
  touch "${exclude_file}"

  marker="# --- azmsi-plugins (managed by infra/deploy-symlinks.sh) ---"
  if ! grep -qF "${marker}" "${exclude_file}"; then
    {
      echo ""
      echo "${marker}"
    } >> "${exclude_file}"
  fi

  for src in "${!LINKS[@]}"; do
    entry="/public/${LINKS[$src]}"
    if grep -qxF "${entry}" "${exclude_file}"; then
      ok "exclude ok     ${entry}"
    else
      echo "${entry}" >> "${exclude_file}"
      ok "exclude added  ${entry}"
    fi
  done
fi

echo
ok "Deploy complete. Run infra/verify-deploy.sh to confirm Moodle sees the plugins."
