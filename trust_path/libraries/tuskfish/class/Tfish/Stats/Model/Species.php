<?php

declare(strict_types=1);

namespace Tfish\Stats\Model;

/**
 * \Tfish\Stats\Model\Species class file.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     Stats
 */

/**
 * Model for the aquaculture species profile page (/species/).
 *
 * Produces a combined payload for the global picture or a single member state: the major-group
 * overview (the donut pair), plus the top species by volume and by value.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     Stats
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Stats\Traits\StatsDatabase  Connection and country lookup helpers.
 */
class Species
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Stats\Traits\StatsDatabase;

    private $database;
    private $preference;
    private $session;
    private \Tfish\Logger $logger;

    private array $speciesData = [];
    private array $countryList = [];
    private array $yearCache = [];

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
     * Load the default payload (global picture, latest available year).
     */
    public function displaySpecies(): void
    {
        $this->speciesData = $this->buildPayload('', $this->latestYear());
    }

    /**
     * Return the most recently built payload.
     *
     * @return  array Combined chart payload.
     */
    public function speciesData(): array
    {
        return $this->speciesData;
    }

    /**
     * Build a payload for the supplied country and year, validating both inputs.
     *
     * Falls back to the global picture for an unknown country and to the latest year for an
     * invalid year, so the page always renders something sensible.
     *
     * @param   string $countryName English country name, or '' for the global picture.
     * @param   int $year Year to display in the species charts.
     */
    public function loadSpeciesData(string $countryName, int $year): void
    {
        if (!$this->statsDb) {
            $this->speciesData = $this->emptyPayload($countryName, $year);
            return;
        }

        $countryName = $this->trimString($countryName);

        if ($countryName !== '' && !\in_array($countryName, $this->getCountryList(), true)) {
            $countryName = '';
        }

        $years = $this->availableYears();

        if (!\in_array($year, $years, true)) {
            $year = $years ? $years[0] : 0;
        }

        $this->speciesData = $this->buildPayload($countryName, $year);
    }

    /**
     * Assemble the combined payload for a (validated) country and year.
     *
     * @param   string $countryName Validated country name, or '' for global.
     * @param   int $year Validated year.
     * @return  array Combined chart payload.
     */
    private function buildPayload(string $countryName, int $year): array
    {
        if (!$this->statsDb) {
            return $this->emptyPayload($countryName, $year);
        }

        $countryCode = $countryName !== '' ? $this->countryCode($countryName) : null;

        // Fetch every species' volume and value in one query, then derive the two rankings and the
        // major-group donut overview from the same rows — the value ranking and both donuts add no
        // extra query. Each ranking is re-sorted in PHP on its own measure (cheap for ~500 rows).
        $rows = $this->speciesRows($year, $countryCode);
        $years = $this->availableYears();
        $volumeRows = $this->rankBy($rows, 'volume');
        $valueRows = $this->rankBy($rows, 'value');
        $volume = $this->formatRanking($volumeRows);
        $value = $this->formatRanking($valueRows);
        $groups = [
            'volume' => $this->collapseGroups($volumeRows),
            'value' => $this->collapseGroups($valueRows),
        ];

        return [
            'country' => $countryName,
            'year' => $year,
            'years' => $years,
            'volume' => $volume,
            'value' => $value,
            'groups' => $groups,
        ];
    }

    /**
     * Empty payload used when the database is unavailable.
     *
     * @param   string $countryName Country name.
     * @param   int $year Year.
     * @return  array Empty chart payload.
     */
    private function emptyPayload(string $countryName, int $year): array
    {
        return [
            'country' => $countryName,
            'year' => $year,
            'years' => [],
            'volume' => ['labels' => [], 'scientific' => [], 'values' => [], 'codes' => []],
            'value' => ['labels' => [], 'scientific' => [], 'values' => [], 'codes' => []],
            'groups' => [
                'volume' => ['labels' => [], 'values' => []],
                'value' => ['labels' => [], 'values' => []],
            ],
        ];
    }

    /**
     * Full per-species rows for a given year, each carrying both volume and value.
     *
     * Returns every species (each row carrying its code, names, major group and both the volume
     * and value totals) so the caller can shape both species rankings, roll the rows up into the
     * major-group donut overview, and offer the complete unaggregated data for CSV download — all
     * from a single fetch. Totals are in final units (integer tonnes / US dollars).
     *
     * Aggregation is by species code (not common name): many species share an empty English name
     * but have a distinct scientific name, so grouping by name would merge them into a single
     * untitled bar.
     *
     * The global (unfiltered) case reads the pre-aggregated global_species_summary table; country
     * cases (and the fallback when the summary is absent) aggregate aquaculture_production live.
     *
     * @param   int $year Year to aggregate.
     * @param   ?string $countryCode UN country code, or null for the global total.
     * @return  array List of ['code' => string, 'name' => ?string, 'sci' => ?string, 'grp' => ?string, 'volume' => int, 'value' => int].
     */
    private function speciesRows(int $year, ?string $countryCode): array
    {
        return ($countryCode === null && $this->hasSummaryTable('global_species_summary'))
            ? $this->summarySpeciesRows($year)
            : $this->liveSpeciesRows($year, $countryCode);
    }

    /**
     * Pre-aggregated global rows for a year (both measures), already in final units.
     *
     * Reads the derived global_species_summary table (one indexed seek to the year's ~500 rows)
     * instead of aggregating aquaculture_production live. Both volume and value come from the same
     * row, so the two rankings and the donuts are served by this single query. Labels are joined
     * from the species table at read time so name corrections take effect without rebuilding the
     * summary. Ordering is left to the caller, which re-sorts per measure.
     *
     * @param   int $year Year to read.
     * @return  array List of ['code' => string, 'name' => ?string, 'sci' => ?string, 'grp' => ?string, 'volume' => int, 'value' => int].
     */
    private function summarySpeciesRows(int $year): array
    {
        $stmt = $this->statsDb->prepare(
            "SELECT s.alpha_3_code AS code, s.name_en AS name, s.scientific_name AS sci,
                    s.major_group AS grp, g.volume_tonnes AS volume, g.value_usd AS value
             FROM global_species_summary g
             JOIN species s ON g.species_code = s.alpha_3_code
             WHERE g.period = :year"
        );
        $stmt->execute([':year' => $year]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Rows aggregated live from aquaculture_production (both measures), scaled to final units.
     *
     * Used for country-filtered views (always current, and cheap because the country narrows the
     * slice) and as the fallback when the summary table is absent. Conditional aggregation pulls
     * both volume (Q_tlw) and value (V_USD_1000, stored in thousands) in one pass.
     *
     * @param   int $year Year to aggregate.
     * @param   ?string $countryCode UN country code, or null for the global total.
     * @return  array List of ['code' => string, 'name' => ?string, 'sci' => ?string, 'grp' => ?string, 'volume' => int, 'value' => int].
     */
    private function liveSpeciesRows(int $year, ?string $countryCode): array
    {
        $sql = "SELECT s.alpha_3_code AS code, s.name_en AS name, s.scientific_name AS sci,
                       s.major_group AS grp,
                       SUM(CASE WHEN p.measure = 'Q_tlw' THEN p.value ELSE 0 END) AS volume,
                       SUM(CASE WHEN p.measure = 'V_USD_1000' THEN p.value ELSE 0 END) AS value
                FROM aquaculture_production p
                JOIN species s ON p.species_code = s.alpha_3_code
                WHERE p.period = :year AND p.measure IN ('Q_tlw', 'V_USD_1000')";

        $params = [':year' => $year];

        if ($countryCode !== null) {
            $sql .= " AND p.country_code = :country_code";
            $params[':country_code'] = $countryCode;
        }

        $sql .= " GROUP BY s.alpha_3_code, s.major_group";

        $stmt = $this->statsDb->prepare($sql);
        $stmt->execute($params);

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
     * Rank the combined rows by one measure, exposing it as 'total' for the downstream shapers.
     *
     * Copies each row with the chosen measure ('volume' or 'value') promoted to a 'total' key and
     * sorts by that total descending, breaking ties on species code so the order is deterministic
     * and matches the previous per-measure SQL ordering.
     *
     * Rows with a zero total for this measure are dropped: the combined fetch returns every species
     * that reported *either* measure, so a value-only species would otherwise surface as a phantom
     * 0-tonne entry in the volume ranking (and its CSV), and vice versa. Keeping only non-zero rows
     * restores the old per-measure semantics, where each ranking listed only species that actually
     * reported that measure.
     *
     * @param   array $rows Combined rows from speciesRows().
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
     * Where the English name is missing the scientific name is used as the label, falling back to
     * the species code as a last resort. Totals are already in final units (integer tonnes / USD).
     *
     * @param   array $rows List of ['code' => string, 'name' => ?string, 'sci' => ?string, 'total' => int|string].
     * @return  array ['labels' => string[], 'scientific' => string[], 'values' => int[]] by descending value.
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
            $labels[] = $name !== '' ? $name : ($sci !== '' ? $sci : $row['code']);
            $scientific[] = $sci;
            $values[] = (int) $row['total'];
            // Species code carried so the bars can drill through to the production (country) ranking.
            $codes[] = $this->trimString((string) ($row['code'] ?? ''));
        }

        return ['labels' => $labels, 'scientific' => $scientific, 'values' => $values, 'codes' => $codes];
    }

    /**
     * Collapse per-species rows into the five reader-friendly major-group buckets the donut shows.
     *
     * Rolls up the same species rows already fetched for the ranking (so the donut overview costs
     * no extra query). The dataset's seven Latin major-group classes are mapped onto five
     * plain-language buckets; anything unmapped (including a missing class) falls into "Other".
     * Buckets are returned in a fixed order so the front-end can assign a stable colour per bucket
     * across both donuts, which is what makes the volume-vs-value reshuffle legible. Zero-valued
     * buckets are retained here (the front-end drops empty slices) so the array shape is constant.
     *
     * @param   array $rows List of species rows, each with ['grp' => ?string, 'total' => int|string].
     * @return  array ['labels' => string[], 'values' => int[]] in fixed bucket order.
     */
    private function collapseGroups(array $rows): array
    {
        $map = [
            'PISCES' => 'Fish',
            'CRUSTACEA' => 'Crustaceans',
            'MOLLUSCA' => 'Molluscs',
            'PLANTAE AQUATICAE' => 'Plants & seaweed',
        ];

        // Fixed display order; every bucket is always present so colours stay stable.
        $order = ['Fish', 'Crustaceans', 'Molluscs', 'Plants & seaweed', 'Other'];
        $totals = \array_fill_keys($order, 0);

        foreach ($rows as $row) {
            $bucket = $map[(string) ($row['grp'] ?? '')] ?? 'Other';
            $totals[$bucket] += (int) $row['total'];
        }

        return ['labels' => $order, 'values' => \array_values($totals)];
    }

    /**
     * Distinct years for which aquaculture volume data exists, most recent first.
     *
     * @return  array List of years (int), descending.
     */
    private function availableYears(): array
    {
        if (!$this->statsDb) return [];

        if ($this->yearCache) return $this->yearCache;

        // Prefer the small derived summary; fall back to scanning the production table live.
        //
        // The summary branch deliberately carries no volume predicate. Every period in the summary
        // already has volume data, so a "WHERE volume_tonnes > 0" filter is a no-op here — but
        // because volume_tonnes is unindexed it forces a full scan of the whole table instead of a
        // covering-index skip-scan over the ~75 distinct periods, which cost ~10ms on every page
        // load. Selecting period alone lets SQLite satisfy the query from the primary-key index.
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
