# FishStat data refresh runbook

The FishStat pages read a separate, read-only SQLite database (`TFISH_FISHSTAT_DB`, currently
`aquaculture-fisheries-2026.db`) built externally from an FAO **FishStatJ** export. New FAO releases
arrive on **no fixed schedule**, so this is a manual, occasional process — which makes the step
below easy to forget. Don't.

## After rebuilding the FishStat database, ALWAYS re-run BOTH summary scripts

The `.sql` files in this directory build **derived** tables — pre-aggregated lookups that exist only
to keep the "global" (unfiltered) chart views fast. They are NOT source data and are NOT rebuilt
automatically. The moment you replace or update the FishStat database, they are stale until you
re-run them:

```sh
sqlite3 trust_path/database/<the-new-fishstat>.db < global_environment_summary.sql
sqlite3 trust_path/database/<the-new-fishstat>.db < global_species_summary.sql
```

(Substitute the actual database filename — it carries the FAO release year, so it changes on a
refresh.)

## Why this matters

Each page serves two paths:

- **Global / unfiltered** views read the summary tables (fast indexed lookups).
- **Country-filtered** views aggregate the production table **live** (always current).

If you refresh the database but skip the rebuild, the two paths disagree: country views show the
**new** figures while global views show the **old** ones. The application does not crash and the
pages do not look broken — they show *plausible but wrong* global numbers next to correct country
numbers. That is exactly the kind of error that survives unnoticed until the next release. The only
safeguard is running the two commands above.

## If a summary table is missing entirely

The application degrades gracefully: with no summary table present, the global view falls back to
the same live aggregation used for country views. The result is correct but slower. So a *forgotten*
rebuild after a refresh is the dangerous case (silently stale); a *deleted* table is merely slow.

## The tables

| Script | Builds | Feeds the global view on |
| --- | --- | --- |
| `global_environment_summary.sql` | `global_environment_summary` (production by environment × year) | `/environment/` |
| `global_species_summary.sql` | `global_species_summary` (production by species × year) | `/species/` |

Both store final integer units (whole tonnes / whole US dollars) and are documented in detail in
their own header comments.
