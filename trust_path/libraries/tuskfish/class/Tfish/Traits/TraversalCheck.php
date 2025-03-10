<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\TraversalCheck trait file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Validates that a filename or path does NOT contain directory traversals in any form.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

trait TraversalCheck
{
    /**
     * Check if a file path contains traversals (including encoded traversals) or null bytes.
     *
     * Directory traversals are not permitted in Tuskfish method parameters. If a path is found to
     * contain a traversal it is presumed to be an attack. Encoded traversals are a clear sign of
     * attempted abuse.
     *
     * In general untrusted data should never be used to construct a file path. This method exists
     * as a second line safety measure.
     *
     * @see https://www.owasp.org/index.php/Path_Traversal.
     *
     * @param string $path
     * @return boolean True if a traversal or null byte is found, otherwise false.
     */
    public function hasTraversalorNullByte(string $path): bool
    {
        // List of traversals and null byte encodings.
        $traversals = [
            "../",
            "..\\",
            "%2e%2e%2f", // Represents ../
            "%2e%2e/", // Represents ../
            "..%2f", // Represents ../
            "%2e%2e%5c", // Represents ..\
            "%2e%2e", // Represents ..\
            "..%5c", // Represents ..\
            "%252e%252e%255c", // Represents ..\
            "%252e%252e%252f", // Double URL-encoded traversal sequence
            "..%255c", // Represents ..\
            "..%c0%af", // Represents ../ (URL encoding)
            "..%c1%9c", // Represents ..\
            "%00", // URL-encoded null byte filename terminator.
            "\0", // C-style null byte (PHP functions are written in C).
            "\x00", // Hexadecimal representation of null byte.
            "0x00", // Hex-encoded null byte.
            "\000", // Octal representation of null byte.
            "chr(0)", // PHP function that returns a null byte character.
            "\u{0000}", // Represents a null byte using Unicode code point notation.
            "&#0;", // Represents a null byte using HTML entity encoding.
            "AA==", //Represents a null byte in Base64 encoding.
        ];

        // Search the path for traversals.
        foreach ($traversals as $traverse) {
            if (\mb_strripos($path, $traverse, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        // No traversals found.
        return false;
    }
}
