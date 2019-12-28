<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\Rights trait file.
 * 
 * Provides a common list of intellectual property rights licenses.
 * 
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       1.1
 * @package     core
 */

/**
 * Provides a common list of intellectual property rights licenses.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.1
 * @package     core
 */

trait Rights
{
    /**
     * Returns a list of intellectual property rights licenses for the content submission form.
     * 
     * In the interests of brevity and sanity, a comprehensive list is not provided. Add entries
     * that you want to use to the array below. Be aware that deleting entries that are in use by
     * your content objects will cause errors.
     * 
     * @return array Array of copyright licenses.
     */
    public function listRights(): array
    {
        return [
            '1' => TFISH_RIGHTS_COPYRIGHT,
            '2' => TFISH_RIGHTS_ATTRIBUTION,
            '3' => TFISH_RIGHTS_ATTRIBUTION_SHARE_ALIKE,
            '4' => TFISH_RIGHTS_ATTRIBUTION_NO_DERIVS,
            '5' => TFISH_RIGHTS_ATTRIBUTION_NON_COMMERCIAL,
            '6' => TFISH_RIGHTS_ATTRIBUTION_NON_COMMERCIAL_SHARE_ALIKE,
            '7' => TFISH_RIGHTS_ATTRIBUTION_NON_COMMERCIAL_NO_DERIVS,
            '8' => TFISH_RIGHTS_GPL2,
            '9' => TFISH_RIGHTS_GPL3,
            '10' => TFISH_RIGHTS_PUBLIC_DOMAIN,
        ];
    }
}
