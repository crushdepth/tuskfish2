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
     * Return the home URL for the highest-privilege group in a user's mask.
     *
     * groupHomes() is ordered by privilege (super, editor, member), so the first matching bit wins.
     * Falls back to the site home page if no known group bit is set.
     *
     * @param int $userMask User's group bitmask.
     * @return string URL.
     */
    public function groupHome(int $userMask): string
    {
        foreach ($this->groupHomes() as $bit => $url) {
            if (($userMask & $bit) !== 0) {
                return $url;
            }
        }

        return TFISH_URL;
    }

    /**
     * Return the OR of all valid group bits.
     *
     * Built from the group constants directly (not from listUserGroups(), whose labels depend on a
     * module language file), so authorisation never depends on language constants being loaded.
     * If you add a group, add its constant here as well as in listUserGroups().
     */
    public function groupsMask(): int
    {
        return self::G_SUPER | self::G_EDITOR | self::G_MEMBER;
    }

    /**
     * Check that a stored user group mask is valid: non-zero, and containing only known group bits.
     *
     * @param int $userMask User's group bitmask.
     * @return bool True if valid, false otherwise.
     */
    public function isValidUserMask(int $userMask): bool
    {
        return $userMask > 0 && ($userMask & ~$this->groupsMask()) === 0;
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
