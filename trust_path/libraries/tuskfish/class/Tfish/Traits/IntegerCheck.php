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
     * Check that a value is an int, optionally within an inclusive range.
     *
     * Strict: only a real int passes. Numeric strings ('5') and floats (5.0) return false. Note
     * that an (int) cast before calling this turns any string into an int ('12abc' becomes 12,
     * 'abc' becomes 0), so after a cast only the range is being checked.
     *
     * @param mixed $int Value to test.
     * @param int|null $min Minimum allowed value (inclusive), or null for no minimum.
     * @param int|null $max Maximum allowed value (inclusive), or null for no maximum.
     * @return bool True if $int is an int within the range, otherwise false.
     * @throws \InvalidArgumentException If $min is greater than $max.
     */
    public function isInt(mixed $int, ?int $min = null, ?int $max = null): bool
    {
        if ($min !== null && $max !== null && $min > $max) {
            throw new \InvalidArgumentException('min must be <= max');
        }

        if (!\is_int($int)) {
            return false;
        }

        return ($min === null || $int >= $min) && ($max === null || $int <= $max);
    }
}
