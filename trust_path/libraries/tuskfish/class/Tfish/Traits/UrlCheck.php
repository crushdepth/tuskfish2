<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\UrlCheck trait file.
 * 
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Validate that a URL meets the specification.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

trait UrlCheck
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
    public function isUrl(string $url): bool
    {
        if (\filter_var($url, FILTER_VALIDATE_URL)) {
            if (\mb_substr($url, 0, 7, 'UTF-8') === 'http://'
                    || \mb_substr($url, 0, 8, 'UTF-8') === 'https://') {
                return true;
            }
        }
        
        return false;
    }
}
