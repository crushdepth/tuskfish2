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
     * @param mixed $min Minimum acceptable value.
     * @param mixed $max Maximum acceptable value.
     * @return bool True if valid int and within optional range check, false otherwise.
     */
    public function isInt(mixed $int, mixed $min = null, mixed $max = null): bool
    {
        if (!\is_int($int)) {
            return false;
        }

        if ($min !== null && (!\is_int($min) || $int < $min)) {
            return false;
        }

        if ($max !== null && (!\is_int($max) || $int > $max)) {
            return false;
        }

        // Detect programmer error.
        if (\is_int($min) && \is_int($max) && $min > $max) {
            throw new \InvalidArgumentException('min must be <= max');
        }

        return true;
    }
}
