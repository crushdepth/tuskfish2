<?php

declare(strict_types=1);

namespace Tfish\Stats\ViewModel;

/**
 * \Tfish\Stats\ViewModel\FoodSecurity class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 */

/**
 * ViewModel for the food security page (/insecurity/).
 *
 * Renders a single global choropleth of the prevalence of moderate or severe food insecurity (SDG
 * indicator 2.1.2, percentage of population). As with the consumption map, the country, species and
 * year dimensions have no effect on this map; they are accepted only as pass-through state so that a
 * selection made on another tab survives a visit here and is carried back. They are therefore
 * deliberately NOT appended to the page title (buildMetadata is called with no filters): the
 * canonical URL stays /insecurity/ and the title never implies country-specific data.
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
class FoodSecurity implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;
    use \Tfish\Stats\Traits\StatsMetadata;

    private object $model;
    private \Tfish\Entity\Preference $preference;
    private array $carriedState = ['country' => '', 'species' => '', 'year' => ''];

    public function __construct(
        object $model,
        \Tfish\Entity\Preference $preference,
    ) {
        $this->model = $model;
        $this->preference = $preference;
        $this->theme = $preference->defaultTheme();
        $this->layout = 'layoutStats';
        $this->pageTitle = TFISH_STATS_FOODSECURITY_TITLE;
        $this->description = TFISH_STATS_FOODSECURITY_DESCRIPTION;
    }

    /**
     * Render the food security template.
     *
     * @param   string $country Persisted member state (pass-through; does not filter this map).
     * @param   string $species Persisted species code (pass-through).
     * @param   string $year Persisted year (pass-through).
     */
    public function displayFoodSecurity(string $country = '', string $species = '', string $year = ''): void
    {
        $this->template = "stats-food-security";
        $this->carriedState = [
            'country' => $this->trimString($country),
            'species' => $this->trimString($species),
            'year' => $this->trimString($year),
        ];
        $this->model->loadFoodSecurityData($country);
        $this->buildMetadata(); // No filters: see class note.
    }

    /**
     * Section key for the shared Stats navigation (marks the active tile).
     */
    public function pageKey(): string
    {
        return 'insecurity';
    }

    /**
     * Persisted selection to carry across the section nav (country/species/year).
     *
     * @return  array ['country' => string, 'species' => string, 'year' => string].
     */
    public function carriedState(): array
    {
        return $this->carriedState;
    }

    /**
     * Reference year of the current snapshot, for display in the template (title, note, aria-label).
     *
     * @return  int Four-digit year.
     */
    public function referenceYear(): int
    {
        return $this->model->referenceYear();
    }

    /**
     * Food security payload as JSON for the initial page render.
     *
     * @return  string JSON array of ['id' => int, 'name' => string, 'pct' => float].
     */
    public function foodSecurityDataJson(): string
    {
        return \json_encode(
            $this->model->foodSecurityData(),
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    /**
     * Country list as JSON for the state filter (reserved for the dynamic version).
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
