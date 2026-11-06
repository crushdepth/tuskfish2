<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\Cache class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     core
 */

/**
 * Handles page-level caching operations.
 *
 * Cached pages are written to the private cache directory(trust_path/cache). The cache can be
 * enabled / disabled and a expiry timer set in Tuskfish preferences.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     core
 * @uses        trait \Tfish\Traits\TraversalCheck	Validates that a filename or path does NOT contain directory traversals in any form.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Entity\Preference $preference An instance of the Tuskfish site preferences class.
 * @var         string $path URL path associated with this request.
 *
 */
class Cache
{
    use Traits\TraversalCheck;
    use Traits\ValidateString;

    private $preference;
    private string $path;

    /**
     * Content types that may be cached and replayed.
     *
     * The Content-Type of each page is stored as the first line of its cache file and re-sent on
     * a cache hit, because the layout that originally set it is not executed for cached pages.
     * Only types on this list are cached or replayed. Add new cacheable output types here
     * deliberately.
     */
    private const CACHEABLE_TYPES = [
        'text/html; charset=utf-8',
        'application/rss+xml; charset=utf-8',
    ];

    private const DEFAULT_TYPE = 'text/html; charset=utf-8';
    private const TYPE_PREFIX = 'Content-Type: ';

    /**
     * Constructor.
     *
     * @param \Tfish\Entity\Preference $preference An instance of the Tuskfish site preferences class.
     */
    function __construct(Entity\Preference $preference)
    {
        $this->preference = $preference;
    }

    /**
     * Check if a cached page exists and has not expired, and displays it.
     *
     * You should only pass in parameters that you were expecting and had explicitly whitelisted
     * and have already validated. Gating the parameters in this way reduces the opportunity for
     * exploitation.
     *
     * If a cached page is not available controller script execution will simply proceed and the
     * FrontController will request the page be written to cache, assuming that caching is enabled.
     *
     * A call to check() should ALWAYS precede a call to save() in sort to set the path variable.
     *
     * @param string $path Path segment of requested URL, eg. parse_url($url, PHP_URL_PATH).
     * @param array $params URL Query string parameters for this page as $key => $value pairs.
     * @return bool Return cached page if exists, otherwise false.
     */
    public function check(string $path, array $params): bool
    {
        // Abort if cache is disabled or params are empty.
        if (!$this->preference->enableCache() || empty($params)) {
            return false;
        }

        $this->setPath($path);

        // Resolve the file name.
        $fileName = $this->_getCachedFileName($params);

        if ($fileName === false) {
            return false;
        }

        // Verify that the constructed path matches the canonical path. Exit cache if path is bad.
        $resolvedPath = $this->standardise(\realpath(TFISH_PRIVATE_CACHE_PATH) . '/' . $fileName);
        $canonical = $this->standardise(TFISH_PRIVATE_CACHE_PATH . $fileName);

        if ($resolvedPath !== $canonical) {
            return false;
        }

        // Check if the file actually exist and has not expired, flush output buffer to screen.
        if (\file_exists($canonical) && (\filemtime($canonical) >
                (\time() - $this->preference->cacheLife()))) {
            $contents = @\file_get_contents($canonical);

            // Treat unreadable or empty files as a cache miss.
            if ($contents === false || $contents === '') {
                return false;
            }

            echo $this->replayContentType($contents);
            \ob_end_flush();
            exit;
        }

        return false;
    }

    /**
     * Re-send the Content-Type stored in a cache file and return the page body.
     *
     * The first line of a cache file holds the Content-Type of the cached page. Only allowlisted
     * types are replayed; anything else falls back to text/html. Legacy cache files without a
     * stored type are returned unchanged.
     *
     * @param string $contents Raw contents of the cache file.
     * @return string Page body.
     */
    private function replayContentType(string $contents): string
    {
        if (!\str_starts_with($contents, self::TYPE_PREFIX)) {
            return $contents; // Legacy cache file.
        }

        [$firstLine, $body] = \explode("\n", $contents, 2) + [1 => ''];
        $type = \strtolower(\trim(\substr($firstLine, \strlen(self::TYPE_PREFIX))));

        if (!\in_array($type, self::CACHEABLE_TYPES, true)) {
            $type = self::DEFAULT_TYPE;
        }

        \header(self::TYPE_PREFIX . $type);

        return $body;
    }

    /**
     * Return the Content-Type set for the current response.
     *
     * header.php sets text/html globally and layouts may override it (eg. RSS), so the last
     * Content-Type in the header list is the one that will be sent.
     *
     * @return string Lowercased Content-Type value.
     */
    private function currentContentType(): string
    {
        $type = self::DEFAULT_TYPE;

        foreach (\headers_list() as $header) {
            if (\stripos($header, self::TYPE_PREFIX) === 0) {
                $type = \strtolower(\trim(\substr($header, \strlen(self::TYPE_PREFIX))));
            }
        }

        return $type;
    }

