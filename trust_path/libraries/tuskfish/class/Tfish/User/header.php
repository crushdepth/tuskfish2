<?php

declare(strict_types=1);

/**
 * Tuskfish header script for User module.
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

namespace Tfish\User;

// Make core language files available.
include 'language/english.php';

// Addtional routes for User module.
$routingTable['/admin/user/'] = new \Tfish\Route(
    '\\Tfish\\User\\Model\\Admin',
    '\\Tfish\\User\\ViewModel\\Admin',
    '\\Tfish\\View\\Listing',
    '\\Tfish\\User\\Controller\\Admin',
    1);

$routingTable['/admin/user/edit/'] = new \Tfish\Route(
    '\\Tfish\\User\\Model\\UserEdit',
    '\\Tfish\\User\\ViewModel\\UserEdit',
    '\\Tfish\\View\\Single',
    '\\Tfish\\User\\Controller\\UserEdit',
    1);

// User file path constants.
\define("TFISH_ADMIN_USER_URL", TFISH_ADMIN_URL . 'user/');
