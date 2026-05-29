<?php

declare(strict_types=1);

namespace Tfish\FishStat\Model;

class Listing
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\FishStat\Traits\FishStatDatabase;

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

    public function displayGlobal(): void
    {
        $this->chartData = $this->getGlobalProductionData();
    }

    public function chartData(): array
    {
        return $this->chartData;
    }

    public function getGlobalProductionData(string $speciesCode = ''): array
    {
        if (!$this->fishStatDb) return [];

        if ($speciesCode === '') {
            return $this->getGlobalSummary();
        }

        return $this->getGlobalProductionBySpecies($speciesCode);
    }

    private function getGlobalSummary(): array
    {
        $stmt = $this->fishStatDb->prepare(
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

    private function getGlobalProductionBySpecies(string $speciesCode): array
    {
        $speciesClause = ' AND species_code = :species';

        $captureStmt = $this->fishStatDb->prepare(
            "SELECT period, CAST(SUM(value) AS INTEGER) AS tonnes
             FROM capture_production
             WHERE measure = :measure{$speciesClause}
             GROUP BY period
             ORDER BY period"
        );
        $captureStmt->execute([':measure' => 'Q_tlw', ':species' => $speciesCode]);
        $captureRows = $captureStmt->fetchAll(\PDO::FETCH_ASSOC);

        $aquaStmt = $this->fishStatDb->prepare(
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

    public function getCountryProductionData(string $countryName, string $speciesCode = ''): array
    {
        if (!$this->fishStatDb) return [];

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

        $captureStmt = $this->fishStatDb->prepare(
            "SELECT period, CAST(SUM(value) AS INTEGER) AS tonnes
             FROM capture_production
             WHERE measure = :measure AND country_code = :country_code{$captureSpecies}
             GROUP BY period
             ORDER BY period"
        );
        $captureStmt->execute($captureParams);
        $captureRows = $captureStmt->fetchAll(\PDO::FETCH_ASSOC);

        $aquaStmt = $this->fishStatDb->prepare(
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

    public function loadChartDataForCountry(string $countryName, string $speciesCode = ''): void
    {
        if (!$this->fishStatDb) return;

        $countryName = $this->trimString($countryName);
        $speciesCode = $this->trimString($speciesCode);

        if ($speciesCode !== '') {
            if (!\preg_match('/^[A-Z]{3}$/', $speciesCode)) {
                $speciesCode = '';
            } else {
                $checkStmt = $this->fishStatDb->prepare(
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

    public function loadCountryList(): void
    {
        $this->countryList = $this->getCountryList();
    }

    public function countries(): array
    {
        return $this->countryList;
    }

}
