<?php

declare(strict_types=1);

namespace Tfish\Stats\ViewModel;

class Listing implements \Tfish\Interface\Viewable
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
        $this->pageTitle = "Global Fisheries and Aquaculture Statistics";
    }

    public function displayGlobal(string $country = '', string $species = ''): void
    {
        $this->template = "stats-global";
        $this->model->loadChartDataForCountry($country, $species);
    }

    /**
     * Section key for the shared Stats navigation (marks the active tile).
     */
    public function pageKey(): string
    {
        return 'overview';
    }

    public function displayChart(): void {}

    public function chartDataJson(): string
    {
        return \json_encode(
            $this->model->chartData(),
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    public function countryListJson(): string
    {
        return \json_encode(
            $this->model->countries(),
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }
}
