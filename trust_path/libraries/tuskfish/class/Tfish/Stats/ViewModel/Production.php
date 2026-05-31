<?php

declare(strict_types=1);

namespace Tfish\Stats\ViewModel;

/**
 * \Tfish\Stats\ViewModel\Production class file.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     Stats
 */

/**
 * ViewModel for the aquaculture production page (/production/).
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     Stats
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\Viewable  Provides standard accessors required by the Viewable interface.
 */
class Production implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;
    use \Tfish\Stats\Traits\StatsMetadata;

    private object $model;
    private \Tfish\Entity\Preference $preference;
    private string $dashboardCountry = '';

    public function __construct(
        object $model,
        \Tfish\Entity\Preference $preference,
    ) {
        $this->model = $model;
        $this->preference = $preference;
        $this->theme = $preference->defaultTheme();
        $this->pageTitle = TFISH_STATS_PRODUCTION_TITLE;
        $this->description = TFISH_STATS_PRODUCTION_DESCRIPTION;
    }

    /**
     * Render the production template.
     */
    public function displayProduction(string $species = '', int $year = 0, string $country = ''): void
    {
        $this->template = "stats-production";
        $this->dashboardCountry = $country;
        $this->model->loadProductionData($species, $year);
        $this->buildMetadata([$country, $this->model->speciesName($species), $year]);
    }

    /**
     * Section key for the shared Stats navigation (marks the active tile).
     */
    public function pageKey(): string
    {
        return 'production';
    }

    /**
     * Persisted dashboard country as JSON, for the production-page country highlight.
     *
     * This page ranks countries for a chosen species, so the country is not a filter here; it is
     * carried only to highlight that country in the ranking (or note it has no recorded
     * production). Unvalidated on the server — matched client-side against the ranking labels.
     *
     * @return  string JSON-encoded country name, or '""'.
     */
    public function dashboardCountryJson(): string
    {
        return \json_encode(
            $this->dashboardCountry,
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    /**
     * Production payload as JSON for the initial page render.
     *
     * @return  string JSON payload.
     */
    public function productionDataJson(): string
    {
        return \json_encode(
            $this->model->productionData(),
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    /**
     * Species list as JSON for the species filter.
     *
     * @return  string JSON array of ['code' => string, 'name' => string, 'sci' => string].
     */
    public function speciesListJson(): string
    {
        return \json_encode(
            $this->model->speciesList(),
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }
}
