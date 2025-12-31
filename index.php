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

// Header for core and content module.
require_once TFISH_PATH . 'header.php';

// Module headers provide additional path, language constants, blocks, and routing table info.
require_once TFISH_CLASS_PATH . 'Tfish/User/header.php';
require_once TFISH_CLASS_PATH . 'Tfish/FishStat/header.php';

// Extract the route and action from the request.
// Note: If using an NGINX reverse proxy in front of Apache/Tuskfish to terminate SSL, use the
// commented out line instead (which locks protocol to https), otherwise routing won't work.
//$url = "https://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
    . "://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

$path = \parse_url($url, PHP_URL_PATH);

// Add trailing slash for consistent route handling.
if (\mb_substr($path, -1, null, "UTF-8") !== '/') {
    $path .= '/';
}

// Calculate relative path (without query) to align with routing table if installed in subdirectory.
$relativeUrl = \parse_url($url, PHP_URL_QUERY) ?? '';
$relativePath = \str_replace(TFISH_LINK, '', $url);
$relativePath = \str_replace('?' . $relativeUrl, '', $relativePath);

// Add trailing slash for consistent route handling.
if (\mb_substr($relativePath, -1, null, "UTF-8") !== '/') {
    $relativePath .= '/';
}

// Route and process request.
$router = new Router($routingTable);
$route = $router->route($relativePath);
$dice->create('\\Tfish\\FrontController', [$dice, $route, $relativePath]);

/*$time_end = microtime(true);
$time = $time_end - $time_start;
echo 'Page execution time: ' . round(($time * 1000), 1). ' milliseconds';*/
