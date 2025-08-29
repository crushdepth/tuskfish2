<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\Group trait file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     user
 */

/**
 * Whitelist of user groups on system.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     user
 */

trait Group
{
    /**
     * Legacy whitelist of user groups permitted on system.
     *
     * If you add any groups to the system you must include them here.
     *
     * @return array Array of user groups with userGroup ID as key.
     */
    public function userGroupList(): array
    {
        return [
            1 => TFISH_USER_SUPER_USER,
            2 => TFISH_USER_EDITOR,
            3 => TFISH_USER_MEMBER,
        ];
    }

    /**
     * ---- New bitmask groups ----
     * One bit per group, extend as required (bits 0...63).
     * These resolve to 2^n to enable bitmask operations.
     */

    public const G_SUPER  = 1 << 0; // 1
    public const G_EDITOR = 1 << 1; // 2
    public const G_MEMBER = 1 << 2; // 4

    /**
     * Overwrite all group privileges with the provided flags.
     *
     * @param int $flags One or more group constants combined with | (e.g. self::G_EDITOR | self::G_MEMBER).
     * @return int New mask containing exactly the specified groups.
     *
     * @example $mask = self::assignGroups(self::G_EDITOR);                       // 2
     * @example $mask = self::assignGroups(self::G_EDITOR | self::G_MEMBER);      // 6
     */
    public static function assignGroups(int $flags): int
    {
        return $flags;
    }

    /**
     * Test if ALL requested flags are set in the mask.
     *
     * @param int $mask Current bitmask value (e.g. from $user->userGroup).
     * @param int $flags One or more group constants combined with |.
     * @return bool True if all specified flags are present; false otherwise.
     *
     * @example self::hasGroup(6, self::G_EDITOR); // true
     * @example self::hasGroup(6, self::G_EDITOR | self::G_MEMBER); // true
     * @example self::hasGroup(6, self::G_EDITOR | self::G_SUPER); // false
     */
    public static function hasGroup(int $mask, int $flags): bool
    {
        return ($mask & $flags) === $flags;
    }
}
