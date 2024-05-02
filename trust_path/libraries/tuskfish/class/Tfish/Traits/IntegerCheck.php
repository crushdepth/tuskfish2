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
     * @param int $int Input to be tested.
     * @param int|null $min Minimum acceptable value.
     * @param int|null $max Maximum acceptable value.
     * @return bool True if valid int and within optional range check, false otherwise.
     */
    public function isInt(int $int, ?int $min = null, ?int $max = null): bool
    {
        $int = \is_int($int) ? $int : null;
        $min = \is_int($min) ? $min : null;
        $max = \is_int($max) ? $max : null;

        // If input is not an integer, return false.
        if (!\is_int($int)) {
            return false;
        }

        // Perform range check if both min and max are provided.
        if (\is_int($min) && \is_int($max)) {
            return $int >= $min && $int <= $max;
        }

        // Perform minimum value check if only min is provided.
        if (\is_int($min)) {
            return $int >= $min;
        }

        // Perform maximum value check if only max is provided.
        if (\is_int($max)) {
            return $int <= $max;
        }

        // If neither min nor max is provided, return true.
        return true;
    }
}
