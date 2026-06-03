<?php

declare(strict_types=1);

namespace Tfish\Stats\Model;

/**
 * \Tfish\Stats\Model\Listing class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 */

/**
 * Model for the Stats landing page (/) — the global overview dashboard.
 *
 * Produces a yearly time series of capture and aquaculture production (volume and value), for the
 * global picture or a single member state, optionally narrowed to one species. The global,
 * unfiltered picture reads the pre-aggregated global_production_summary table; country and species
 * cuts aggregate the source production tables live. See sql/README.md for the summary-table runbook.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Stats\Traits\StatsDatabase  Connection and country lookup helpers.
 */
class Listing
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Stats\Traits\StatsDatabase;

    private $database;
    private $preference;
    private $session;
    private \Tfish\Logger $logger;

    private array $chartData = [];
    private array $countryList = [];

    public function __construct(
        \Tfish\Database $database,
        \Tfish\Entity\Preference $preference,
        \Tfish\Session $session,
        \Tfish\Logger $logger
    ) {
        $this->database = $database;
        $this->preference = $preference;
        $this->session = $session;
        $this->logger = $logger;
        $this->connect();
    }

    /**
     * Load the default payload (global picture, full year range).
     */
    public function displayGlobal(): void
    {
        $this->chartData = $this->getGlobalProductionData();
    }

    /**
     * Return the most recently built chart payload.
     *
     * @return  array Combined production chart payload.
     */
    public function chartData(): array
    {
        return $this->chartData;
    }

    /**
     * Build the global production payload, optionally narrowed to one species.
     *
     * With no species the headline capture-vs-aquaculture totals come from the pre-aggregated
     * global_production_summary table; a species code switches to a live per-species aggregation.
     *
     * @param   string $speciesCode Three-letter species code, or '' for all species.
     * @return  array Combined production chart payload.
     */
    public function getGlobalProductionData(string $speciesCode = ''): array
    {
        if (!$this->statsDb) return [];

        if ($speciesCode === '') {
            return $this->getGlobalSummary();
        }

        return $this->getGlobalProductionBySpecies($speciesCode);
    }

    /**
     * Read the headline global totals from the pre-aggregated global_production_summary table.
     *
     * @return  array Combined production chart payload (global, all species).
     */
    private function getGlobalSummary(): array
    {
        $stmt = $this->statsDb->prepare(
            "SELECT period, capture_tonnes, aquaculture_tonnes, aquaculture_value_usd
             FROM global_production_summary
             ORDER BY period"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $labels = [];
        $capture = [];
        $aquaculture = [];
        $aquacultureValue = [];

        foreach ($rows as $row) {
            $labels[] = (int)$row['period'];
            $capture[] = (int)$row['capture_tonnes'];
            $aquaculture[] = (int)$row['aquaculture_tonnes'];
            $aquacultureValue[] = (int)$row['aquaculture_value_usd'];
        }

        return [
            'labels' => $labels,
            'capture' => $capture,
            'aquaculture' => $aquaculture,
            'aquaculture_value' => $aquacultureValue,
            'country' => '',
            'species' => '',
        ];
    }

    /**
     * Aggregate global capture and aquaculture production for a single species, by year.
     *
     * @param   string $speciesCode Three-letter species code to filter on.
     * @return  array Combined production chart payload (global, one species).
     */
    private function getGlobalProductionBySpecies(string $speciesCode): array
    {
        $speciesClause = ' AND species_code = :species';

        $captureStmt = $this->statsDb->prepare(
            "SELECT period, CAST(SUM(value) AS INTEGER) AS tonnes
             FROM capture_production
             WHERE measure = :measure{$speciesClause}
             GROUP BY period
             ORDER BY period"
        );
        $captureStmt->execute([':measure' => 'Q_tlw', ':species' => $speciesCode]);
        $captureRows = $captureStmt->fetchAll(\PDO::FETCH_ASSOC);

        $aquaStmt = $this->statsDb->prepare(
            "SELECT period,
                    CAST(SUM(CASE WHEN measure = :measure_qty THEN value ELSE 0 END) AS INTEGER) AS tonnes,
                    CAST(SUM(CASE WHEN measure = :measure_val THEN value ELSE 0 END) AS INTEGER) AS usd_thousands
             FROM aquaculture_production
             WHERE measure IN (:measure_qty, :measure_val){$speciesClause}
             GROUP BY period
             ORDER BY period"
        );
        $aquaStmt->execute([':measure_qty' => 'Q_tlw', ':measure_val' => 'V_USD_1000', ':species' => $speciesCode]);
        $aquaRows = $aquaStmt->fetchAll(\PDO::FETCH_ASSOC);

        $captureByYear = [];
        foreach ($captureRows as $row) {
            $captureByYear[(int)$row['period']] = (int)$row['tonnes'];
        }

        $aquacultureByYear = [];
        $valueByYear = [];
        foreach ($aquaRows as $row) {
            $aquacultureByYear[(int)$row['period']] = (int)$row['tonnes'];
            $valueByYear[(int)$row['period']] = (int)$row['usd_thousands'];
        }

        $allYears = \array_unique(\array_merge(
            \array_keys($captureByYear),
            \array_keys($aquacultureByYear)
        ));
        \sort($allYears);

        $labels = [];
        $capture = [];
        $aquaculture = [];
        $aquacultureValue = [];

        foreach ($allYears as $year) {
            $labels[] = $year;
            $capture[] = $captureByYear[$year] ?? 0;
            $aquaculture[] = $aquacultureByYear[$year] ?? 0;
            $aquacultureValue[] = $valueByYear[$year] ?? 0;
        }

        return [
            'labels' => $labels,
            'capture' => $capture,
            'aquaculture' => $aquaculture,
            'aquaculture_value' => $aquacultureValue,
            'country' => '',
            'species' => $speciesCode,
        ];
    }

    /**
     * Aggregate capture and aquaculture production for one member state, optionally one species.
     *
     * @param   string $countryName English country name to filter on.
     * @param   string $speciesCode Three-letter species code, or '' for all species.
     * @return  array Combined production chart payload (one country).
     */
    public function getCountryProductionData(string $countryName, string $speciesCode = ''): array
    {
        if (!$this->statsDb) return [];

        $countryCode = $this->countryCode($countryName);

        if ($countryCode === null) return [];

        $captureParams = [':measure' => 'Q_tlw', ':country_code' => $countryCode];
        $aquaParams = [':measure_qty' => 'Q_tlw', ':measure_val' => 'V_USD_1000', ':country_code' => $countryCode];
        $captureSpecies = '';
        $aquaSpecies = '';

        if ($speciesCode !== '') {
            $captureSpecies = ' AND species_code = :species';
            $aquaSpecies = ' AND species_code = :species';
            $captureParams[':species'] = $speciesCode;
            $aquaParams[':species'] = $speciesCode;
        }

        $captureStmt = $this->statsDb->prepare(
            "SELECT period, CAST(SUM(value) AS INTEGER) AS tonnes
             FROM capture_production
             WHERE measure = :measure AND country_code = :country_code{$captureSpecies}
             GROUP BY period
             ORDER BY period"
        );
        $captureStmt->execute($captureParams);
        $captureRows = $captureStmt->fetchAll(\PDO::FETCH_ASSOC);

        $aquaStmt = $this->statsDb->prepare(
            "SELECT period,
                    CAST(SUM(CASE WHEN measure = :measure_qty THEN value ELSE 0 END) AS INTEGER) AS tonnes,
                    CAST(SUM(CASE WHEN measure = :measure_val THEN value ELSE 0 END) AS INTEGER) AS usd_thousands
             FROM aquaculture_production
             WHERE measure IN (:measure_qty, :measure_val) AND country_code = :country_code{$aquaSpecies}
             GROUP BY period
             ORDER BY period"
        );
        $aquaStmt->execute($aquaParams);
        $aquaRows = $aquaStmt->fetchAll(\PDO::FETCH_ASSOC);

        $captureByYear = [];
        foreach ($captureRows as $row) {
            $captureByYear[(int)$row['period']] = (int)$row['tonnes'];
        }

        $aquacultureByYear = [];
        $valueByYear = [];
        foreach ($aquaRows as $row) {
            $aquacultureByYear[(int)$row['period']] = (int)$row['tonnes'];
            $valueByYear[(int)$row['period']] = (int)$row['usd_thousands'];
        }

        $allYears = \array_unique(\array_merge(
            \array_keys($captureByYear),
            \array_keys($aquacultureByYear)
        ));
        \sort($allYears);

        $labels = [];
        $capture = [];
        $aquaculture = [];
        $aquacultureValue = [];

        foreach ($allYears as $year) {
            $labels[] = $year;
            $capture[] = $captureByYear[$year] ?? 0;
            $aquaculture[] = $aquacultureByYear[$year] ?? 0;
            $aquacultureValue[] = $valueByYear[$year] ?? 0;
        }

        return [
            'labels' => $labels,
            'capture' => $capture,
            'aquaculture' => $aquaculture,
            'aquaculture_value' => $aquacultureValue,
            'country' => $countryName,
            'species' => $speciesCode,
        ];
    }

    /**
     * Build and store the chart payload for the supplied country and species, validating both.
     *
     * Falls back to the global picture for an empty or unknown country, and ignores a species code
     * that is malformed or not present in the database, so the page always renders something sensible.
     *
     * @param   string $countryName English country name, or '' for the global picture.
     * @param   string $speciesCode Three-letter species code, or '' for all species.
     */
    public function loadChartDataForCountry(string $countryName, string $speciesCode = ''): void
    {
        if (!$this->statsDb) return;

        $countryName = $this->trimString($countryName);
        $speciesCode = $this->trimString($speciesCode);

        if ($speciesCode !== '') {
            if (!\preg_match('/^[A-Z]{3}$/', $speciesCode)) {
                $speciesCode = '';
            } else {
                $checkStmt = $this->statsDb->prepare(
                    "SELECT 1 FROM species WHERE alpha_3_code = :code LIMIT 1"
                );
                $checkStmt->execute([':code' => $speciesCode]);

                if (!$checkStmt->fetch()) {
                    $speciesCode = '';
                }
            }
        }

        if ($countryName === '') {
            $this->chartData = $this->getGlobalProductionData($speciesCode);
            return;
        }

        $countries = $this->getCountryList();

        if (!\in_array($countryName, $countries, true)) {
            $this->chartData = $this->getGlobalProductionData($speciesCode);
            return;
        }

        $this->chartData = $this->getCountryProductionData($countryName, $speciesCode);
    }

    /**
     * Populate the country list for the page (member-state filter).
     */
    public function loadCountryList(): void
    {
        $this->countryList = $this->getCountryList();
    }

    /**
     * Return the loaded country list.
     *
     * @return  array Alphabetical list of country names.
     */
    public function countries(): array
    {
        return $this->countryList;
    }

}
