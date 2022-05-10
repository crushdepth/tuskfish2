<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\EmailCheck trait file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Validate that email address conforms to specification.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

trait EmailCheck
{
    /**
     * Check if an email address is valid.
     *
     * Note that valid email addresses can contain database-unsafe characters such as single quotes.
     *
     * @param string $email Input to be tested.
     * @return bool True if a valid email address, otherwise false.
     */
    public function isEmail(string $email)
    {
        if (\mb_strlen($email, 'UTF-8') > 2) {
            return \filter_var($email, FILTER_VALIDATE_EMAIL);
        } else {
            return false;
        }
    }
}
