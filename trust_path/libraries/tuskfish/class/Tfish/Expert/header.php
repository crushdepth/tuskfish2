<?php

declare(strict_types=1);

/**
 * Tuskfish header script for Expert module.
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

namespace Tfish\Expert;

// Make core language files available.
include 'language/english.php';

// Addtional routes for Expert module.
$routingTable['/experts/'] = new \Tfish\Route(
    '\\Tfish\\Expert\\Model\\Search',
    '\\Tfish\\Expert\\ViewModel\\Search',
    '\\Tfish\\View\\Listing',
    '\\Tfish\\Expert\\Controller\\Search',
    0);

$routingTable['/admin/experts/'] = new \Tfish\Route(
    '\\Tfish\\Expert\\Model\\Admin',
    '\\Tfish\\Expert\\ViewModel\\Admin',
    '\\Tfish\\View\\Listing',
    '\\Tfish\\Expert\\Controller\\Admin',
    1);

$routingTable['/admin/experts/edit/'] = new \Tfish\Route(
    '\\Tfish\\Expert\\Model\\ExpertEdit',
    '\\Tfish\\Expert\\ViewModel\\ExpertEdit',
    '\\Tfish\\View\\Single',
    '\\Tfish\\Expert\\Controller\\ExpertEdit',
    1);

// User file path constants.
\define("TFISH_ADMIN_EXPERT_URL", TFISH_ADMIN_URL . 'expert/');
