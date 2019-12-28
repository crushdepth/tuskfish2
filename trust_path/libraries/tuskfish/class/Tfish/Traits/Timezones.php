<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\Timezones trait file.
 * 
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Provides an array of time zones.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

trait Timezones
{
    /**
     * Provide a list of timezone offsets.
     * 
     * @return array Array of timezone offsets.
     */
    public function listTimezones(): array
    {
        return [
            '-12' => 'UTC-12:00',
            '-11' => 'UTC-11:00',
            '-10' => 'UTC-10:00',
            '-9.5' => 'UTC-9:30',
            '-9' => 'UTC-9:00',
            '-8' => 'UTC-8:00',
            '-7' => 'UTC-7:00',
            '-6' => 'UTC-6:00',
            '-5' => 'UTC-5:00',
            '-4' => 'UTC-4:00',
            '-3.5' => 'UTC-3:30',
            '-3' => 'UTC-3:00',
            '-2' => 'UTC-2:00',
            '-1' => 'UTC-1:00',
            '0' => 'UTC',
            '1' => 'UTC+1:00',
            '2' => 'UTC+2:00',
            '3' => 'UTC+3:00',
            '3.5' => 'UTC+3:30',
            '4' => 'UTC+4:00',
            '4.5' => 'UTC+4:30',
            '5' => 'UTC+5:00',
            '5.5' => 'UTC+5:30',
            '5.75' => 'UTC+5:45',
            '6' => 'UTC+6:00',
            '6.5' => 'UTC+6:30',
            '7' => 'UTC+7:00',
            '8' => 'UTC+8:00',
            '8.75' => 'UTC+8:45',
            '9' => 'UTC+9:00',
            '9.5' => 'UTC+9:30',
            '10' => 'UTC+10:00',
            '10.5' => 'UTC+10:30',
            '11' => 'UTC+11:00',
            '12' => 'UTC+12:00',
        ];
    }
}
