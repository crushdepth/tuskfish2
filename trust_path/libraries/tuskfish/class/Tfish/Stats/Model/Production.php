<?php

declare(strict_types=1);

namespace Tfish\Stats\Model;

/**
 * \Tfish\Stats\Model\Production class file.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     Stats
 */

/**
 * Model for the aquaculture production page (/production/).
 *
 * The mirror of the species page: where /species/ ranks species for a chosen country, this ranks
 * countries for a chosen species. Two payload shapes are produced. Before a species is selected
 * the page shows a "landing" menu of the biggest species worldwide (by volume) — a visual picker
 * whose bars double as the way in. Once a species is chosen the payload carries the top producing
 * countries by volume and by value, plus a Production-by-Region donut overview.
 *
 * Ranking countries for a single species in a single year is a tiny, well-indexed slice
 * (idx_production_species_measure_period), so this page always aggregates aquaculture_production
 * live — there is no pre-aggregated summary table for it, and none is needed.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     Stats
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Stats\Traits\StatsDatabase  Connection and species/country lookup helpers.
 */
class Production
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Stats\Traits\StatsDatabase;

    private $database;
    private $preference;
    private $session;
    private \Tfish\Logger $logger;

    private array $productionData = [];
    private array $speciesList = [];
    private array $yearCache = [];

    /** Number of species shown on the landing menu. */
    private const TOP_SPECIES = 12;

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
     * Load the default payload: the landing menu for the latest available year.
     */
    public function displayProduction(): void
    {
        $this->productionData = $this->buildLanding($this->latestYear());
    }

    /**
     * Return the most recently built payload.
     *
     * @return  array Chart payload (landing or species mode).
     */
    public function productionData(): array
    {
        return $this->productionData;
    }

    /**
     * Build a payload for the supplied species and year, validating both inputs.
     *
     * An empty or unknown species code yields the landing menu; an invalid year falls back to the
     * latest, so the page always renders something sensible.
     *
     * @param   string $speciesCode Species code (alpha_3_code), or '' for the landing menu.
     * @param   int $year Year to display.
     */
    public function loadProductionData(string $speciesCode, int $year): void
    {
        if (!$this->statsDb) {
            $this->productionData = $this->emptyLanding($year);
            return;
        }

        $years = $this->availableYears();

        if (!\in_array($year, $years, true)) {
            $year = $years ? $years[0] : 0;
        }

        $speciesCode = $this->trimString($speciesCode);
        $info = $speciesCode !== '' ? $this->speciesInfo($speciesCode) : null;

        $this->productionData = $info === null
            ? $this->buildLanding($year)
            : $this->buildSpecies($speciesCode, $info, $year);
    }

    /**
     * Assemble the landing menu: the biggest species worldwide for a year, by volume.
     *
     * @param   int $year Validated year.
     * @return  array Landing-mode payload.
     */
    private function buildLanding(int $year): array
    {
        $rows = $this->rankBy($this->topSpeciesRows($year), 'volume');

        return [
            'mode' => 'landing',
            'species' => '',
            'speciesName' => '',
            'speciesSci' => '',
            'year' => $year,
            'years' => $this->availableYears(),
            'topSpecies' => $this->formatRanking($rows),
            'volume' => ['labels' => [], 'scientific' => [], 'values' => [], 'codes' => []],
            'value' => ['labels' => [], 'scientific' => [], 'values' => [], 'codes' => []],
            'regions' => [
                'volume' => ['labels' => [], 'values' => []],
                'value' => ['labels' => [], 'values' => []],
            ],
        ];
    }

    /**
     * Assemble the species payload: top producing countries by volume and value, plus the region
     * overview, all from a single live aggregation.
     *
     * @param   string $speciesCode Validated species code.
     * @param   array $info Species labels: ['name' => string, 'sci' => string].
     * @param   int $year Validated year.
     * @return  array Species-mode payload.
     */
    private function buildSpecies(string $speciesCode, array $info, int $year): array
    {
        $rows = $this->producerRows($year, $speciesCode);

        // Many species only have data in older years (e.g. discontinued reporting). The year
        // dropdown defaults to the latest year across ALL species, so picking such a species would
        // otherwise show an empty view. Fall back to this species' own most recent year with data.
        if (!$rows) {
            $fallbackYear = $this->latestYearForSpecies($speciesCode);

            if ($fallbackYear !== 0 && $fallbackYear !== $year) {
                $year = $fallbackYear;
                $rows = $this->producerRows($year, $speciesCode);
            }
        }

        $volumeRows = $this->rankBy($rows, 'volume');
        $valueRows = $this->rankBy($rows, 'value');

        return [
            'mode' => 'species',
            'species' => $speciesCode,
            'speciesName' => $info['name'],
            'speciesSci' => $info['sci'],
            'year' => $year,
            'years' => $this->availableYears(),
            'topSpecies' => ['labels' => [], 'scientific' => [], 'values' => [], 'codes' => []],
            'volume' => $this->formatRanking($volumeRows),
            'value' => $this->formatRanking($valueRows),
            'regions' => [
                'volume' => $this->collapseRegions($volumeRows),
                'value' => $this->collapseRegions($valueRows),
            ],
        ];
    }

    /**
     * Empty landing payload used when the database is unavailable.
     *
     * @param   int $year Year.
     * @return  array Empty landing-mode payload.
     */
    private function emptyLanding(int $year): array
    {
        return [
            'mode' => 'landing',
            'species' => '',
            'speciesName' => '',
            'speciesSci' => '',
            'year' => $year,
            'years' => [],
            'topSpecies' => ['labels' => [], 'scientific' => [], 'values' => [], 'codes' => []],
            'volume' => ['labels' => [], 'scientific' => [], 'values' => [], 'codes' => []],
            'value' => ['labels' => [], 'scientific' => [], 'values' => [], 'codes' => []],
            'regions' => [
                'volume' => ['labels' => [], 'values' => []],
                'value' => ['labels' => [], 'values' => []],
            ],
        ];
    }

    /**
     * The biggest species worldwide for a year, by volume, capped at TOP_SPECIES rows.
     *
     * Reads the pre-aggregated global_species_summary where present (an indexed seek to the year's
     * ~500 rows, then a tiny sort); falls back to live aggregation of aquaculture_production if the
     * summary is absent. Totals are in final units (integer tonnes). Each row carries 'volume' so
     * the shared ranking/format helpers can treat it like any other measured row.
     *
     * @param   int $year Year to read.
     * @return  array List of ['code' => string, 'name' => ?string, 'sci' => ?string, 'volume' => int].
     */
    private function topSpeciesRows(int $year): array
    {
        // The row cap is a compile-time integer constant, never user input, so it is interpolated
        // directly: binding a parameter to LIMIT is unreliable across pdo_sqlite prepare modes.
        $limit = (int) self::TOP_SPECIES;

        if ($this->hasSummaryTable('global_species_summary')) {
            $stmt = $this->statsDb->prepare(
                "SELECT s.alpha_3_code AS code, s.name_en AS name, s.scientific_name AS sci,
                        g.volume_tonnes AS volume
                 FROM global_species_summary g
                 JOIN species s ON g.species_code = s.alpha_3_code
                 WHERE g.period = :year AND g.volume_tonnes > 0
                 ORDER BY g.volume_tonnes DESC
                 LIMIT {$limit}"
            );
            $stmt->execute([':year' => $year]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $stmt = $this->statsDb->prepare(
            "SELECT s.alpha_3_code AS code, s.name_en AS name, s.scientific_name AS sci,
                    CAST(SUM(p.value) AS INTEGER) AS volume
             FROM aquaculture_production p
             JOIN species s ON p.species_code = s.alpha_3_code
             WHERE p.period = :year AND p.measure = 'Q_tlw'
             GROUP BY s.alpha_3_code
             HAVING volume > 0
             ORDER BY volume DESC
             LIMIT {$limit}"
        );
        $stmt->execute([':year' => $year]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Per-country rows for one species and year, each carrying both volume and value.
     *
     * Aggregated live from aquaculture_production; one species across all countries for one year is
     * a small, well-indexed slice. Conditional aggregation pulls volume (Q_tlw) and value
     * (V_USD_1000, stored in thousands) in a single pass. Each row also carries the country's major
     * region for the donut overview. Totals are returned in final units (integer tonnes / USD).
     *
     * @param   int $year Year to aggregate.
     * @param   string $speciesCode Species code (alpha_3_code).
     * @return  array List of ['code' => string, 'name' => ?string, 'region' => ?string, 'volume' => int, 'value' => int].
     */
    private function producerRows(int $year, string $speciesCode): array
    {
        $stmt = $this->statsDb->prepare(
            "SELECT c.un_code AS code, c.name_en AS name, c.continent_group_en AS region,
                    SUM(CASE WHEN p.measure = 'Q_tlw' THEN p.value ELSE 0 END) AS volume,
                    SUM(CASE WHEN p.measure = 'V_USD_1000' THEN p.value ELSE 0 END) AS value
             FROM aquaculture_production p
             JOIN countries c ON p.country_code = c.un_code
             WHERE p.species_code = :code AND p.period = :year
                   AND p.measure IN ('Q_tlw', 'V_USD_1000')
             GROUP BY p.country_code"
        );
        $stmt->execute([':code' => $speciesCode, ':year' => $year]);

        $rows = [];

        // Value is stored in thousands of US dollars; volume is already in tonnes.
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $row['volume'] = (int) \round((float) $row['volume']);
            $row['value'] = (int) \round((float) $row['value'] * 1000);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Rank rows by one measure, exposing it as 'total' for the downstream shapers.
     *
     * Promotes the chosen measure ('volume' or 'value') to a 'total' key and sorts descending,
     * breaking ties on code so the order is deterministic. Rows with a zero total for this measure
     * are dropped: the combined fetch returns every country (or species) reporting *either* measure,
     * so a value-only entry would otherwise surface as a phantom zero in the volume ranking, and
     * vice versa.
     *
     * @param   array $rows Combined rows.
     * @param   string $key Measure key to rank on: 'volume' or 'value'.
     * @return  array Rows sorted by descending total, each with a 'total' key added.
     */
    private function rankBy(array $rows, string $key): array
    {
        $ranked = [];

        foreach ($rows as $row) {
            $total = (int) $row[$key];

            if ($total === 0) {
                continue;
            }

            $row['total'] = $total;
            $ranked[] = $row;
        }

        \usort(
            $ranked,
            static fn(array $a, array $b): int =>
                ($b['total'] <=> $a['total']) ?: \strcmp((string) $a['code'], (string) $b['code'])
        );

        return $ranked;
    }

    /**
     * Shape ranking rows into the parallel arrays the chart payload expects.
     *
     * Emits a 'codes' array alongside the labels so the front-end can map a clicked bar back to a
     * species code (the landing menu) — it is ignored for country rankings, whose bars are terminal.
     * Where the English name is missing the scientific name is used as the label, falling back to
     * the code. Totals are already in final units (integer tonnes / USD).
     *
     * @param   array $rows List of rows, each with 'code', optional 'name'/'sci', and 'total'.
     * @return  array ['labels' => string[], 'scientific' => string[], 'values' => int[], 'codes' => string[]].
     */
    private function formatRanking(array $rows): array
    {
        $labels = [];
        $scientific = [];
        $values = [];
        $codes = [];

        foreach ($rows as $row) {
            $name = $this->trimString((string) ($row['name'] ?? ''));
            $sci = $this->trimString((string) ($row['sci'] ?? ''));
            $labels[] = $name !== '' ? $name : ($sci !== '' ? $sci : (string) $row['code']);
            $scientific[] = $sci;
            $values[] = (int) $row['total'];
            $codes[] = (string) $row['code'];
        }

        return ['labels' => $labels, 'scientific' => $scientific, 'values' => $values, 'codes' => $codes];
    }

    /**
     * Collapse per-country rows into the major-region buckets the donut shows.
     *
     * Uses the country's continent_group_en (Asia / Americas / Europe / Africa / Oceania); anything
     * unmapped (including a blank group) falls into "Other". Buckets are returned in a fixed order
     * so the front-end can assign a stable colour per region across both donuts. Zero-valued buckets
     * are retained here (the front-end drops empty slices) so the array shape is constant.
     *
     * @param   array $rows List of country rows, each with ['region' => ?string, 'total' => int].
     * @return  array ['labels' => string[], 'values' => int[]] in fixed bucket order.
     */
    private function collapseRegions(array $rows): array
    {
        // Fixed display order; every bucket is always present so colours stay stable.
        $order = ['Asia', 'Americas', 'Europe', 'Africa', 'Oceania', 'Other'];
        $totals = \array_fill_keys($order, 0);

        foreach ($rows as $row) {
            $region = $this->trimString((string) ($row['region'] ?? ''));
            $bucket = isset($totals[$region]) && $region !== 'Other' ? $region : 'Other';
            $totals[$bucket] += (int) $row['total'];
        }

        return ['labels' => $order, 'values' => \array_values($totals)];
    }

    /**
     * The English name and scientific name for a species code, or null if the code is unknown.
     *
     * Doubles as the validity check for a requested species: a null result sends the page back to
     * the landing menu.
     *
     * @param   string $speciesCode Species code (alpha_3_code).
     * @return  ?array ['name' => string, 'sci' => string], or null if not found.
     */
    private function speciesInfo(string $speciesCode): ?array
    {
        $stmt = $this->statsDb->prepare(
            "SELECT name_en AS name, scientific_name AS sci FROM species WHERE alpha_3_code = :code LIMIT 1"
        );
        $stmt->execute([':code' => $speciesCode]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $name = $this->trimString((string) ($row['name'] ?? ''));
        $sci = $this->trimString((string) ($row['sci'] ?? ''));

        return [
            'name' => $name !== '' ? $name : ($sci !== '' ? $sci : $speciesCode),
            'sci' => $sci,
        ];
    }

    /**
     * The most recent year in which a given species has reported aquaculture volume.
     *
     * Used as a fallback when the requested year (the global latest) holds no data for the species,
     * so the page lands on a populated view rather than an empty one.
     *
     * @param   string $speciesCode Species code (alpha_3_code).
     * @return  int Latest year with volume > 0 for this species, or 0 if none.
     */
    private function latestYearForSpecies(string $speciesCode): int
    {
        $stmt = $this->statsDb->prepare(
            "SELECT MAX(period) FROM aquaculture_production
             WHERE species_code = :code AND measure = 'Q_tlw' AND value > 0"
        );
        $stmt->execute([':code' => $speciesCode]);
        $value = $stmt->fetchColumn();

        return $value !== false && $value !== null ? (int) $value : 0;
    }

    /**
     * Distinct years for which aquaculture volume data exists, most recent first.
     *
     * Prefers the small derived summary (selecting period alone lets SQLite satisfy the query from
     * the primary-key index); falls back to scanning the production table live.
     *
     * @return  array List of years (int), descending.
     */
    private function availableYears(): array
    {
        if (!$this->statsDb) return [];

        if ($this->yearCache) return $this->yearCache;

        $sql = $this->hasSummaryTable('global_species_summary')
            ? "SELECT DISTINCT period FROM global_species_summary ORDER BY period DESC"
            : "SELECT DISTINCT period FROM aquaculture_production WHERE measure = 'Q_tlw' ORDER BY period DESC";

        $stmt = $this->statsDb->query($sql);

        $this->yearCache = \array_map('intval', \array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'period'));

        return $this->yearCache;
    }

    /**
     * The most recent year with aquaculture volume data.
     *
     * @return  int Latest year, or 0 if none.
     */
    private function latestYear(): int
    {
        $years = $this->availableYears();

        return $years ? $years[0] : 0;
    }

    /**
     * Populate the species list for the page (species filter).
     */
    public function loadSpeciesList(): void
    {
        $this->speciesList = $this->getSpeciesList();
    }

    /**
     * Return the loaded species list.
     *
     * @return  array List of ['code' => string, 'name' => string, 'sci' => string].
     */
    public function speciesList(): array
    {
        return $this->speciesList;
    }
}
