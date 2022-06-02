<?php

declare(strict_types=1);

namespace Tfish\User\Traits;

/**
 * \Tfish\User\Traits\UserGroup trait file.
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

trait UserGroup
{
    /**
     * Whitelist of user groups permitted on system.
     *
     * If you add any groups to the system you must include them here.
     *
     * @param string $url Input to be tested.
     * @return array Array of user groups with userGroup ID as key.
     */
    public function userGroupList(): array
    {
        return [
            1 => TFISH_USER_SUPER_USER,
            2 => TFISH_USER_EDITOR,
        ];
    }
}
