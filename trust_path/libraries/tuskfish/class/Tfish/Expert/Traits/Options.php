<?php

declare(strict_types=1);

namespace Tfish\Expert\Traits;

/**
 * \Tfish\Expert\Traits\Options trait file.
 *
 * @copyright   Simon Wilkinson 2018+(https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 1.0
 * @since       1.0
 * @package     expert
 */

/**
 * Common traits of expert objects and form controls.
 *
 * @copyright   Simon Wilkinson 2018+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 1.0
 * @since       1.0
 * @package     expert
 */
trait Options
{

    /**
     * Returns a whitelist of regions as an array
     *
     * @return array List of regions.
     */
    public function regionList(): array
    {
        return [
            0 => "- Select Region -",
            1 => "Africa",
            2 => "Asia",
            3 => "Europe",
            4 => "Middle East",
            5 => "North America",
            6 => "South America",
        ];
    }

    /**
     * Returns a whitelist of countries as array.
     *
     * @return array List of countries.
     */
    public function countryList(): array
    {
        return [
            0 => TFISH_EXPERTS_SELECT_STATE,
            1 => "Bangladesh",
            2 => "Belgium",
            3 => "Brazil",
            4 => "Denmark",
            5 => "Germany",
            6 => "India",
            7 => "Iran",
            8 => "Italy",
            9 => "Kazakhstan",
            10 => "Kenya",
            11 => "Norway",
            12 => "Russia",
            13 => "Spain",
            14 => "Thailand",
            15 => "United Kingdom",
            16 => "USA",
            17 => "Uzbekistan",
            18 => "Vietnam"
        ];
    }

    /**
     * Returns a whitelist of sectors as an array.
     *
     * @return array
     */
    public function sectorList(): array
    {
        return [
            0 => "- Select Sector -",
            1 => "Cooperative",
            2 => "Equipment vendor",
            3 => "Farming (artisinal)",
            4 => "Farming (commercial)",
            5 => "Feed manufacturer",
            6 => "Government",
            7 => "Harvester",
            8 => "Hatchery",
            9 => "Industry association",
            10 => "NGO",
            11 => "Private/public financiers",
            12 => "Processor",
            13 => "Research",
            14 => "Trader",
        ];
    }

    /**
     * Returns a whitelist of business interests as an array.
     *
     * @return array
     */
    public function businessInterest(): array
    {
        return [
            0 => "- Select business -",
            1 => "Biomass",
            2 => "Climate",
            3 => "Conservation",
            4 => "Cosmetics",
            5 => "Cysts",
            6 => "Development",
            7 => "Economic sustainability",
            8 => "Feed production",
            9 => "Gender",
            10 => "Human food",
            11 => "Processing technology",
            12 => "Youth",
        ];
    }

    public function innovationArea(): array
    {
        return [
            0 => "- Select innovation -",
            1 => "Alternative feed ingredients",
            2 => "Aftemia flakes",
            3 => "Bioactive compounds",
            4 => "Compound feeds",
            5 => "Enrichment diets",
            6 => "Farming systems",
            7 => "Gene (cyst) bank",
            8 => "Hatchery feeds",
            9 => "Human food",
            10 => "Integrated livelihoods",
            11 => "Live Artemia products",
            12 => "Magnetic cyst separation",
            13 => "Networking",
        ];
    }

    /**
     * Returns a whitelist of genders as array.
     *
     * @return array List of genders.
     */
    public function genderList()
    {
        return [
            0 => "Female",
            1 => "Male"
        ];
    }

    /**
     * Returns a whitelist of salutations as array.
     *
     * @return array List of salutations as key => value pairs.
     */
    public function salutationList()
    {
        return [
            0 => TFISH_ZERO_OPTION,
            1 => "Dr",
            2 => "Prof.",
            3 => "Mr",
            4 => "Mrs",
            5 => "Ms"
        ];
    }

}
