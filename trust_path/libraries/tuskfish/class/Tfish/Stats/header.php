<?php

declare(strict_types=1);

/**
 * Tuskfish header script for Stats module.
 *
 * Sets additional routes and path constants.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.0.4
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

$routingTable['/intermediate/'] = new \Tfish\Route(
    '\\Tfish\\Stats\\Model\\Intermediate',
    '\\Tfish\\Stats\\ViewModel\\Intermediate',
    '\\Tfish\\View\\Single',
    '\\Tfish\\Stats\\Controller\\Intermediate',
    0);

$routingTable['/species/'] = new \Tfish\Route(
    '\\Tfish\\Stats\\Model\\Species',
    '\\Tfish\\Stats\\ViewModel\\Species',
    '\\Tfish\\View\\Single',
    '\\Tfish\\Stats\\Controller\\Species',
    0);

$routingTable['/environment/'] = new \Tfish\Route(
    '\\Tfish\\Stats\\Model\\Environment',
    '\\Tfish\\Stats\\ViewModel\\Environment',
    '\\Tfish\\View\\Single',
    '\\Tfish\\Stats\\Controller\\Environment',
    0);

