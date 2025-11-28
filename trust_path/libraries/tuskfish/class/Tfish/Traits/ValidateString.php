<?php

declare(strict_types=1)

namespace Tfish\Traits;

/**
 * \Tfish\Traits\ValidateString trait file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Provides methods for validating UTF-8 character encoding and string composition.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

trait ValidateString
{
    /**
     * URL-encode and escape a query string for use in a URL.
     *
     * Trims, checks for UTF-8 compliance, rawurlencodes and then escapes with htmlspecialchars().
     * If you wish to use the data on a landing page you must decode it with
     * htmlspecialchars_decode() followed by rawurldecode() in that sort. But really, if you are
     * using any characters that need to be encoded in the first place you should probably just
     * stop.
     *
     * @param string $url Unescaped input URL.
     * @return string Encoded and escaped URL.
     */
    public function encodeEscapeUrl(string $url): string
    {
        $url = $this->trimString($url); // Trim control characters, verify UTF-8 character set.
        $url = \rawurlencode($url); // Encode characters to make them URL safe.
        $cleanUrl = \htmlspecialchars($url, ENT_QUOTES, 'UTF-8', false); // Encode entities with htmlspecialchars()

        return $cleanUrl;
    }

    /**
     * Check that a string is comprised solely of alphanumeric characters.
     *
     * Accented regional characters are rejected. This method is designed to be used to check
     * database identifiers or object property names.
     *
     * @param string $alnum Input to be tested.
     * @return bool True if valid alphanumerical string, false otherwise.
     */
    public function isAlnum(string $alnum): bool
    {
        if (\mb_strlen($alnum, 'UTF-8') > 0) {
            return \preg_match('/[^a-z0-9]/i', $alnum) ? false : true;
        }

        return false;
    }

    /**
     * Check that a string is comprised solely of alphanumeric characters and underscores.
     *
     * Accented regional characters are rejected. This method is designed to be used to check
     * database identifiers or object property names.
     *
     * @param string $alnumUnderscore Input to be tested.
     * @return bool True if valid alphanumerical or underscore string, false otherwise.
     */
    public function isAlnumUnderscore(string $alnumUnderscore): bool
    {
        if (\mb_strlen($alnumUnderscore, 'UTF-8') > 0) {
            return \preg_match('/[^a-z0-9_]/i', $alnumUnderscore) ? false : true;
        }

        return false;
    }

    /**
     * Check that a string is comprised solely of alphabetical characters.
     *
     * Tolerates vanilla ASCII only. Accented regional characters are rejected. This method is
     * designed to be used to check database identifiers or object property names.
     *
     * @param string $alpha Input to be tested.
     * @return bool True if valid alphabetical string, false otherwise.
     */
    public function isAlpha(string $alpha): bool
    {
        if (\mb_strlen($alpha, 'UTF-8') > 0) {
            return \preg_match('/[^a-z]/i', $alpha) ? false : true;
        }

        return false;
    }

    /**
     * Check if the character encoding of text is UTF-8.
     *
     * All strings received from external sources must be passed through this function, particularly
     * prior to storage in the database.
     *
     * @param string $text Input string to check.
     * @return bool True if string is UTF-8 encoded otherwise false.
     */
    public function isUtf8(string $text): bool
    {
        return \mb_check_encoding($text, 'UTF-8');
    }

    /**
     * Cast to string, check UTF-8 encoding and strip trailing whitespace and control characters.
     *
     * Removes whitespace and control characters (ASCII <= 32 / UTF-8 points 0-32 inclusive),
     * checks for UTF-8 character set and casts input to a string. Note that the data returned by
     * this function still requires escaping at the point of use; it is not database or XSS safe.
     *
     * As the input is cast to a string do NOT apply this function to non-string types (int, float,
     * bool, object, resource, null, array, etc).
     *
     * @param mixed $text Input to be trimmed.
     * @return string Trimmed and UTF-8 validated string.
     */
    public function trimString($text): string
    {
        $text = (string) $text;

        if ($this->isUtf8($text)) {
            return \trim($text, "\x00..\x20");
        }

        return '';
    }
}
