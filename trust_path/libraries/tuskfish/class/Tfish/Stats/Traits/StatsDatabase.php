<?php

declare(strict_types=1);

namespace Tfish\Stats\Traits;

/**
 * \Tfish\Stats\Traits\StatsDatabase trait file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 */

/**
 * Shared connection and lookup helpers for the Stats statistical database.
 *
 * Provides a read-only PDO connection to the Stats SQLite database (separate from the main
 * Tuskfish site database) along with country lookup helpers reused by Stats models.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 * @var         ?\PDO $statsDb Connection to the Stats statistical database.
 * @uses        \Tfish\Logger $logger Host class must provide a logger property.
 */
trait StatsDatabase
{
    private ?\PDO $statsDb = null;
    private array $summaryTableExists = [];

    /**
     * Open a read-only connection to the Stats statistical database.
     *
     * @return  bool True on success, false on failure.
     */
    public function connect(): bool
    {
        $dbPath = TFISH_DATABASE_PATH . TFISH_STATS_DB;

        if (!\is_file($dbPath)) {
            $this->logger->logError(0, 'Stats database not found: ' . $dbPath, __FILE__, __LINE__);
            return false;
        }

        try {
            // Open read-only where the driver supports it: this database is reference data the
            // site only ever reads, so the connection should be incapable of writing it even if
            // a future query bug tried to. PHP 8.5 moved these constants onto the Pdo\Sqlite class
            // and deprecated the old PDO::SQLITE_* spellings; prefer the new ones where present and
            // fall back to the old ones, so the code is correct across 7.3–8.4 and 8.5+ alike. If
            // neither is defined (an older/!sqlite driver) the connection simply opens read-write.
            $options = [];

            if (\defined('Pdo\Sqlite::ATTR_OPEN_FLAGS')) {
                $options[\Pdo\Sqlite::ATTR_OPEN_FLAGS] = \Pdo\Sqlite::OPEN_READONLY;
            } elseif (\defined('PDO::SQLITE_ATTR_OPEN_FLAGS')) {
                $options[\PDO::SQLITE_ATTR_OPEN_FLAGS] = \PDO::SQLITE_OPEN_READONLY;
            }

            $this->statsDb = new \PDO('sqlite:' . $dbPath, null, null, $options);
            $this->statsDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->statsDb->setAttribute(\PDO::ATTR_TIMEOUT, 5);
        } catch (\PDOException $e) {
            $this->logger->logError((int) $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
            return false;
        }

        return true;
    }

    /**
     * Return the UN country code for a country name, or null if not found.
     *
     * @param   string $countryName English country name (countries.name_en).
     * @return  ?string UN country code, or null if the name is unknown.
     */
    public function countryCode(string $countryName): ?string
    {
        if (!$this->statsDb) return null;

        $stmt = $this->statsDb->prepare(
            "SELECT un_code FROM countries WHERE name_en = :name LIMIT 1"
        );
        $stmt->execute([':name' => $countryName]);
        $code = $stmt->fetchColumn();

        return $code === false ? null : (string) $code;
    }

    /**
     * Return a human-readable name for a species code (alpha_3_code).
     *
     * Prefers the English common name, falling back to the scientific name. Returns an empty string
     * for an empty/unknown code or a species with no name on record, so callers (e.g. page titles)
     * never surface the raw three-letter code.
     *
     * @param   string $speciesCode Species code (alpha_3_code).
     * @return  string Common name, scientific name, or '' if none is available.
     */
    public function speciesName(string $speciesCode): string
    {
        $speciesCode = $this->trimString($speciesCode);

        if ($speciesCode === '' || !$this->statsDb) return '';

        $stmt = $this->statsDb->prepare(
            "SELECT name_en, scientific_name FROM species WHERE alpha_3_code = :code LIMIT 1"
        );
        $stmt->execute([':code' => $speciesCode]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) return '';

        $name = $this->trimString((string) ($row['name_en'] ?? ''));
        $sci = $this->trimString((string) ($row['scientific_name'] ?? ''));

        return $name !== '' ? $name : $sci;
    }

    /**
     * Return the list of countries that report aquaculture production.
     *
     * @return  array Alphabetical list of country names (name_en).
     */
    public function getCountryList(): array
    {
        if (!$this->statsDb) return [];

        $stmt = $this->statsDb->prepare(
            "SELECT DISTINCT c.name_en
             FROM countries c
             WHERE EXISTS (
                 SELECT 1 FROM aquaculture_production ap
                 WHERE ap.country_code = c.un_code AND ap.measure = :measure
             )
             ORDER BY c.name_en"
        );
        $stmt->execute([':measure' => 'Q_tlw']);

        return \array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'name_en');
    }

