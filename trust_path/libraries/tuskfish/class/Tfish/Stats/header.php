<?php

declare(strict_types=1);

/**
 * Tuskfish header script for Stats module.
 *
 * Sets additional routes and path constants.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 */

namespace Tfish\Stats;

// Make core language files available.
include 'language/english.php';

// Path to the module's bundled default templates, used as a fallback when the active theme does
// not provide a given Stats template (see \Tfish\Entity\Template::validPath()).
\define("TFISH_STATS_TEMPLATE_PATH", TFISH_CLASS_PATH . 'Tfish/Stats/templates/');

// Read-only SQLite database backing the Stats pages (resolved against TFISH_DATABASE_PATH in
// \Tfish\Stats\Traits\StatsDatabase). Module-local: ships with the module, no site config needed.
\define("TFISH_STATS_DB", 'aquaculture-fisheries-trade.db');

// Additional routes for User module.
$routingTable['/global/'] = new \Tfish\Route(
    '\\Tfish\\Stats\\Model\\Listing',
    '\\Tfish\\Stats\\ViewModel\\Listing',
    '\\Tfish\\View\\Single',
    '\\Tfish\\Stats\\Controller\\Listing',
    0);

$routingTable['/production/'] = new \Tfish\Route(
    '\\Tfish\\Stats\\Model\\Production',
    '\\Tfish\\Stats\\ViewModel\\Production',
    '\\Tfish\\View\\Single',
    '\\Tfish\\Stats\\Controller\\Production',
    0);

$routingTable['/species/'] = new \Tfish\Route(
    '\\Tfish\\Stats\\Model\\Species',
    '\\Tfish\\Stats\\ViewModel\\Species',
    '\\Tfish\\View\\Single',
    '\\Tfish\\Stats\\Controller\\Species',
    0);

$routingTable['/trade/'] = new \Tfish\Route(
    '\\Tfish\\Stats\\Model\\Trade',
    '\\Tfish\\Stats\\ViewModel\\Trade',
    '\\Tfish\\View\\Single',
    '\\Tfish\\Stats\\Controller\\Trade',
    0);

$routingTable['/environment/'] = new \Tfish\Route(
    '\\Tfish\\Stats\\Model\\Environment',
    '\\Tfish\\Stats\\ViewModel\\Environment',
    '\\Tfish\\View\\Single',
    '\\Tfish\\Stats\\Controller\\Environment',
    0);

$routingTable['/consumption/'] = new \Tfish\Route(
    '\\Tfish\\Stats\\Model\\Consumption',
    '\\Tfish\\Stats\\ViewModel\\Consumption',
    '\\Tfish\\View\\Single',
    '\\Tfish\\Stats\\Controller\\Consumption',
    0);

$routingTable['/insecurity/'] = new \Tfish\Route(
    '\\Tfish\\Stats\\Model\\FoodSecurity',
    '\\Tfish\\Stats\\ViewModel\\FoodSecurity',
    '\\Tfish\\View\\Single',
    '\\Tfish\\Stats\\Controller\\FoodSecurity',
    0);

