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
     * Keys are bit flags (powers of two).
     * One bit per group, extend as required (bits 0...63).
     */

    public const G_SUPER  = 1 << 0; // 1
    public const G_EDITOR = 1 << 1; // 2
    public const G_MEMBER = 1 << 2; // 4, next groups should be 8, 16, 32 and so on.

    /**
     * Whitelist of user groups permitted on system.
     *
     * If you add any groups to the system you must also include them here.
     *
     * @return array<int,string> Array of user groups with userGroup ID as key.
     */
    public function listUserGroups(): array
    {
        return [
            self::G_SUPER  => TFISH_USER_SUPER_USER,
            self::G_EDITOR => TFISH_USER_EDITOR,
            self::G_MEMBER => TFISH_USER_MEMBER,
        ];
    }

    /**
     * Redirect targets for different groups on successful login.
     *
     * @return array
     */
    public function groupHomes(): array
    {
        return [
            self::G_SUPER  => TFISH_ADMIN_URL,
            self::G_EDITOR => TFISH_ADMIN_URL,
            self::G_MEMBER => TFISH_URL,
        ];
    }

    /**
     * Compute OR of all valid group bits.
     */
    public function groupsMask(): int
    {
        static $mask = null;

        if ($mask === null) {
            $mask = 0;

            foreach (\array_keys($this->listUserGroups()) as $bit) {
                $mask |= (int) $bit;
            }
        }

        return $mask;
    }

    /**
     * Check if a user may access a CONTENT resource via group bitmask comparison.
     *
     * Public resources ($requiredMask = 0) is always accessible.
     * Super admin and editors bypass checks (access all content, always).
     * Any overlap between a user's groups and authorised groups permits access to resource.
     *
     * Note that editors do NOT have access to all ROUTES, which are handled separately.
     *
     * @param int $userMask
     * @param int $requiredMask
     * @return bool true if allowed, false if denied.
     */
    public function canAccess(int $userMask, int $requiredMask): bool
    {
        if ($requiredMask === 0) return true; // public content/route
        if (($userMask & (self::G_SUPER | self::G_EDITOR)) !== 0) return true; // superuser / editor
        return $this->hasAnyGroup($userMask, $requiredMask); // Validate all others.
    }

    /**
     * Check if user is a member of any authorised group.
     *
     * Return true if any bit overlaps between user and allowed flags.
     * Normalises inputs to the whitelist for safety.
     */
    public function hasAnyGroup(int $userMask, int $allowedMask): bool
    {
        $whitelist = $this->groupsMask();

        // Guard: allowedMask must only use whitelisted bits.
        if (($allowedMask & ~$whitelist) !== 0) {
            throw new \InvalidArgumentException(TFISH_ERROR_INVALID_GROUP);
        }

        // Normalise the user-provided mask to known bits.
        $userMask &= $whitelist;

        return ($userMask & $allowedMask) !== 0;
    }
}
