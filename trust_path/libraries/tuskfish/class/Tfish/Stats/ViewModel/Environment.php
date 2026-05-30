<?php

declare(strict_types=1);

namespace Tfish\Stats\ViewModel;

/**
 * \Tfish\Stats\ViewModel\Environment class file.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
 * @package     Stats
 */

/**
 * ViewModel for the aquaculture production by environment page (/environment/).
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
class Environment implements \Tfish\Interface\Viewable
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
        $this->pageTitle = "Farming Environments";
    }

    /**
     * Render the environment template.
     */
    public function displayEnvironment(string $country = ''): void
    {
        $this->template = "stats-environment";
        $this->model->loadEnvironmentData($country);
    }

    /**
     * Section key for the shared Stats navigation (marks the active tile).
     */
    public function pageKey(): string
    {
        return 'environment';
    }

    /**
     * Production-by-environment payload as JSON for the initial page render.
     *
     * @return  string JSON payload.
     */
    public function environmentDataJson(): string
    {
        return \json_encode(
            $this->model->environmentData(),
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
