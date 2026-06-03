<?php

declare(strict_types=1);

namespace Tfish\Stats\Model;

/**
 * \Tfish\Stats\Model\Trade class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 */

/**
 * Model for the trade page (/trade/).
 *
 * Produces a yearly time series of the two reported trade flows (imports, exports) for both
 * volume (tonnes product weight) and value (US dollars), for the global picture or a single member
 * state. The global (unfiltered) picture reads the pre-aggregated global_trade_summary table — a
 * live GROUP BY over the ~2.5M-row trade table takes ~7 seconds, so this table is not optional — and
 * falls back to the v_trade_global_yearly view when it is absent (slow-but-correct). Single-country
 * views read the pre-aggregated country_trade_summary table for the same reason — live aggregation
 * of the v_trade_country_yearly view runs ~60 ms per request (~15x the summary path) — and fall back
 * to that view when the table is absent. See sql/README.md and sql/*_trade_summary.sql for the
 * rebuild runbook.
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
class Trade
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Stats\Traits\StatsDatabase;

    private $database;
    private $preference;
    private $session;
    private \Tfish\Logger $logger;

    private array $tradeData = [];
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
     * Return the most recently built payload.
     *
     * @return  array Combined trade chart payload.
     */
    public function tradeData(): array
    {
        return $this->tradeData;
    }

    /**
     * Build a payload for the supplied country, validating the input.
     *
     * Falls back to the global picture for an empty or unknown country, so the page always renders
     * something sensible.
     *
     * @param   string $countryName English country name, or '' for the global picture.
     */
    public function loadTradeData(string $countryName): void
    {
        if (!$this->statsDb) {
            $this->tradeData = $this->emptyPayload('');
            return;
        }

        $countryName = $this->trimString($countryName);

        if ($countryName !== '' && !\in_array($countryName, $this->getTradeCountryList(), true)) {
            $countryName = '';
        }

        $countryCode = $countryName !== '' ? $this->countryCode($countryName) : null;
        $this->tradeData = $this->buildPayload($countryCode, $countryName);
    }

    /**
     * Assemble the combined payload for a (validated) country.
     *
     * The global (unfiltered) case reads the pre-aggregated global_trade_summary table; without it
     * a GROUP BY over the ~2.5M-row trade table takes ~7 seconds, so this is not optional for a
     * responsive page. It falls back to the v_trade_global_yearly view when the summary is absent,
     * so the page degrades to slow-but-correct rather than failing. Country cases read the
     * pre-aggregated country_trade_summary table filtered by UN code, falling back to the
     * v_trade_country_yearly view when that table is absent (slow-but-correct). All sources express
     * value in full US dollars and volume in tonnes product weight, so the rows pivot straight into
     * the per-flow arrays the charts consume.
     *
     * @param   ?string $countryCode UN country code, or null for the global total.
     * @param   string $countryName Display name to echo back in the payload (or '' for global).
     * @return  array Combined trade chart payload.
     */
    private function buildPayload(?string $countryCode, string $countryName = ''): array
    {
        if (!$this->statsDb) {
            return $this->emptyPayload($countryName);
        }

        if ($countryCode === null) {
            $stmt = $this->hasSummaryTable('global_trade_summary')
                ? $this->statsDb->query(
                    "SELECT period AS year, flow_code, quantity_tonnes_pw, value_usd
                     FROM global_trade_summary WHERE flow_code IN ('I', 'E') ORDER BY period")
                : $this->statsDb->query(
                    "SELECT year, flow_code, quantity_tonnes_pw, value_usd
                     FROM v_trade_global_yearly WHERE flow_code IN ('I', 'E') ORDER BY year");
        } else {
            $stmt = $this->hasSummaryTable('country_trade_summary')
                ? $this->statsDb->prepare(
                    "SELECT period AS year, flow_code, quantity_tonnes_pw, value_usd
                     FROM country_trade_summary
                     WHERE country_code = :country_code AND flow_code IN ('I', 'E') ORDER BY period")
                : $this->statsDb->prepare(
                    "SELECT year, flow_code, quantity_tonnes_pw, value_usd
                     FROM v_trade_country_yearly
                     WHERE country_code = :country_code AND flow_code IN ('I', 'E') ORDER BY year");
            $stmt->execute([':country_code' => $countryCode]);
        }

        return $this->pivotTradeRows($stmt->fetchAll(\PDO::FETCH_ASSOC), $countryName);
    }

    /**
     * Pivot trade-flow rows into per-flow arrays aligned to a shared list of years.
     *
     * Each source row carries one (year, flow) combination with both its volume and value. The
     * imports (I) and exports (E) flows are spread into parallel arrays; reexports (R, unreliable
     * source data) and processed production (P) are ignored — and the read queries already filter
     * to I/E, so they should not even arrive here. Years with no row for a given flow are zero-filled
     * so all arrays share the same length and index as the labels.
     *
     * @param   array $rows List of ['year' => int, 'flow_code' => string, 'quantity_tonnes_pw' => float, 'value_usd' => float].
     * @param   string $countryName Display name to echo back (or '' for global).
     * @return  array Combined trade chart payload.
     */
    private function pivotTradeRows(array $rows, string $countryName): array
    {
        $map = ['I' => 'imports', 'E' => 'exports'];
        $byYear = [];

        foreach ($rows as $row) {
            $key = $map[(string) $row['flow_code']] ?? null;

            if ($key === null) {
                continue;
            }

            $year = (int) $row['year'];

            if (!isset($byYear[$year])) {
                $byYear[$year] = [
                    'imports' => 0, 'exports' => 0,
                    'imports_value' => 0, 'exports_value' => 0,
                ];
            }

            $byYear[$year][$key] = (int) \round((float) $row['quantity_tonnes_pw']);
            $byYear[$year][$key . '_value'] = (int) \round((float) $row['value_usd']);
        }

        \ksort($byYear);

        $payload = $this->emptyPayload($countryName);

        foreach ($byYear as $year => $vals) {
            $payload['labels'][] = $year;
            $payload['imports'][] = $vals['imports'];
            $payload['exports'][] = $vals['exports'];
            $payload['imports_value'][] = $vals['imports_value'];
            $payload['exports_value'][] = $vals['exports_value'];
        }

        return $payload;
    }

    /**
     * Empty payload used as the array template and when the database is unavailable.
     *
     * @param   string $countryName Country name (or '' for global).
     * @return  array Empty trade chart payload.
     */
    private function emptyPayload(string $countryName): array
    {
        return [
            'labels' => [],
            'imports' => [],
            'exports' => [],
            'imports_value' => [],
            'exports_value' => [],
            'country' => $countryName,
        ];
    }

    /**
     * Populate the country list for the page (member-state filter).
     */
    public function loadCountryList(): void
    {
        $this->countryList = $this->getTradeCountryList();
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
