-- global_trade_summary
--
-- Pre-aggregated global trade totals by year and flow: quantity (tonnes product weight) and value
-- (US dollars), one row per (period, trade flow). It backs the headline trade time series on the
-- Stats /trade/ page (\Tfish\Stats\Model\Trade::buildPayload, global branch), collapsing a
-- full-history scan of the ~2.5M-row trade table into a ~190-row lookup.
--
-- WHY THIS EXISTS — the live global aggregation (SELECT ... FROM trade GROUP BY period,
-- trade_flow_code, or equivalently the v_trade_global_yearly view) takes ~7 seconds: there is no
-- index supporting a GROUP BY on (period, trade_flow_code), so SQLite scans every trade row. The
-- per-COUNTRY views are fast (idx_trade_country narrows the slice), but the global, no-filter view
-- is not. This table moves that one expensive aggregation to build time. Model::buildPayload reads
-- it when present and FALLS BACK to v_trade_global_yearly when absent, so a missing table degrades
-- to slow-but-correct (like global_environment_summary / global_species_summary, not like
-- global_production_summary which has no fallback).
--
-- All four trade flows are materialised (I/E/R and P = processed production); the application reads
-- only imports (I) and exports (E) — reexports (R) are excluded because the source figures are
-- unreliable, and P is unused — but keeping every flow here makes this table a faithful image of the
-- source and lets reexports be re-enabled in code alone (no rebuild) if the data ever improves.
--
-- UNITS — stored as final integers matching the v_trade_global_yearly view the live path uses:
--   * quantity_tonnes_pw   whole tonnes product weight (trade Q_tpw, summed as-is)
--   * value_usd            whole US dollars — trade's V_USD_1000 measure is in THOUSANDS, so it is
--                          scaled x1000 here, exactly as the view does (value * 1000).
--
-- This is a DERIVED table. It is NOT user data and must be rebuilt whenever the trade table changes
-- (e.g. the annual FAO data refresh). Re-run this whole script:
--
--     sqlite3 trust_path/database/aquaculture-fisheries-trade.db < global_trade_summary.sql

DROP TABLE IF EXISTS global_trade_summary;

CREATE TABLE global_trade_summary (
    period INTEGER NOT NULL,
    flow_code TEXT NOT NULL,
    quantity_tonnes_pw INTEGER NOT NULL DEFAULT 0,
    value_usd INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (period, flow_code)
);

INSERT INTO global_trade_summary (period, flow_code, quantity_tonnes_pw, value_usd)
SELECT period,
       trade_flow_code,
       CAST(SUM(CASE WHEN measure = 'Q_tpw'      THEN value        ELSE 0 END) AS INTEGER),
       CAST(SUM(CASE WHEN measure = 'V_USD_1000' THEN value * 1000 ELSE 0 END) AS INTEGER)
FROM trade
GROUP BY period, trade_flow_code;
