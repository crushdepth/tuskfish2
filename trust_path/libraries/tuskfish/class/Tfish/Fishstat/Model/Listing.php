<?php

declare(strict_types=1);

namespace Tfish\FishStat\Model;

class Listing
{
    use \Tfish\Traits\ValidateString;

    private $database;
    private $preference;
    private $session;
    private \Tfish\Logger $logger;
    private ?\PDO $fishStatDb = null;

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

    public function connect(): bool
    {
        $dbPath = TFISH_DATABASE_PATH . 'aquaculture-fisheries.db';

        if (!\is_file($dbPath)) {
            $this->logger->logError(0, 'FishStat database not found: ' . $dbPath, __FILE__, __LINE__);
            return false;
        }

        try {
            $this->fishStatDb = new \PDO('sqlite:' . $dbPath);
            $this->fishStatDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->fishStatDb->setAttribute(\PDO::ATTR_TIMEOUT, 5);
        } catch (\PDOException $e) {
            $this->logger->logError((int) $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
            return false;
        }

        return true;
    }

    public function getGlobalProductionData(string $speciesCode = ''): array
    {
        if (!$this->fishStatDb) return [];

        $speciesClause = '';
        $captureParams = [':measure' => 'Q_tlw'];
        $aquacultureParams = [':measure' => 'Q_tlw'];

        if ($speciesCode !== '') {
            $speciesClause = ' AND species_code = :species';
            $captureParams[':species'] = $speciesCode;
            $aquacultureParams[':species'] = $speciesCode;
        }

        $captureStmt = $this->fishStatDb->prepare(
            "SELECT period, CAST(SUM(value) AS INTEGER) AS tonnes
             FROM capture_production
             WHERE measure = :measure{$speciesClause}
             GROUP BY period
             ORDER BY period"
        );
        $captureStmt->execute($captureParams);
        $captureRows = $captureStmt->fetchAll(\PDO::FETCH_ASSOC);

        $aquacultureStmt = $this->fishStatDb->prepare(
            "SELECT period, CAST(SUM(value) AS INTEGER) AS tonnes
             FROM aquaculture_production
             WHERE measure = :measure{$speciesClause}
             GROUP BY period
             ORDER BY period"
        );
        $aquacultureStmt->execute($aquacultureParams);
        $aquacultureRows = $aquacultureStmt->fetchAll(\PDO::FETCH_ASSOC);

        $captureByYear = [];
        foreach ($captureRows as $row) {
            $captureByYear[(int)$row['period']] = (int)$row['tonnes'];
        }

        $aquacultureByYear = [];
        foreach ($aquacultureRows as $row) {
            $aquacultureByYear[(int)$row['period']] = (int)$row['tonnes'];
        }

        $allYears = \array_unique(\array_merge(
            \array_keys($captureByYear),
            \array_keys($aquacultureByYear)
        ));
        \sort($allYears);

        $labels = [];
        $capture = [];
        $aquaculture = [];

        foreach ($allYears as $year) {
            $labels[] = $year;
            $capture[] = $captureByYear[$year] ?? 0;
            $aquaculture[] = $aquacultureByYear[$year] ?? 0;
        }

        return [
            'labels' => $labels,
            'capture' => $capture,
            'aquaculture' => $aquaculture,
            'country' => '',
            'species' => $speciesCode,
        ];
    }

    public function getCountryProductionData(string $countryName, string $speciesCode = ''): array
    {
        if (!$this->fishStatDb) return [];

        $captureParams = [':measure' => 'Q_tlw', ':country' => $countryName];
        $aquacultureParams = [':measure' => 'Q_tlw', ':country' => $countryName];
        $captureSpecies = '';
        $aquacultureSpecies = '';

        if ($speciesCode !== '') {
            $captureSpecies = ' AND cp.species_code = :species';
            $aquacultureSpecies = ' AND ap.species_code = :species';
            $captureParams[':species'] = $speciesCode;
            $aquacultureParams[':species'] = $speciesCode;
        }

        $captureStmt = $this->fishStatDb->prepare(
            "SELECT cp.period, CAST(SUM(cp.value) AS INTEGER) AS tonnes
             FROM capture_production cp
             JOIN countries c ON cp.country_code = c.un_code
             WHERE cp.measure = :measure AND c.name_en = :country{$captureSpecies}
             GROUP BY cp.period
             ORDER BY cp.period"
        );
        $captureStmt->execute($captureParams);
        $captureRows = $captureStmt->fetchAll(\PDO::FETCH_ASSOC);

        $aquacultureStmt = $this->fishStatDb->prepare(
            "SELECT ap.period, CAST(SUM(ap.value) AS INTEGER) AS tonnes
             FROM aquaculture_production ap
             JOIN countries c ON ap.country_code = c.un_code
             WHERE ap.measure = :measure AND c.name_en = :country{$aquacultureSpecies}
             GROUP BY ap.period
             ORDER BY ap.period"
        );
        $aquacultureStmt->execute($aquacultureParams);
        $aquacultureRows = $aquacultureStmt->fetchAll(\PDO::FETCH_ASSOC);

        $captureByYear = [];
        foreach ($captureRows as $row) {
            $captureByYear[(int)$row['period']] = (int)$row['tonnes'];
        }

        $aquacultureByYear = [];
        foreach ($aquacultureRows as $row) {
            $aquacultureByYear[(int)$row['period']] = (int)$row['tonnes'];
        }

        $allYears = \array_unique(\array_merge(
            \array_keys($captureByYear),
            \array_keys($aquacultureByYear)
        ));
        \sort($allYears);

        $labels = [];
        $capture = [];
        $aquaculture = [];

        foreach ($allYears as $year) {
            $labels[] = $year;
            $capture[] = $captureByYear[$year] ?? 0;
            $aquaculture[] = $aquacultureByYear[$year] ?? 0;
        }

        return [
            'labels' => $labels,
            'capture' => $capture,
            'aquaculture' => $aquaculture,
            'country' => $countryName,
            'species' => $speciesCode,
        ];
    }

    public function getCountryList(): array
    {
        if (!$this->fishStatDb) return [];

        $stmt = $this->fishStatDb->prepare(
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

    public function loadChartDataForCountry(string $countryName, string $speciesCode = ''): void
    {
        if (!$this->fishStatDb) return;

        $countryName = $this->trimString($countryName);
        $speciesCode = $this->trimString($speciesCode);

        if ($speciesCode !== '') {
            if (!\preg_match('/^[A-Za-z0-9]{3}$/', $speciesCode)) {
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