    /**
     * Clear the private cache.
     *
     * The entire cache will be cleared. This method is called if a single object is added, edited
     * or destroyed to ensure that index pages and pagination controls stay up to date.
     *
     * @return bool True on success, false on failure.
     */
    public function flush(): bool
    {
        $directory_iterator = new \DirectoryIterator(TFISH_PRIVATE_CACHE_PATH);

        foreach ($directory_iterator as $file) {

            if ($file->isFile()) {
                $name = $file->getFileName();
                $path = TFISH_PRIVATE_CACHE_PATH . $name;

                if ($path && \file_exists($path)) {
                    try {
                        if ($name !== 'index.html') \unlink($path);
                    } catch (\Exception $e) {
                        \trigger_error(TFISH_CACHE_FLUSH_FAILED_TO_UNLINK, E_USER_NOTICE);
                        return false;
                    }
                } else {
                    \trigger_error(TFISH_CACHE_FLUSH_FAILED_BAD_PATH, E_USER_NOTICE);
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Save a copy of this page to the cache directory.
     *
     * This function should be called after check() and before ob_end_flush(). Note that
     * warnings are suppressed when trying to open the file. The query parameters are important
     * to retrieve the precise representation of the page requested, since they change its state.
     *
     * To disable the cache for a particular page load, pass in empty $params array.
     *
     * @param array $params URL Query string parameters for this page as $key => $value pairs.
     * @param string $buffer HTML page output from ob_get_contents().
     * @return void
     */
    public function save(array $params, string $buffer): void
    {
        // Abort if cache is disabled or $params is empty (= do not cache signal).
        if (!$this->preference->enableCache() || empty($params)) {
            return;
        }

        // Resolve the file name.
        $fileName = $this->_getCachedFileName($params);

        if ($fileName === false) {
            return;
        }

        // Verify that the constructed path matches the canonical path. Exit cache if path is bad.
        $resolvedPath = $this->standardise(\realpath(TFISH_PRIVATE_CACHE_PATH) . '/' . $fileName);
        $canonical = $this->standardise(TFISH_PRIVATE_CACHE_PATH . $fileName);

        if ($resolvedPath !== $canonical) {
            return;
        }

        // Only cache allowlisted content types.
        $type = $this->currentContentType();

        if (!\in_array($type, self::CACHEABLE_TYPES, true)) {
            return;
        }

        // Never cache a page containing the session's CSRF token, otherwise it would be served to
        // other visitors. Defence in depth against templates that embed the token in a form.
        $token = (string) ($_SESSION['token'] ?? '');

        if ($token !== '' && \str_contains($buffer, $token)) {
            return;
        }

        // Write the page to the cache, with its Content-Type as the first line.
        if (false !== ($f = @\fopen($canonical, 'w'))) {
            \fwrite($f, self::TYPE_PREFIX . $type . "\n" . $buffer);
            \fclose($f);
        }
    }

    /**
     * Calculate the return the name of a cached file, based on query string parameters.
     *
     * Empty parameter values are skipped. If any non-empty key or value fails validation, the page
     * is NOT cached (false is returned). Silently dropping the parameter instead would file a page
     * rendered with that value under the key of a different page (eg. ?id=-1 under the home page).
     *
     * @param array $params URL query string parameters for this page as $key => $value pairs.
     * @return string|false Name of the cached version of a file, or false if the page must not be cached.
     */
    private function _getCachedFileName(array $params): string|false
    {
        $fileName = $this->path;
        $isFirstParam = true;

        if (!empty($params)) {

            foreach ($params as $key => $value) {

                if ($value) {
                    $cleanKey = $this->trimString($key);
                    $cleanValue = $this->trimString($value);

                    if (!$this->isAlnumUnderscore($cleanKey)
                            || !$this->isAlnumUnderscore($cleanValue)) {
                        return false;
                    }

                    $separator = $isFirstParam ? '' : '&';
                    $fileName .= $separator . $cleanKey . '=' . $cleanValue;
                    $isFirstParam = false;
                }

                unset($key, $value, $cleanKey, $cleanValue);
            }
        }

        return \mb_strtolower($fileName . '.html', "UTF-8");
    }

    // Set the path property.
    /** @internal */
    private function setPath(string $path)
    {
        // Cast to string, trim and check for UTF-8 compliance.
        $path = $this->trimString($path);

        // Remove any file extension.
        if (\str_ends_with($path, '.php') || \str_ends_with($path, '.PHP')) {
            $path = \substr($path, 0, -4);
        }

        // Check for directory traversals and null byte injection.
        if ($this->hasTraversalorNullByte($path)) {
            throw new \InvalidArgumentException(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE);
        }

        $this->path = $this->removeTraversals($path);
    }


    // Make directory separators consistent between Windows and Linux.
    /** @internal */
    private function standardise(string $path): string
    {
        if (DIRECTORY_SEPARATOR == '\\') {
            $path = \str_replace('\\', '/', $path);
        }

        return \mb_strtolower($path, "UTF-8");
    }

    // Remove slashes from path, as it will be used in cached file name.
    /** @internal */
    private function removeTraversals(string $path): string
    {
        return \str_replace('/', '_', $path);
    }
}
