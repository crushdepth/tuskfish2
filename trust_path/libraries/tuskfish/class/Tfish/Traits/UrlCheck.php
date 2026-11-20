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
 * Check that a string is an absolute http:// or https:// URL (format only).
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
     * Check that a string is a normal absolute http:// or https:// URL.
     *
     * Passes only if all of these hold:
     * - PHP's FILTER_VALIDATE_URL accepts it.
     * - The scheme is lower-case 'http' or 'https'.
     * - It has a host.
     * - It has no user info (http://name@host/), which can disguise the real host.
     * - It contains only characters RFC 3986 allows (letters, digits and - . _ ~ : / ? # [ ] @
     *   ! $ & ' ( ) * + , ; =) and well-formed percent-encodings (% plus two hex digits). Spaces,
     *   control characters, non-ASCII and < > " \ ^ ` { | } must be percent-encoded.
     *
     * Not checked: where the URL points (localhost and IP addresses pass), so this is not an
     * open-redirect guard. Non-ASCII domain names must use the xn-- form. Still escape on output,
     * as ' & and ; are legal URL characters.
     *
     * @param string $url Input to be tested.
     * @return bool True if $url is a valid http(s) URL, otherwise false.
     */
    public function isUrl(string $url): bool
    {
        if (\filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        // Only RFC 3986 characters, and every % must start a %XX escape. A regex error counts as a
        // failure.
        if (\preg_match('/^[A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]+$/D', $url) !== 1
                || \preg_match('/%(?![0-9A-Fa-f]{2})/', $url) !== 0) {
            return false;
        }

        $parts = \parse_url($url);

        if ($parts === false) {
            return false;
        }

        return \in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            && !empty($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }
}
