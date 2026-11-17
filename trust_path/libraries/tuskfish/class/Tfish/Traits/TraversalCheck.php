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
 * Checks a filename or path for ".." segments and null bytes (a second line of defence).
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
     * Check if a path contains a ".." segment or a null byte, including in encoded form.
     *
     * ".." counts only as a whole segment between separators (slash or backslash) or the ends of the
     * string, so "..." and "a..b" pass. Up to two rounds of URL and HTML-entity decoding are
     * applied first; anything encoded three or more times is not decoded and passes.
     *
     * Not checked: absolute paths ("/etc/passwd") and stream wrappers ("php://", "phar://").
     * Callers must prefix a fixed base directory, which is what makes those harmless.
     *
     * In general untrusted data should never be used to construct a file path. This method exists
     * as a second line safety measure.
     *
     * @see https://owasp.org/www-community/attacks/Path_Traversal
     *
     * @param string $path Path or filename to check.
     * @return bool True if a ".." segment or null byte is found, otherwise false.
     */
    public function hasTraversalorNullByte(string $path): bool
    {
        // Actual binary NUL => reject immediately
        if (\strpos($path, "\0") !== false) {
            return true;
        }

        // Fast path: if no '..' and no encodings present, it's clean.
        if (\strpos($path, '..') === false
            && \strpos($path, '%') === false
            && \strpos($path, '&') === false) {
            return false;
        }

        // Decode at most twice (covers single/double-encoded payloads).
        $s = $path;
        for ($i = 0; $i < 2; $i++) {
            $changed = false;

            if (\strpos($s, '%') !== false) {
                $d = \rawurldecode($s);
                if ($d !== $s) { $s = $d; $changed = true; }
            }

            if (\strpos($s, '&') !== false) {
                $d = \html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($d !== $s) { $s = $d; $changed = true; }
            }

            if (\strpos($s, "\0") !== false) {
                return true; // NUL surfaced after decoding
            }

            if (!$changed) {
                break; // stabilized before 2 passes
            }
        }

        // Match ".." as a PATH SEGMENT (between start/end or a slash/backslash)
        // If you don't want to consider backslash a separator on Linux, drop "\\\\" below.
        return (bool)\preg_match('/(^|[\/\\\\])\.\.([\/\\\\]|$)/', $s);
    }
}
