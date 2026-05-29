<?php

declare(strict_types=1);

namespace Tfish\FishStat\Traits;

/**
 * \Tfish\FishStat\Traits\FishStatDatabase trait file.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     FishStat
 */

/**
 * Shared connection and lookup helpers for the FishStat statistical database.
 *
 * Provides a read-only PDO connection to the FishStat SQLite database (separate from the main
 * Tuskfish site database) along with country lookup helpers reused by FishStat models.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     FishStat
 * @var         ?\PDO $fishStatDb Connection to the FishStat statistical database.
 * @uses        \Tfish\Logger $logger Host class must provide a logger property.
 */
trait FishStatDatabase
{
    private ?\PDO $fishStatDb = null;

    /**
     * Open a read-only connection to the FishStat statistical database.
     *
     * @return  bool True on success, false on failure.
     */
    public function connect(): bool
    {
        $dbPath = TFISH_DATABASE_PATH . TFISH_FISHSTAT_DB;

        if (!\is_file($dbPath)) {
            $this->logger->logError(0, 'FishStat database not found: ' . $dbPath, __FILE__, __LINE__);
            return false;
        }

        try {
            // Open read-only where the driver supports it: this database is reference data the
            // site only ever reads, so the connection should be incapable of writing it even if
            // a future query bug tried to. The open-flags option is part of pdo_sqlite (PHP 7.3+);
            // guard on it so an older/!sqlite driver simply falls back to a normal connection
            // rather than fatalling on an undefined constant.
            $options = [];

            if (\defined('PDO::SQLITE_ATTR_OPEN_FLAGS')) {
                $options[\PDO::SQLITE_ATTR_OPEN_FLAGS] = \PDO::SQLITE_OPEN_READONLY;
            }

            $this->fishStatDb = new \PDO('sqlite:' . $dbPath, null, null, $options);
            $this->fishStatDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->fishStatDb->setAttribute(\PDO::ATTR_TIMEOUT, 5);
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
        if (!$this->fishStatDb) return null;

        $stmt = $this->fishStatDb->prepare(
            "SELECT un_code FROM countries WHERE name_en = :name LIMIT 1"
        );
        $stmt->execute([':name' => $countryName]);
        $code = $stmt->fetchColumn();

        return $code === false ? null : (string) $code;
    }

    /**
     * Return the list of countries that report aquaculture production.
     *
     * @return  array Alphabetical list of country names (name_en).
     */
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
}
