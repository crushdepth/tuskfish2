-- global_environment_summary
--
-- Pre-aggregated global aquaculture production by environment and year, holding both the
-- volume (tonnes) and value (USD) totals in one row per (period, environment_code). It exists
-- purely to accelerate the unfiltered "global" view of the environment time-series charts on
-- the /environment/ page: aggregating the ~107k volume / ~86k value rows of aquaculture_production
-- live costs ~240 ms / ~96 ms respectively, whereas reading this table is a ~225-row scan.
--
-- Country-filtered views do NOT use this table — they hit aquaculture_production directly via
-- idx_production_country_measure_period (a few ms per country) and so stay current automatically.
--
-- This is a DERIVED table. It is NOT user data and must be rebuilt whenever aquaculture_production
-- changes (e.g. the annual FAO data refresh). Re-run this whole script against the database:
--
--     sqlite3 trust_path/database/aquaculture-fisheries-trade.db < global_environment_summary.sql
--
-- The application degrades gracefully if this table is absent (it falls back to live
-- aggregation), so a forgotten rebuild is slow, not broken.
--
-- Value is stored in whole US dollars. aquaculture_production.value for the V_USD_1000 measure is
-- in thousands of USD; the ×1000 below (matching the application's own conversion) yields dollars.

DROP TABLE IF EXISTS global_environment_summary;

CREATE TABLE global_environment_summary (
    period INTEGER NOT NULL,
    environment_code TEXT NOT NULL,
    volume_tonnes INTEGER NOT NULL DEFAULT 0,
    value_usd INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (period, environment_code)
);

INSERT INTO global_environment_summary (period, environment_code, volume_tonnes, value_usd)
SELECT period,
       environment_code,
       CAST(SUM(CASE WHEN measure = 'Q_tlw'      THEN value ELSE 0 END) AS INTEGER)        AS volume_tonnes,
       CAST(SUM(CASE WHEN measure = 'V_USD_1000' THEN value ELSE 0 END) AS INTEGER) * 1000 AS value_usd
FROM aquaculture_production
WHERE measure IN ('Q_tlw', 'V_USD_1000')
GROUP BY period, environment_code;
