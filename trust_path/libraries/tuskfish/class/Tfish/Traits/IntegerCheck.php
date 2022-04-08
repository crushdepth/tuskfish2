<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\IntegerCheck trait file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Validate and range check integers.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

trait IntegerCheck
{
    /**
     * Validate integer, optionally include range check.
     *
     * @param mixed $int Input to be tested.
     * @param int $min Minimum acceptable value.
     * @param int $max Maximum acceptable value.
     * @return bool True if valid int and within optional range check, false otherwise.
     */
    public function isInt(int $int, int $min = null, int $max = null): bool
    {
        $cleanInt = \is_int($int) ? $int : null;
        $cleanMin = \is_int($min) ? $min : null;
        $cleanMax = \is_int($max) ? $max : null;

        // Range check on minimum and maximum value.
        if (\is_int($cleanInt) && \is_int($cleanMin) && \is_int($cleanMax)) {
            return ($cleanInt >= $cleanMin) && ($cleanInt <= $cleanMax) ? true : false;
        }

        // Range check on minimum value.
        if (\is_int($cleanInt) && \is_int($cleanMin) && !isset($cleanMax)) {
            return $cleanInt >= $cleanMin ? true : false;
        }

        // Range check on maximum value.
        if (\is_int($cleanInt) && !isset($cleanMin) && \is_int($cleanMax)) {
            return $cleanInt <= $cleanMax ? true : false;
        }

        // Simple use case, no range check.
        if (\is_int($cleanInt) && !isset($cleanMin) && !isset($cleanMax)) {
            return true;
        } else {
            return false;
        }
    }
}
