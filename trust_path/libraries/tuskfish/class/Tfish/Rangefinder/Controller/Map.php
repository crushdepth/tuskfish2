<?php

declare(strict_types=1);

namespace Tfish\Rangefinder\Controller;

/**
 * \Tfish\Rangefinder\Controller\Map class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */

/**
 * Controller for the Rangefinder occurrence map (/map/).
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 */
class Map
{
    use \Tfish\Traits\ValidateString;

    private object $model;
    private object $viewModel;
    private \Tfish\Logger $logger;

    public function __construct(object $model, object $viewModel, \Tfish\Logger $logger)
    {
        $this->model = $model;
        $this->viewModel = $viewModel;
        $this->logger = $logger;
    }

    /**
     * Render the occurrence map page.
     *
     * The page itself is filter-agnostic: the whole marker payload ships once and all filtering
     * (species/lineage, presence toggle, country, physical holding) happens client-side against
     * it, with the active state carried in the URL fragment for deep-linking. So there is one
     * cacheable page regardless of the filters in play.
     *
     * @return  array Cache parameters.
     */
    public function display(): array
    {
        $cacheParams = ['page' => 'map'];
        $loggedIn = !empty($_SESSION['id']);

        if ($loggedIn) {
            $cacheParams['loggedIn'] = '1';
        }

        // Before anything is loaded: on a 304 this exits without touching the database, and on a
        // cache hit \Tfish\Cache::check() exits after the action returns, so headers set here still
        // apply to the cached copy.
        $this->sendCacheValidators($loggedIn);

        $this->viewModel->displayMap();

        return $cacheParams;
    }

    /**
     * Send browser cache validators, and answer a conditional request with 304 if nothing changed.
     *
     * The map is dynamic in the browser but static on the server. Panning and zooming fetch tiles
     * from the tile provider, not from us; filtering runs client-side over the payload already
     * loaded, so it issues no requests at all; and the deep-link parameters are read by JavaScript
     * after load and never change the HTML. The response is therefore byte-identical for every
     * anonymous visitor and changes only when the occurrence database is rebuilt.
     *
     * So the validator is the database's own mtime. That gives revalidation rather than a flat
     * max-age, which matters: you can flush a server cache, but you cannot flush the world's
     * browsers, and a max-age that turns out to be wrong keeps serving superseded scientific
     * records for its full duration with no way to recall them. Revalidation costs one conditional
     * request and picks up a rebuild immediately, with no purge step for anyone to forget.
     *
     * Logged-in pages carry session-dependent furniture, so they are marked private and are keyed
     * separately.
     *
     * @param   bool $loggedIn Whether a session is active.
     */
    private function sendCacheValidators(bool $loggedIn): void
    {
        $timestamp = $this->model->databaseTimestamp();

        if ($timestamp < 1) return;

        $etag = '"rf-' . $timestamp . ($loggedIn ? '-in' : '') . '"';

        // PHP's session cache limiter has already emitted 'Pragma: no-cache' and a 1981 'Expires'
        // by this point. Cache-Control outranks both for anything HTTP/1.1, and Pragma is a request
        // header that means nothing in a response, so they are harmless — but they contradict what
        // is being said here, and a contradictory header set is exactly what an intermediary
        // resolves in whichever direction it likes. Drop them and let one directive speak.
        \header_remove('Pragma');
        \header_remove('Expires');

        \header('Cache-Control: ' . ($loggedIn ? 'private' : 'public') . ', must-revalidate');
        \header('ETag: ' . $etag);

        $ifNoneMatch = $this->trimString($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');

        // A client may return a list, and a cache may have weakened the tag with a W/ prefix.
        foreach (\explode(',', $ifNoneMatch) as $candidate) {
            if (\ltrim(\trim($candidate), 'W/') === $etag) {
                \http_response_code(304);
                exit;
            }
        }
    }
}
