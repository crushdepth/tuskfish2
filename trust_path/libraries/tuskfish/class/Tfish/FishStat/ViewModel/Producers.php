<?php

declare(strict_types=1);

namespace Tfish\FishStat\ViewModel;

/**
 * \Tfish\FishStat\ViewModel\Producers class file.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     FishStat
 */

/**
 * ViewModel for the aquaculture producers page (/producers/).
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     FishStat
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\Viewable  Provides standard accessors required by the Viewable interface.
 */
class Producers implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;

    private object $model;
    private \Tfish\Entity\Preference $preference;

    public function __construct(
        object $model,
        \Tfish\Entity\Preference $preference,
    ) {
        $this->model = $model;
        $this->preference = $preference;
        $this->theme = $preference->defaultTheme();
        $this->pageTitle = "Distribution of Aquaculture Production";
    }

    /**
     * Render the producers template.
     */
    public function displayProducers(): void
    {
        $this->template = "fishstat-producers";
        $this->model->displayProducers();
    }

    /**
     * Producers payload as JSON for the initial page render.
     *
     * @return  string JSON payload.
     */
    public function producersDataJson(): string
    {
        return \json_encode(
            $this->model->producersData(),
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
