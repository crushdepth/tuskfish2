<?php

declare(strict_types=1);

/**
 * Tuskfish header script for FishStat module.
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

namespace Tfish\FishStat;

// Make core language files available.
include 'language/english.php';

// Addtional routes for User module.
$routingTable['/fishstat/'] = new \Tfish\Route(
    '\\Tfish\\FishStat\\Model\\Listing',
    '\\Tfish\\FishStat\\ViewModel\\Listing',
    '\\Tfish\\View\\Single',
    '\\Tfish\\FishStat\\Controller\\Listing',
    0);

$routingTable['/admin/fishstat/'] = new \Tfish\Route(
    '\\Tfish\\FishStat\\Model\\Admin',
    '\\Tfish\\FishStat\\ViewModel\\Admin',
    '\\Tfish\\FishStat\\Listing',
    '\\Tfish\\FishStat\\Controller\\Admin',
    1);

// User file path constants.
\define("TFISH_ADMIN_FISHSTAT_URL", TFISH_ADMIN_URL . 'fishstat/');
