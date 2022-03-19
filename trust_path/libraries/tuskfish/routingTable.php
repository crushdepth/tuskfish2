<?php

declare(strict_types=1);

namespace Tfish;

/**
 * Stores the static routing table used by the Router class.
 * 
 * The routing table is used to select components to initialise for a given page (route).
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

return [
    '/' => new Route(
        '\\Tfish\\Content\\Model\\Listing',
        '\\Tfish\\Content\\ViewModel\\Listing',
        '\\Tfish\\View\\Listing',
        '\\Tfish\\Content\\Controller\\Listing',
        false),
    '/admin/' => new Route(
        '\\Tfish\\Content\\Model\\Admin',
        '\\Tfish\\Content\\ViewModel\\Admin',
        '\\Tfish\\View\\Listing',
        '\\Tfish\\Content\\Controller\\Admin',
        true),
    '/admin/content/' => new Route(
        '\\Tfish\\Content\\Model\\ContentEdit',
        '\\Tfish\\Content\\ViewModel\\ContentEdit',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Content\\Controller\\ContentEdit',
        true),
    '/admin/search/' => new Route(
        '\\Tfish\\Content\\Model\\Search',
        '\\Tfish\\Content\\ViewModel\\AdminSearch',
        '\\Tfish\\View\\Listing',
        '\\Tfish\\Content\\Controller\\Search',
        true),
    '/error/' => new Route(
        '\\Tfish\\Model\\Error',
        '\\Tfish\\ViewModel\\Error',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Error',
        false),
    '/gallery/' => new Route(
        '\\Tfish\\Content\\Model\\Listing',
        '\\Tfish\\Content\\ViewModel\\Gallery',
        '\\Tfish\\View\\Listing',
        '\\Tfish\\Content\\Controller\\Gallery',
        false),
    '/password/' => new Route(
        '\\Tfish\\Model\\Password',
        '\\Tfish\\ViewModel\\Password',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Password',
        true),
    '/flush/' => new Route(
        '\\Tfish\\Model\\Cache',
        '\\Tfish\\ViewModel\\Cache',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Cache',
        true),
    '/enclosure/' => new Route(
        '\\Tfish\\Content\\Model\\Enclosure',
        '\\Tfish\\Content\\ViewModel\\Enclosure',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Content\\Controller\\Enclosure',
        false),

    // Standard username / password login route.
    '/login/' => new Route(
        '\\Tfish\\Model\\Login',
        '\\Tfish\\ViewModel\\Login',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Login',
        false),

    // Alternative two-factor login route for use with Yubikey.
    //'/login/' => new Route(
    //    '\\Tfish\\Model\\Yubikey',
    //    '\\Tfish\\ViewModel\\Yubikey',
    //    '\\Tfish\\View\\Single',
    //    '\\Tfish\\Controller\\Yubikey',
    //    false),

    '/logout/' => new Route(
        '\\Tfish\\Model\\Login',
        '\\Tfish\\ViewModel\\Login',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Logout',
        false),
    '/preference/' => new Route(
        '\\Tfish\\Model\\Preference',
        '\\Tfish\\ViewModel\\PreferenceList',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\PreferenceList',
        true),
    '/preference/edit/' => new Route(
        '\\Tfish\\Model\\Preference',
        '\\Tfish\\ViewModel\\PreferenceEdit',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\PreferenceEdit',
        true),
    '/rss/' => new Route(
        '\\Tfish\\Content\\Model\\Rss',
        '\\Tfish\\Content\\ViewModel\\Rss',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Content\\Controller\\Rss',
        false),
    '/sitemap/' => new Route(
        '\\Tfish\\Model\\Sitemap',
        '\\Tfish\\ViewModel\\Sitemap',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Sitemap',
        true),
    '/search/' => new Route(
        '\\Tfish\\Content\\Model\\Search',
        '\\Tfish\\Content\\ViewModel\\Search',
        '\\Tfish\\View\\Listing',
        '\\Tfish\\Content\\Controller\\Search',
        false),
    '/token/' => new Route(
        '\\Tfish\\Model\\Error',
        '\\Tfish\\ViewModel\\Token',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Error',
        false)
];