    /**
     * Return the list of countries that report trade, for the trade page state filter.
     *
     * Distinct from getCountryList() (which is keyed to aquaculture production): the trade table
     * has its own, wider set of reporting countries (~225), so the trade page filters against this
     * list. The EXISTS probe is satisfied by idx_trade_country.
     *
     * @return  array Alphabetical list of country names (name_en).
     */
    public function getTradeCountryList(): array
    {
        if (!$this->statsDb) return [];

        $stmt = $this->statsDb->query(
            "SELECT DISTINCT c.name_en
             FROM countries c
             WHERE EXISTS (
                 SELECT 1 FROM trade t WHERE t.country_code = c.un_code
             )
             ORDER BY c.name_en"
        );

        return \array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'name_en');
    }

    /**
     * Return the list of species that report aquaculture production, for the species filter.
     *
     * Each entry carries the species code (alpha_3_code), English common name and scientific name,
     * so the front-end autocomplete can match on either name and pass the code back. Ordered by
     * common name; species with an empty common name sort to the end (broken on code so the order
     * is deterministic) but remain selectable.
     *
     * Where the pre-aggregated global_species_summary is present the reporting-species set is read
     * from its distinct species codes — a covering-index scan over ~770 codes via
     * idx_gss_species_code, rather than scanning all ~14k species rows and probing
     * aquaculture_production once each. The summary is built from exactly the Q_tlw/V_USD_1000 rows,
     * so its species set matches the live one. Falls back to the live EXISTS scan when the summary
     * is absent, so the list degrades to slow-but-correct rather than failing.
     *
     * @return  array List of ['code' => string, 'name' => string, 'sci' => string].
     */
    public function getSpeciesList(): array
    {
        if (!$this->statsDb) return [];

        if ($this->hasSummaryTable('global_species_summary')) {
            $stmt = $this->statsDb->query(
                "SELECT s.alpha_3_code AS code, s.name_en AS name, s.scientific_name AS sci
                 FROM species s
                 JOIN (SELECT DISTINCT species_code FROM global_species_summary) g
                   ON g.species_code = s.alpha_3_code
                 ORDER BY (s.name_en IS NULL OR s.name_en = ''), s.name_en, s.alpha_3_code"
            );
        } else {
            $stmt = $this->statsDb->prepare(
                "SELECT s.alpha_3_code AS code, s.name_en AS name, s.scientific_name AS sci
                 FROM species s
                 WHERE EXISTS (
                     SELECT 1 FROM aquaculture_production ap
                     WHERE ap.species_code = s.alpha_3_code AND ap.measure = :measure
                 )
                 ORDER BY (s.name_en IS NULL OR s.name_en = ''), s.name_en, s.alpha_3_code"
            );
            $stmt->execute([':measure' => 'Q_tlw']);
        }

        $list = [];

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $name = $this->trimString((string) ($row['name'] ?? ''));
            $sci = $this->trimString((string) ($row['sci'] ?? ''));

            $list[] = [
                'code' => (string) $row['code'],
                'name' => $name !== '' ? $name : ($sci !== '' ? $sci : (string) $row['code']),
                'sci' => $sci,
            ];
        }

        return $list;
    }

    /**
     * Production by environment, as a yearly time series.
     *
     * Used for both the volume (Q_tlw, tonnes) and value (V_USD_1000, US dollars) breakdowns.
     *
     * The global (unfiltered) case reads the pre-aggregated global_environment_summary table,
     * which collapses the ~100k-row live aggregation into a ~225-row lookup. Country-filtered
     * cases hit aquaculture_production directly (fast via idx_production_country_measure_period
     * and always current). If the summary table is missing the global case falls back to the
     * same live aggregation, so the page degrades to slow-but-correct rather than failing.
     *
     * @param   ?string $countryCode UN country code, or null for the global total.
     * @param   string $measure Measure code: 'Q_tlw' (tonnes) or 'V_USD_1000' (value).
     * @return  array ['labels' => int[], 'freshwater' => int[], 'brackishwater' => int[], 'marine' => int[]].
     */
    public function environmentSeries(?string $countryCode, string $measure): array
    {
        $rows = ($countryCode === null && $this->hasSummaryTable('global_environment_summary'))
            ? $this->summaryEnvironmentRows($measure)
            : $this->liveEnvironmentRows($countryCode, $measure);

        return $this->pivotEnvironmentRows($rows);
    }

    /**
     * Rows from the pre-aggregated global summary, already in final units (tonnes / USD).
     *
     * @param   string $measure Measure code: 'Q_tlw' (tonnes) or 'V_USD_1000' (value).
     * @return  array List of ['year' => int, 'env' => string, 'amount' => int].
     */
    private function summaryEnvironmentRows(string $measure): array
    {
        // Column is chosen from a fixed whitelist, never from user input.
        $column = $measure === 'V_USD_1000' ? 'value_usd' : 'volume_tonnes';

        $stmt = $this->statsDb->query(
            "SELECT period AS year, environment_code AS env, {$column} AS amount
             FROM global_environment_summary ORDER BY period"
        );

        $rows = [];

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $rows[] = ['year' => (int) $row['year'], 'env' => $row['env'], 'amount' => (int) $row['amount']];
        }

        return $rows;
    }

    /**
     * Rows aggregated live from aquaculture_production, scaled to final units.
     *
     * @param   ?string $countryCode UN country code, or null for the global total.
     * @param   string $measure Measure code: 'Q_tlw' (tonnes) or 'V_USD_1000' (value).
     * @return  array List of ['year' => int, 'env' => string, 'amount' => int].
     */
    private function liveEnvironmentRows(?string $countryCode, string $measure): array
    {
        $multiplier = $measure === 'V_USD_1000' ? 1000 : 1;

        $sql = "SELECT p.period AS year, p.environment_code AS env,
                       CAST(SUM(p.value) AS INTEGER) AS amount
                FROM aquaculture_production p
                WHERE p.measure = :measure";

        $params = [':measure' => $measure];

        if ($countryCode !== null) {
            $sql .= " AND p.country_code = :country_code";
            $params[':country_code'] = $countryCode;
        }

        $sql .= " GROUP BY p.period, p.environment_code ORDER BY p.period";

        $stmt = $this->statsDb->prepare($sql);
        $stmt->execute($params);

        $rows = [];

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'year' => (int) $row['year'],
                'env' => $row['env'],
                'amount' => (int) $row['amount'] * $multiplier,
            ];
        }

        return $rows;
    }

    /**
     * Pivot environment rows into per-environment arrays aligned to a shared list of years.
     *
     * @param   array $rows List of ['year' => int, 'env' => string, 'amount' => int].
     * @return  array ['labels' => int[], 'freshwater' => int[], 'brackishwater' => int[], 'marine' => int[]].
     */
    private function pivotEnvironmentRows(array $rows): array
    {
        $map = ['IN' => 'freshwater', 'BW' => 'brackishwater', 'MA' => 'marine'];
        $byYear = [];

        foreach ($rows as $row) {
            $year = $row['year'];

            if (!isset($byYear[$year])) {
                $byYear[$year] = ['freshwater' => 0, 'brackishwater' => 0, 'marine' => 0];
            }

            $key = $map[$row['env']] ?? null;

            if ($key !== null) {
                $byYear[$year][$key] = $row['amount'];
            }
        }

        \ksort($byYear);

        $labels = [];
        $freshwater = [];
        $brackishwater = [];
        $marine = [];

        foreach ($byYear as $year => $vals) {
            $labels[] = $year;
            $freshwater[] = $vals['freshwater'];
            $brackishwater[] = $vals['brackishwater'];
            $marine[] = $vals['marine'];
        }

        return [
            'labels' => $labels,
            'freshwater' => $freshwater,
            'brackishwater' => $brackishwater,
            'marine' => $marine,
        ];
    }

    /**
     * Whether a named pre-aggregated summary table is present (memoized per table name).
     *
     * Used to decide whether a "global" view can read a derived summary table or must fall back
     * to live aggregation. The name is bound as a parameter, never interpolated.
     *
     * @param   string $tableName Summary table name (e.g. 'global_environment_summary').
     * @return  bool True if the table exists.
     */
    private function hasSummaryTable(string $tableName): bool
    {
        if (!\array_key_exists($tableName, $this->summaryTableExists)) {
            $stmt = $this->statsDb->prepare(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name LIMIT 1"
            );
            $stmt->execute([':name' => $tableName]);
            $this->summaryTableExists[$tableName] = $stmt->fetchColumn() !== false;
        }

        return $this->summaryTableExists[$tableName];
    }
}
