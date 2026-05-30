# Stats data refresh runbook

The Stats pages read a separate, read-only SQLite database (`TFISH_STATS_DB`, currently
`aquaculture-fisheries-2026.db`) built externally from an FAO **FishStatJ** export. New FAO releases
arrive on **no fixed schedule**, so this is a manual, occasional process — which makes the step
below easy to forget. Don't.

## After rebuilding the Stats database, ALWAYS rebuild the derived tables

The `.sql` files in this directory build **derived** tables — pre-aggregated lookups that exist only
to keep the "global" (unfiltered) chart views fast. They are NOT source data and are NOT rebuilt
automatically. The moment you replace or update the Stats database, they are stale until you
re-run them.

### Do this (one command, self-verifying)

```sh
./rebuild.sh trust_path/database/<the-new-stats>.db
```

`rebuild.sh` runs all the build scripts **and then verifies** the result: every summary must be
non-empty and its newest year must match the newest year in the source data, and the covering index
must be present. It exits non-zero — and tells you which check failed — if anything is wrong, so you
find out at rebuild time instead of from a wrong chart weeks later. Run it from anywhere; it finds
its own scripts. (Substitute the actual database filename — it carries the FAO release year, so it
changes on a refresh.)

### Or, by hand (equivalent)

If you can't run the wrapper, run the scripts yourself — all three, in any order:

```sh
sqlite3 trust_path/database/<the-new-stats>.db < global_production_summary.sql
sqlite3 trust_path/database/<the-new-stats>.db < global_environment_summary.sql
sqlite3 trust_path/database/<the-new-stats>.db < global_species_summary.sql
```

Each script is self-contained and idempotent: it `DROP`s and recreates its own table (and any index
on it), so the order does not matter and re-running is safe. If in doubt, run all three. The wrapper
is preferred only because it also *checks* the outcome.

## Why this matters

Each page serves two paths:

- **Global / unfiltered** views read the summary tables (fast indexed lookups).
- **Country-filtered** views aggregate the production table **live** (always current).

If you refresh the database but skip the rebuild, the two paths disagree: country views show the
**new** figures while global views show the **old** ones. The application does not crash and the
pages do not look broken — they show *plausible but wrong* global numbers next to correct country
numbers. That is exactly the kind of error that survives unnoticed until the next release. The
safeguard is running `rebuild.sh` (or the three commands) above — and `rebuild.sh` additionally
*verifies* it actually took, which prose-followed-by-hand commands cannot.

## If a summary table is missing entirely

For `global_environment_summary` and `global_species_summary` the application degrades gracefully:
with the table absent, the global view falls back to the same live aggregation used for country
views — correct but slower. So for these two a *forgotten* rebuild after a refresh is the dangerous
case (silently stale); a *deleted* table is merely slow.

**`global_production_summary` is the exception: it has NO live fallback.** The headline
capture-vs-aquaculture chart (`Listing::getGlobalSummary`) reads only this table. If it is stale the
landing chart shows last release's totals; if it is missing the global chart has no data at all.
Either way there is no safety net but this runbook — so this is the script you most cannot skip.

## The tables

| Script | Builds | Feeds the global view on |
| --- | --- | --- |
| `global_production_summary.sql` | `global_production_summary` (capture + aquaculture totals × year) | Stats landing chart (`/`) — **no live fallback** |
| `global_environment_summary.sql` | `global_environment_summary` (production by environment × year) | `/environment/` |
| `global_species_summary.sql` | `global_species_summary` (production by species × year) **and `idx_gss_species_code`** | `/species/`, `/production/` |

All store final integer units (whole tonnes / whole US dollars) and are documented in detail in
their own header comments. Note `global_production_summary` is derived from BOTH `capture_production`
and `aquaculture_production` (the other two come from `aquaculture_production` alone).

`global_species_summary.sql` also creates the covering index `idx_gss_species_code` at the end of
the script, so re-running the script rebuilds the index along with the table — there is no separate
step to remember. The index backs the `/production/` species filter (`getSpeciesList()`), turning a
full scan of every species into a covering-index scan of the ~770 that actually report production.
A missing index still returns the correct list, just more slowly — so a stripped index is slow, not
broken — but don't strip it.
