#!/usr/bin/env bash
#
# rebuild.sh — rebuild ALL derived Stats summary tables (and their indexes) in one step.
#
# Run this after replacing or updating the Stats database (e.g. an annual FAO refresh).
# It re-runs every build script in this directory and then VERIFIES the result, so you see
# whether the rebuild actually worked instead of trusting that it did. See README.md for the
# why; this script is the "one command that tells you if it worked" version of that runbook.
#
# Usage:
#     ./rebuild.sh /path/to/aquaculture-fisheries-YYYY.db
#
# Exit status is 0 only if every script ran AND every verification check passed; non-zero
# otherwise (so it is safe to use in a deploy step / CI guard).

set -euo pipefail

# Resolve this script's own directory so the .sql files are found regardless of the caller's CWD.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

die() { printf 'ERROR: %s\n' "$1" >&2; exit 1; }

# --- Arguments & preconditions -------------------------------------------------------------

[ "$#" -eq 1 ] || die "usage: $(basename "$0") /path/to/stats.db"
DB="$1"

command -v sqlite3 >/dev/null 2>&1 || die "sqlite3 not found on PATH"
[ -f "$DB" ] || die "database not found: $DB"

# Every build script in this directory, run in this order. global_production_summary first
# because it is the one with no live fallback (see README.md) — most important to get right.
SCRIPTS=(
    global_production_summary.sql
    global_environment_summary.sql
    global_species_summary.sql
)

for s in "${SCRIPTS[@]}"; do
    [ -f "$SCRIPT_DIR/$s" ] || die "build script missing: $SCRIPT_DIR/$s"
done

# --- Run the build scripts -----------------------------------------------------------------

printf 'Rebuilding Stats summary tables in: %s\n\n' "$DB"

for s in "${SCRIPTS[@]}"; do
    printf '  running %s ... ' "$s"
    if sqlite3 "$DB" < "$SCRIPT_DIR/$s"; then
        printf 'ok\n'
    else
        printf 'FAILED\n'
        die "$s did not run cleanly — database may be partially rebuilt"
    fi
done

# --- Verify ---------------------------------------------------------------------------------
#
# For each derived table: it must be non-empty, and its newest year MAX(period) must equal the
# newest year in the SOURCE rows it is built from. A mismatch means the summary is stale or was
# built against the wrong database — exactly the "plausible but wrong" failure this guards against.

sq() { sqlite3 -noheader -batch "$DB" "$1"; }

# Source max-year expressions (must mirror the WHERE clauses in the .sql build scripts).
SRC_AQUA_MAX="SELECT MAX(period) FROM aquaculture_production WHERE measure IN ('Q_tlw','V_USD_1000')"
SRC_PROD_MAX="SELECT MAX(p) FROM (
    SELECT MAX(period) p FROM capture_production WHERE measure='Q_tlw'
    UNION ALL
    SELECT MAX(period)   FROM aquaculture_production WHERE measure IN ('Q_tlw','V_USD_1000'))"

# table | source-max-year query
CHECKS=(
    "global_production_summary|$SRC_PROD_MAX"
    "global_environment_summary|$SRC_AQUA_MAX"
    "global_species_summary|$SRC_AQUA_MAX"
)

printf '\nVerifying:\n'
printf '  %-28s %8s %12s %12s   %s\n' "table" "rows" "summary_yr" "source_yr" "status"

fail=0
for entry in "${CHECKS[@]}"; do
    tbl="${entry%%|*}"
    src_q="${entry#*|}"

    rows="$(sq "SELECT COUNT(*) FROM $tbl;")"
    sum_yr="$(sq "SELECT MAX(period) FROM $tbl;")"
    src_yr="$(sq "$src_q;")"

    status="ok"
    if [ "${rows:-0}" -eq 0 ]; then
        status="FAIL (empty)"; fail=1
    elif [ "${sum_yr:-x}" != "${src_yr:-y}" ]; then
        status="FAIL (stale: summary $sum_yr != source $src_yr)"; fail=1
    fi

    printf '  %-28s %8s %12s %12s   %s\n' "$tbl" "$rows" "${sum_yr:-NULL}" "${src_yr:-NULL}" "$status"
done

# The covering index that backs the /production/ species filter is built inside
# global_species_summary.sql; confirm it survived the rebuild.
idx="$(sq "SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name='idx_gss_species_code';")"
if [ "${idx:-0}" -eq 1 ]; then
    printf '  %-28s %8s %12s %12s   %s\n' "idx_gss_species_code" "-" "-" "-" "ok (present)"
else
    printf '  %-28s %8s %12s %12s   %s\n' "idx_gss_species_code" "-" "-" "-" "FAIL (missing)"
    fail=1
fi

printf '\n'
if [ "$fail" -ne 0 ]; then
    die "verification failed — do NOT trust the global charts until this is resolved"
fi

printf 'All summary tables rebuilt and verified. ✓\n'
