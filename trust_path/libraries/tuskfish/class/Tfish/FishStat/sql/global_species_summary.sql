-- global_species_summary
--
-- Pre-aggregated global aquaculture production by species and year, holding both the volume
-- (tonnes) and value (USD) totals in one row per (period, species_code). It accelerates the
-- unfiltered "global" view of the species ranking charts on the /species/ page: aggregating
-- aquaculture_production live runs two GROUP BY scans of the ~2.5k-row (measure, period) slice and
-- recovers the year list with a ~97k-row DISTINCT scan, whereas reading this table is a small
-- indexed lookup per year. The /producers/ page also reads it — for its landing menu (biggest
-- species worldwide), its year list, and, via idx_gss_species_code (created at the foot of this
-- script), its species filter.
--
-- Only species_code is stored; the English / scientific names used as chart labels are joined
-- from the species table at read time, so label corrections take effect without a rebuild and
-- this table stays lean.
--
-- Country-filtered views do NOT use this table — they hit aquaculture_production directly via
-- idx_production_country_measure_period (a couple of ms per country) and so stay current
-- automatically.
--
-- This is a DERIVED table. It is NOT user data and must be rebuilt whenever aquaculture_production
-- changes (e.g. the annual FAO data refresh). Re-run this whole script against the database:
--
--     sqlite3 trust_path/database/aquaculture-fisheries-2026.db < global_species_summary.sql
--
-- The application degrades gracefully if this table is absent (it falls back to live
-- aggregation), so a forgotten rebuild is slow, not broken.
--
-- Values are stored as final integers matching the application's own conversion: volume is rounded
-- whole tonnes; value is rounded whole US dollars (aquaculture_production.value for the V_USD_1000
-- measure is in thousands of USD, hence the x1000). Rounding is applied after summing, mirroring
-- the live ranking path so the global and country-filtered figures agree methodologically.

DROP TABLE IF EXISTS global_species_summary;

CREATE TABLE global_species_summary (
    period INTEGER NOT NULL,
    species_code TEXT NOT NULL,
    volume_tonnes INTEGER NOT NULL DEFAULT 0,
    value_usd INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (period, species_code)
);

INSERT INTO global_species_summary (period, species_code, volume_tonnes, value_usd)
SELECT period,
       species_code,
       CAST(ROUND(SUM(CASE WHEN measure = 'Q_tlw'      THEN value ELSE 0 END))        AS INTEGER) AS volume_tonnes,
       CAST(ROUND(SUM(CASE WHEN measure = 'V_USD_1000' THEN value ELSE 0 END) * 1000) AS INTEGER) AS value_usd
FROM aquaculture_production
WHERE measure IN ('Q_tlw', 'V_USD_1000')
GROUP BY period, species_code;

-- Covering index for the /producers/ species filter, which needs the distinct set of species that
-- report production. The primary key is (period, species_code), so a DISTINCT over species_code
-- alone would full-scan the table and sort; this species_code-first index lets that resolve as a
-- covering-index scan instead (~16ms -> ~5ms on getSpeciesList()).
CREATE INDEX idx_gss_species_code ON global_species_summary(species_code);
