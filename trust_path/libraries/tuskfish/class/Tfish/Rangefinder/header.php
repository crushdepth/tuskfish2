<?php

declare(strict_types=1);

/**
 * Tuskfish header script for Rangefinder module.
 *
 * Sets additional routes and path constants.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */

namespace Tfish\Rangefinder;

// Make module language files available.
include __DIR__ . '/language/english.php';

// Path to the module's bundled default templates, used as a fallback when the active theme does
// not provide a given Rangefinder template (see \Tfish\Entity\Template::validPath()).
\define("TFISH_RANGEFINDER_TEMPLATE_PATH", TFISH_CLASS_PATH . 'Tfish/Rangefinder/templates/');

// Read-only SQLite occurrence database backing the map (resolved against TFISH_DATABASE_PATH in
// \Tfish\Rangefinder\Traits\RangefinderDatabase). Rangefinder is the taxon-agnostic engine; this
// constant names the *deployment* dataset, which for artemia.info is ArtemiaBase. Point it at a
// different DwC-A-derived database to redeploy the module against another taxon.
\define("TFISH_RANGEFINDER_DB", 'artemia-occurrences.db');

// Additional routes for the Rangefinder module.
$routingTable['/map/'] = new \Tfish\Route(
    '\\Tfish\\Rangefinder\\Model\\Map',
    '\\Tfish\\Rangefinder\\ViewModel\\Map',
    '\\Tfish\\View\\Single',
    '\\Tfish\\Rangefinder\\Controller\\Map',
    0);
