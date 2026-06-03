<?php

declare(strict_types=1);

namespace Tfish\Stats\Model;

/**
 * \Tfish\Stats\Model\Environment class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 */

/**
 * Model for the aquaculture production by environment page (/environment/).
 *
 * Produces the production-by-environment time series (volume in tonnes and value in US dollars)
 * for the global picture or for a single member state.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Stats\Traits\StatsDatabase  Connection, country and environment-series helpers.
 */
class Environment
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Stats\Traits\StatsDatabase;

    private $database;
    private $preference;
    private $session;
    private \Tfish\Logger $logger;

    private array $environmentData = [];
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
     * Load the default payload (global picture).
     */
    public function displayEnvironment(): void
    {
        $this->environmentData = $this->buildPayload('');
    }

    /**
     * Return the most recently built payload.
     *
     * @return  array Combined environment chart payload.
     */
    public function environmentData(): array
    {
        return $this->environmentData;
    }

    /**
     * Build a payload for the supplied country, validating the input.
     *
     * Falls back to the global picture for an unknown country, so the page always renders
     * something sensible.
     *
     * @param   string $countryName English country name, or '' for the global picture.
     */
    public function loadEnvironmentData(string $countryName): void
    {
        if (!$this->statsDb) {
            $this->environmentData = $this->emptyPayload($countryName);
            return;
        }

        $countryName = $this->trimString($countryName);

        if ($countryName !== '' && !\in_array($countryName, $this->getCountryList(), true)) {
            $countryName = '';
        }

        $this->environmentData = $this->buildPayload($countryName);
    }

    /**
     * Assemble the combined payload for a (validated) country.
     *
     * @param   string $countryName Validated country name, or '' for global.
     * @return  array Combined environment chart payload.
     */
    private function buildPayload(string $countryName): array
    {
        if (!$this->statsDb) {
            return $this->emptyPayload($countryName);
        }

        $countryCode = $countryName !== '' ? $this->countryCode($countryName) : null;

        return [
            'country' => $countryName,
            'environment' => $this->environmentSeries($countryCode, 'Q_tlw'),
            'environmentValue' => $this->environmentSeries($countryCode, 'V_USD_1000'),
        ];
    }

    /**
     * Empty payload used when the database is unavailable.
     *
     * @param   string $countryName Country name.
     * @return  array Empty environment chart payload.
     */
    private function emptyPayload(string $countryName): array
    {
        return [
            'country' => $countryName,
            'environment' => ['labels' => [], 'freshwater' => [], 'brackishwater' => [], 'marine' => []],
            'environmentValue' => ['labels' => [], 'freshwater' => [], 'brackishwater' => [], 'marine' => []],
        ];
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
