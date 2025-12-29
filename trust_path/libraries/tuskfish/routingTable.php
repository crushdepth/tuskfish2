<?php

declare(strict_types=1);

namespace Tfish;

/**
 * Stores the static routing table used by the Router class.
 *
 * The routing table is used to select components to initialise for a given page (route).
 * The trailing integer is a bitmask controlling group access permissions. This works a bit like
 * CHMOD file permissions. Site admninistrator has implicit access to all routes.
 *
 * 0 = public.
 * 1 = site administrator only.
 * 2 = editors.
 * 4 = members.
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
        0),
    '/admin/' => new Route(
        '\\Tfish\\Content\\Model\\Admin',
        '\\Tfish\\Content\\ViewModel\\Admin',
        '\\Tfish\\View\\Listing',
        '\\Tfish\\Content\\Controller\\Admin',
        2),
    '/admin/blocks/' => new Route(
        '\\Tfish\\Model\\BlockAdmin',
        '\\Tfish\\ViewModel\\BlockAdmin',
        '\\Tfish\\View\\Listing',
        '\\Tfish\\Controller\\BlockAdmin',
        1),
    '/admin/blocks/edit/' => new Route(
        '\\Tfish\\Model\\BlockEdit',
        '\\Tfish\\ViewModel\\BlockEdit',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\BlockEdit',
        1),
    '/admin/content/' => new Route(
        '\\Tfish\\Content\\Model\\ContentEdit',
        '\\Tfish\\Content\\ViewModel\\ContentEdit',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Content\\Controller\\ContentEdit',
        2),
    '/admin/search/' => new Route(
        '\\Tfish\\Content\\Model\\Search',
        '\\Tfish\\Content\\ViewModel\\AdminSearch',
        '\\Tfish\\View\\Listing',
        '\\Tfish\\Content\\Controller\\Search',
        2),
    '/error/' => new Route(
        '\\Tfish\\Model\\Error',
        '\\Tfish\\ViewModel\\Error',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Error',
        0),
    '/gallery/' => new Route(
        '\\Tfish\\Content\\Model\\Listing',
        '\\Tfish\\Content\\ViewModel\\Gallery',
        '\\Tfish\\View\\Listing',
        '\\Tfish\\Content\\Controller\\Gallery',
        0),
    '/password/' => new Route(
        '\\Tfish\\Model\\Password',
        '\\Tfish\\ViewModel\\Password',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Password',
        7),
    '/flush/' => new Route(
        '\\Tfish\\Model\\Cache',
        '\\Tfish\\ViewModel\\Cache',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Cache',
        2),
    '/enclosure/' => new Route(
        '\\Tfish\\Content\\Model\\Enclosure',
        '\\Tfish\\Content\\ViewModel\\Enclosure',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Content\\Controller\\Enclosure',
        0),

    // Standard username / password login route.
    '/login/' => new Route(
        '\\Tfish\\Model\\Login',
        '\\Tfish\\ViewModel\\Login',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Login',
        0),
    '/logout/' => new Route(
        '\\Tfish\\Model\\Login',
        '\\Tfish\\ViewModel\\Login',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Logout',
        0),
    '/register/' => new Route(
        '\\Tfish\\Model\\WebAuthnCredential',
        '\\Tfish\\ViewModel\\WebAuthn',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\WebAuthn',
        7),
    '/restricted/' => new Route(
        '\\Tfish\\Model\\Login',
        '\\Tfish\\ViewModel\\Restricted',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Login',
        0),
    '/preference/' => new Route(
        '\\Tfish\\Model\\Preference',
        '\\Tfish\\ViewModel\\PreferenceList',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\PreferenceList',
        1),
    '/preference/edit/' => new Route(
        '\\Tfish\\Model\\Preference',
        '\\Tfish\\ViewModel\\PreferenceEdit',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\PreferenceEdit',
        1),
    '/rss/' => new Route(
        '\\Tfish\\Content\\Model\\Rss',
        '\\Tfish\\Content\\ViewModel\\Rss',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Content\\Controller\\Rss',
        0),
    '/sitemap/' => new Route(
        '\\Tfish\\Model\\Sitemap',
        '\\Tfish\\ViewModel\\Sitemap',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Sitemap',
        2),
    '/search/' => new Route(
        '\\Tfish\\Content\\Model\\Search',
        '\\Tfish\\Content\\ViewModel\\Search',
        '\\Tfish\\View\\Listing',
        '\\Tfish\\Content\\Controller\\Search',
        0),
    '/token/' => new Route(
        '\\Tfish\\Model\\Error',
        '\\Tfish\\ViewModel\\Token',
        '\\Tfish\\View\\Single',
        '\\Tfish\\Controller\\Error',
        0),
];
