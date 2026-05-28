<?php

declare(strict_types=1);

namespace Tfish\FishStat\ViewModel;

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

    public function displayGlobal(): void
    {
        $this->template = "fishstat-global";
        $this->model->displayGlobal();
    }

    public function displayChart(): void {}

    public function chartDataJson(): string
    {
        return \json_encode($this->model->chartData(), JSON_THROW_ON_ERROR);
    }

    public function countryListJson(): string
    {
        return \json_encode($this->model->countries(), JSON_THROW_ON_ERROR);
    }
}
