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
     * ---- New bitmask groups ----
     * One bit per group, extend as required (bits 0...63).
     * These resolve to 2^n to enable bitmask operations.
     */

    public const G_SUPER  = 1 << 0; // 1
    public const G_EDITOR = 1 << 1; // 2
    public const G_MEMBER = 1 << 2; // 4

    /**
     * Whitelist of user groups permitted on system.
     *
     * If you add any groups to the system you must also include them here.
     *
     * @return array Array of user groups with userGroup ID as key.
     */
    public function userGroupList(): array
    {
        return [
            self::G_SUPER  => TFISH_USER_SUPER_USER,
            self::G_EDITOR => TFISH_USER_EDITOR,
            self::G_MEMBER => TFISH_USER_MEMBER,
        ];
    }

    /**
     * Check if the user belongs to ANY of the specified groups.
     *
     * @param int $userMask The user's group bitmask (e.g. from $user->userGroup).
     * @param int $flags One or more group constants combined with |, or a route mask from routing table.
     * @return bool True if the user has at least one of the specified groups, false otherwise.
     * @example $this->hasAnyGroup($user->userGroup, self::G_EDITOR | self::G_MEMBER);
     * @example $this->hasAnyGroup($user->userGroup, 2 | 4); // equivalent of above.
     */
    public function hasAnyGroup(int $userMask, int $flags): bool
    {
        return ($userMask & $flags) !== 0;
    }
}
