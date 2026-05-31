<?php

declare(strict_types=1);

namespace Tfish\Stats\ViewModel;

/**
 * \Tfish\Stats\ViewModel\Species class file.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     Stats
 */

/**
 * ViewModel for the aquaculture species profile page (/species/).
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
class Species implements \Tfish\Interface\Viewable
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
        $this->pageTitle = TFISH_STATS_SPECIES_TITLE;
        $this->description = TFISH_STATS_SPECIES_DESCRIPTION;
    }

    /**
     * Render the species profile template.
     */
    public function displaySpecies(string $country = '', int $year = 0): void
    {
        $this->template = "stats-species";
        $this->model->loadSpeciesData($country, $year);
        $this->buildMetadata([$country, $year]);
    }

    /**
     * Section key for the shared Stats navigation (marks the active tile).
     */
    public function pageKey(): string
    {
        return 'species';
    }

    /**
     * Combined species/environment payload as JSON for the initial page render.
     *
     * @return  string JSON payload.
     */
    public function speciesDataJson(): string
    {
        return \json_encode(
            $this->model->speciesData(),
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
