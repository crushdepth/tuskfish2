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
 * @package     User
 */

namespace Tfish\Stats;

// Make core language files available.
include 'language/english.php';

// Addtional routes for User module.
$routingTable['/overview/'] = new \Tfish\Route(
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

