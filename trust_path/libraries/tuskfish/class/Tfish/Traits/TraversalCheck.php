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
    /**
     * True if $path contains a traversal ("..") segment or a null byte.
     * Handles %XX and HTML entities with up to TWO decode passes.
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
