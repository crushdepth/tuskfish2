<?php

declare(strict_types=1);

namespace Tfish\FishStat\Model;

/**
 * \Tfish\FishStat\Model\Species class file.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     FishStat
 */

/**
 * Model for the aquaculture species and environment profile page (/species/).
 *
 * Produces a combined payload (top species by volume, top species by value, and a production
 * volume by environment time series) for the global picture or for a single member state.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     FishStat
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\FishStat\Traits\FishStatDatabase  Connection and country lookup helpers.
 */
class Species
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\FishStat\Traits\FishStatDatabase;

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
        if (!$this->fishStatDb) {
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
        if (!$this->fishStatDb) {
            return $this->emptyPayload($countryName, $year);
        }

        $countryCode = $countryName !== '' ? $this->countryCode($countryName) : null;

        return [
            'country' => $countryName,
            'year' => $year,
            'years' => $this->availableYears(),
            'volume' => $this->speciesRanking('Q_tlw', $year, $countryCode),
            'value' => $this->speciesRanking('V_USD_1000', $year, $countryCode),
            'environment' => $this->environmentSeries($countryCode),
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
            'volume' => ['labels' => [], 'scientific' => [], 'values' => []],
            'value' => ['labels' => [], 'scientific' => [], 'values' => []],
            'environment' => ['labels' => [], 'freshwater' => [], 'brackishwater' => [], 'marine' => []],
        ];
    }

    /**
     * Full species ranking for a measure in a given year, ordered by descending value.
     *
     * Returns every species so the client can both chart the largest contributors (grouping the
     * long tail as "Other") and offer the complete, unaggregated data for CSV download.
     *
     * Value figures are returned in US dollars (the V_USD_1000 measure is stored in thousands).
     *
     * Aggregation is by species code (not common name): many species share an empty English
     * name but have a distinct scientific name, so grouping by name would merge them into a
     * single untitled bar. Where the English name is missing the scientific name is used as the
     * label, falling back to the species code as a last resort.
     *
     * @param   string $measure Measure code: 'Q_tlw' (tonnes) or 'V_USD_1000' (value).
     * @param   int $year Year to aggregate.
     * @param   ?string $countryCode UN country code, or null for the global total.
     * @return  array ['labels' => string[], 'scientific' => string[], 'values' => int[]] by descending value.
     */
    private function speciesRanking(string $measure, int $year, ?string $countryCode): array
    {
        $sql = "SELECT s.alpha_3_code AS code, s.name_en AS name, s.scientific_name AS sci,
                       SUM(p.value) AS total
                FROM aquaculture_production p
                JOIN species s ON p.species_code = s.alpha_3_code
                WHERE p.measure = :measure AND p.period = :year";

        $params = [':measure' => $measure, ':year' => $year];

        if ($countryCode !== null) {
            $sql .= " AND p.country_code = :country_code";
            $params[':country_code'] = $countryCode;
        }

        $sql .= " GROUP BY s.alpha_3_code ORDER BY total DESC";

        $stmt = $this->fishStatDb->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $multiplier = $measure === 'V_USD_1000' ? 1000 : 1;
        $labels = [];
        $scientific = [];
        $values = [];

        foreach ($rows as $row) {
            $name = $this->trimString((string) ($row['name'] ?? ''));
            $sci = $this->trimString((string) ($row['sci'] ?? ''));
            $labels[] = $name !== '' ? $name : ($sci !== '' ? $sci : $row['code']);
            $scientific[] = $sci;
            $values[] = (int) \round((float) $row['total'] * $multiplier);
        }

        return ['labels' => $labels, 'scientific' => $scientific, 'values' => $values];
    }

    /**
     * Production volume (tonnes) by environment, as a yearly time series.
     *
     * @param   ?string $countryCode UN country code, or null for the global total.
     * @return  array ['labels' => int[], 'freshwater' => int[], 'brackishwater' => int[], 'marine' => int[]].
     */
    private function environmentSeries(?string $countryCode): array
    {
        $sql = "SELECT p.period AS year, p.environment_code AS env,
                       CAST(SUM(p.value) AS INTEGER) AS tonnes
                FROM aquaculture_production p
                WHERE p.measure = 'Q_tlw'";

        $params = [];

        if ($countryCode !== null) {
            $sql .= " AND p.country_code = :country_code";
            $params[':country_code'] = $countryCode;
        }

        $sql .= " GROUP BY p.period, p.environment_code ORDER BY p.period";

        $stmt = $this->fishStatDb->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $map = ['IN' => 'freshwater', 'BW' => 'brackishwater', 'MA' => 'marine'];
        $byYear = [];

        foreach ($rows as $row) {
            $year = (int) $row['year'];

            if (!isset($byYear[$year])) {
                $byYear[$year] = ['freshwater' => 0, 'brackishwater' => 0, 'marine' => 0];
            }

            $key = $map[$row['env']] ?? null;

            if ($key !== null) {
                $byYear[$year][$key] = (int) $row['tonnes'];
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
     * Distinct years for which aquaculture volume data exists, most recent first.
     *
     * @return  array List of years (int), descending.
     */
    private function availableYears(): array
    {
        if (!$this->fishStatDb) return [];

        if ($this->yearCache) return $this->yearCache;

        $stmt = $this->fishStatDb->query(
            "SELECT DISTINCT period FROM aquaculture_production WHERE measure = 'Q_tlw' ORDER BY period DESC"
        );

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
