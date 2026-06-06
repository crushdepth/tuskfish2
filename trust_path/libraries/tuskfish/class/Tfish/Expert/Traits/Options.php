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
     * Returns a whitelist of countries as array.
     *
     * @return array List of countries.
     */
    public function countryList()
    {
        return [
            0 => TFISH_EXPERTS_SELECT_STATE,
            1 => "Australia",
            2 => "Bangladesh",
            3 => "Cambodia",
            4 => "China",
            5 => "Hong Kong SAR (China)",
            6 => "India",
            7 => "Indonesia",
            8 => "I.R. Iran",
            9 => "Lao PDR",
            10 => "Malaysia",
            11 => "Maldives",
            12 => "Myanmar",
            13 => "Nepal",
            14 => "Pakistan",
            15 => "Philippines",
            16 => "Sri Lanka",
            17 => "Thailand",
            18 => "Vietnam"
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
