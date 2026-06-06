<?php

declare(strict_types=1);

namespace Tfish\Stats\ViewModel;

/**
 * \Tfish\Stats\ViewModel\Listing class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 */

/**
 * ViewModel for the Stats landing page (/) — the global overview dashboard.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\Viewable  Provides standard accessors required by the Viewable interface.
 */
class Listing implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;
    use \Tfish\Stats\Traits\StatsMetadata;

    private object $model;
    private \Tfish\Entity\Preference $preference;

    public function __construct(
        object $model,
        \Tfish\Entity\Preference $preference,
    ) {
        $this->model = $model;
        $this->preference = $preference;
        $this->theme = $preference->defaultTheme();
        $this->layout = 'layoutStats';
        $this->modulePath = TFISH_STATS_TEMPLATE_PATH;
        $this->pageTitle = TFISH_STATS_OVERVIEW_TITLE;
        $this->description = TFISH_STATS_OVERVIEW_DESCRIPTION;
    }

    /**
     * Render the global overview template.
     *
     * @param   string $country Optional member state to focus the dashboard on.
     * @param   string $species Optional species code to filter the dashboard by.
     */
    public function displayGlobal(string $country = '', string $species = ''): void
    {
        $this->template = "stats-global";
        $this->model->loadChartDataForCountry($country, $species);
        $this->buildMetadata([$country, $this->model->speciesName($species)]);
    }

    /**
     * Section key for the shared Stats navigation (marks the active tile).
     */
    public function pageKey(): string
    {
        return 'global';
    }

    /**
     * No-op required by the Viewable interface; the page renders its chart client-side.
     */
    public function displayChart(): void {}

    /**
     * Production chart payload as JSON for the initial page render.
     *
     * @return  string JSON payload.
     */
    public function chartDataJson(): string
    {
        return \json_encode(
            $this->model->chartData(),
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    /**
     * Country list as JSON for the state filter.
     *
     * @return  string JSON array of country names.
     */
    public function countryListJson(): string
    {
        return \json_encode(
            $this->model->countries(),
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }
}
