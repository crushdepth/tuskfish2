# Stats data refresh runbook

The Stats pages read a separate, read-only SQLite database (`TFISH_STATS_DB`, currently
`aquaculture-fisheries-trade.db`) built externally from an FAO **FishStatJ** export. New FAO releases
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

If you can't run the wrapper, run the scripts yourself — all five, in any order:

```sh
sqlite3 trust_path/database/<the-new-stats>.db < global_production_summary.sql
sqlite3 trust_path/database/<the-new-stats>.db < global_environment_summary.sql
sqlite3 trust_path/database/<the-new-stats>.db < global_species_summary.sql
sqlite3 trust_path/database/<the-new-stats>.db < global_trade_summary.sql
sqlite3 trust_path/database/<the-new-stats>.db < country_trade_summary.sql
```

Each script is self-contained and idempotent: it `DROP`s and recreates its own table (and any index
on it), so the order does not matter and re-running is safe. If in doubt, run all five. The wrapper
is preferred only because it also *checks* the outcome.

## Why this matters

Each page serves two paths:

- **Global / unfiltered** views read the summary tables (fast indexed lookups).
- **Country-filtered** views aggregate the source table **live** (always current) — *except trade*,
  whose country view also reads a summary table (`country_trade_summary`), because live aggregation
  of `v_trade_country_yearly` runs ~60 ms per request. So trade is stale on **both** paths if you
  skip the rebuild; the other pages are stale only on the global path.

If you refresh the database but skip the rebuild, the two paths disagree: country views show the
**new** figures while global views show the **old** ones (and for trade, both show the old ones). The application does not crash and the
pages do not look broken — they show *plausible but wrong* global numbers next to correct country
numbers. That is exactly the kind of error that survives unnoticed until the next release. The
safeguard is running `rebuild.sh` (or the three commands) above — and `rebuild.sh` additionally
*verifies* it actually took, which prose-followed-by-hand commands cannot.

## If a summary table is missing entirely

For `global_environment_summary`, `global_species_summary` and `global_trade_summary` the application
degrades gracefully: with the table absent, the global view falls back to the same live aggregation
used for country views — correct but slower. So for these three a *forgotten* rebuild after a refresh
is the dangerous case (silently stale); a *deleted* table is merely slow. `global_trade_summary` is
the one to watch on the slow side: its fallback (`v_trade_global_yearly`, a full GROUP BY over the
~2.5M-row trade table) takes ~7 seconds versus ~4 ms with the table present, so a missing trade
summary is *correct but painfully slow*, not just a little slower. `country_trade_summary` behaves
the same way: absent, the country trade view falls back to `v_trade_country_yearly` (~60 ms live
aggregation versus ~1 ms with the table) — correct, just slower per country selection.

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
| `global_trade_summary.sql` | `global_trade_summary` (trade volume + value by flow × year) | `/trade/` — falls back to `v_trade_global_yearly`, but that fallback is ~7 s |
| `country_trade_summary.sql` | `country_trade_summary` (trade volume + value by country × flow × year) | `/trade/?country=…` — falls back to `v_trade_country_yearly` (~60 ms/request) |

All store final integer units (whole tonnes / whole US dollars) and are documented in detail in
their own header comments. Note `global_production_summary` is derived from BOTH `capture_production`
and `aquaculture_production` (the other three come from a single source table —
`aquaculture_production` for environment/species, `trade` for trade).

`global_species_summary.sql` also creates the covering index `idx_gss_species_code` at the end of
the script, so re-running the script rebuilds the index along with the table — there is no separate
step to remember. The index backs the `/production/` species filter (`getSpeciesList()`), turning a
full scan of every species into a covering-index scan of the ~770 that actually report production.
A missing index still returns the correct list, just more slowly — so a stripped index is slow, not
broken — but don't strip it.
