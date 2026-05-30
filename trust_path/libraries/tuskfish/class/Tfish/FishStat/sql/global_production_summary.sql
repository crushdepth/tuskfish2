-- global_production_summary
--
-- Pre-aggregated global production totals by year: capture tonnes, aquaculture tonnes, and
-- aquaculture value, one row per period. It backs the headline capture-vs-aquaculture time series
-- on the FishStat landing page (\Tfish\FishStat\Model\Listing::getGlobalSummary), collapsing a
-- full-history scan of BOTH the capture_production and aquaculture_production tables into a ~75-row
-- lookup.
--
-- NOTE — this summary is read with NO live fallback. The species/country views in Listing.php
-- aggregate the source tables directly (always current), but the global, no-filter view reads ONLY
-- this table. So unlike global_species_summary / global_environment_summary, a MISSING table here
-- does not degrade to slow-but-correct — the global chart query simply finds nothing. And a STALE
-- table shows last release's global totals while every drill-down shows the new ones. Rebuild it.
--
-- Derived from TWO source tables: capture_production (capture_tonnes) and aquaculture_production
-- (aquaculture_tonnes + aquaculture_value_usd). The period spine is the union of the years present
-- in either source, so a year reported by only one side still appears (zero-filled on the other).
--
-- UNITS — stored as final integers matching the application's own conversion and the live path in
-- Listing.php (CAST(SUM(...)) — truncated, not rounded):
--   * capture_tonnes        whole tonnes (capture_production Q_tlw)
--   * aquaculture_tonnes    whole tonnes (aquaculture_production Q_tlw)
--   * aquaculture_value_usd despite the name, THOUSANDS of US dollars — aquaculture_production's
--                           V_USD_1000 measure is already in thousands and is summed as-is (the x1000
--                           scale-up to whole dollars is NOT applied here; the chart consumes the
--                           thousands value directly, exactly as the per-species/per-country paths do).
--
-- This is a DERIVED table. It is NOT user data and must be rebuilt whenever capture_production or
-- aquaculture_production changes (e.g. the annual FAO data refresh). Re-run this whole script:
--
--     sqlite3 trust_path/database/aquaculture-fisheries-2026.db < global_production_summary.sql

DROP TABLE IF EXISTS global_production_summary;

CREATE TABLE global_production_summary (
    period INTEGER NOT NULL,
    capture_tonnes INTEGER NOT NULL DEFAULT 0,
    aquaculture_tonnes INTEGER NOT NULL DEFAULT 0,
    aquaculture_value_usd INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (period)
);

INSERT INTO global_production_summary (period, capture_tonnes, aquaculture_tonnes, aquaculture_value_usd)
SELECT y.period,
       COALESCE(cap.capture_tonnes, 0),
       COALESCE(aq.aquaculture_tonnes, 0),
       COALESCE(aq.aquaculture_value_usd, 0)
FROM (
    SELECT DISTINCT period FROM capture_production WHERE measure = 'Q_tlw'
    UNION
    SELECT DISTINCT period FROM aquaculture_production WHERE measure IN ('Q_tlw', 'V_USD_1000')
) y
LEFT JOIN (
    SELECT period, CAST(SUM(value) AS INTEGER) AS capture_tonnes
    FROM capture_production
    WHERE measure = 'Q_tlw'
    GROUP BY period
) cap ON cap.period = y.period
LEFT JOIN (
    SELECT period,
           CAST(SUM(CASE WHEN measure = 'Q_tlw'      THEN value ELSE 0 END) AS INTEGER) AS aquaculture_tonnes,
           CAST(SUM(CASE WHEN measure = 'V_USD_1000' THEN value ELSE 0 END) AS INTEGER) AS aquaculture_value_usd
    FROM aquaculture_production
    WHERE measure IN ('Q_tlw', 'V_USD_1000')
    GROUP BY period
) aq ON aq.period = y.period;
