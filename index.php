<?php

declare(strict_types=1);

/**
 * Tuskfish home page controller script.
 *
 * Displays a single stream of mixed content (teasers), excluding tags and static content objects.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     content
 */

namespace Tfish;

// $time_start = microtime(true);

// Access trust path, DB credentials, configuration and preferences.
require_once 'mainfile.php';

// Routing table for front end controller is declared here for convenient editing.
$routingTable = require_once TFISH_PATH . 'routingTable.php';

// Core header: language constants, block-registry seed arrays, DICE container and error handlers.
require_once TFISH_PATH . 'header.php';

// Module headers provide additional path, language constants, blocks, and routing table info.
// Auto-discovered: any module directory containing a header.php is loaded (alphabetical order).
foreach (\glob(TFISH_CLASS_PATH . 'Tfish/*/header.php') ?: [] as $moduleHeader) {
    require_once $moduleHeader;
}

// Finalise the block registry from the core + module block registrations and register it with DICE.
// Must follow the glob (module headers populate the seed arrays) and precede FrontController
// creation. addRule() returns a new immutable Dice instance, so $dice is reassigned.
$dice = $dice->addRule('\\Tfish\\BlockRegistry', [
    'shared' => true,
    'constructParams' => [[
        'types' => $blockTypes,
        'templates' => $blockTemplates,
        'positions' => $blockPositions,
        'routes' => $blockRoutes,
        'config' => $blockConfig
    ]]
]);

// Extract the route from the request. Route purely from the request path. Scheme and host are
// deliberately ignored: scheme is irrelevant to route matching (and works transparently behind a
// reverse proxy that terminates SSL), and the host (Host header / SERVER_NAME) is client-supplied
// and must not be trusted. The only admin-controlled input we need is the base directory, taken
// from TFISH_LINK, which keeps subdirectory installs working.
$basePath = \rtrim(\parse_url(TFISH_LINK, PHP_URL_PATH) ?? '', '/');

// REQUEST_URI is origin-form (path[?query]); take the part before the query directly rather than
// via parse_url(), which would misread a leading '//' as an authority and silently route bogus
// '//foo//' URLs to the home page instead of returning a 404.
$relativePath = \explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];

// Strip the base directory (e.g. '/sub'), guarding against partial matches like '/subway'.
if ($basePath !== ''
    && (\strncmp($relativePath, $basePath . '/', \strlen($basePath) + 1) === 0
        || $relativePath === $basePath)) {
    $relativePath = \substr($relativePath, \strlen($basePath));
}

// Normalise to a single leading and trailing slash for consistent route matching.
$relativePath = '/' . \trim($relativePath, '/');
if ($relativePath !== '/') {
    $relativePath .= '/';
}

// Route and process request.
$router = new Router($routingTable);
$route = $router->route($relativePath);
$dice->create('\\Tfish\\FrontController', [$dice, $route, $relativePath]);

/*$time_end = microtime(true);
$time = $time_end - $time_start;
echo 'Page execution time: ' . round(($time * 1000), 1). ' milliseconds';*/
