-- country_trade_summary
--
-- Pre-aggregated PER-COUNTRY trade totals by year and flow: quantity (tonnes product weight) and
-- value (US dollars), one row per (country_code, period, trade flow). It backs the trade time series
-- on the Stats /trade/ page when a single member state is selected
-- (\Tfish\Stats\Model\Trade::buildPayload, country branch).
--
-- WHY THIS EXISTS — the country branch previously read the v_trade_country_yearly VIEW live. That
-- view joins trade -> trade_flows -> countries and GROUPs BY (period, flow, country) over every raw
-- trade row for the country (e.g. ~27k rows / 395 commodities for a big reporter), through temp
-- B-trees for both the GROUP BY and ORDER BY — ~60 ms per request versus ~1 ms here, and ~15x the
-- global path. The flow filter does not help the view (it aggregates all flows first, then discards
-- R/P), so the only fix is to move the aggregation to build time, exactly as global_trade_summary
-- does for the unfiltered picture. Model::buildPayload reads this table when present and FALLS BACK
-- to v_trade_country_yearly when absent, so a missing table degrades to slow-but-correct.
--
-- All four trade flows are materialised (I/E/R and P = processed production); the application reads
-- only imports (I) and exports (E) — reexports (R) are excluded because the source figures are
-- unreliable, and P is unused — but keeping every flow here mirrors global_trade_summary and lets
-- reexports be re-enabled in code alone (no rebuild) if the data ever improves.
--
-- UNITS — identical to global_trade_summary / the v_trade_country_yearly view:
--   * quantity_tonnes_pw   whole tonnes product weight (trade Q_tpw, summed as-is)
--   * value_usd            whole US dollars — trade's V_USD_1000 measure is in THOUSANDS, so it is
--                          scaled x1000 here, exactly as the view does (value * 1000).
--
-- This is a DERIVED table. It is NOT user data and must be rebuilt whenever the trade table changes
-- (e.g. the annual FAO data refresh). Re-run this whole script:
--
--     sqlite3 trust_path/database/aquaculture-fisheries-trade.db < country_trade_summary.sql

DROP TABLE IF EXISTS country_trade_summary;

CREATE TABLE country_trade_summary (
    country_code TEXT NOT NULL,
    period INTEGER NOT NULL,
    flow_code TEXT NOT NULL,
    quantity_tonnes_pw INTEGER NOT NULL DEFAULT 0,
    value_usd INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (country_code, period, flow_code)
);

INSERT INTO country_trade_summary (country_code, period, flow_code, quantity_tonnes_pw, value_usd)
SELECT country_code,
       period,
       trade_flow_code,
       CAST(SUM(CASE WHEN measure = 'Q_tpw'      THEN value        ELSE 0 END) AS INTEGER),
       CAST(SUM(CASE WHEN measure = 'V_USD_1000' THEN value * 1000 ELSE 0 END) AS INTEGER)
FROM trade
GROUP BY country_code, period, trade_flow_code;
