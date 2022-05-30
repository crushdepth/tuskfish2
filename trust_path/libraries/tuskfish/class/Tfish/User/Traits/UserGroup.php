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
     * Validate URL.
     *
     * Only accepts http:// and https:// protocol and ASCII characters. Other protocols
     * and internationalised domain names will fail validation due to limitation of filter.
     *
     * @param string $url Input to be tested.
     * @return bool True if valid URL otherwise false.
     */
    public function userGroupList(): array
    {
        return [
            1 => TFISH_USER_SUPER_USER,
            2 => TFISH_USER_EDITOR,
        ];
    }
}
