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

// Path to the module's editable configuration (currently the basemap tile provider registry).
// Configuration lives in a file rather than a site preference so the module installs with no core
// edits; \Tfish\Entity\Preference is a fixed-property class, so a first-class preference would mean
// patching Preference.php, ViewModel\PreferenceEdit, the admin form template and the language file.
\define("TFISH_RANGEFINDER_CONFIG_PATH", TFISH_CLASS_PATH . 'Tfish/Rangefinder/config/');

// Web-accessible base URL for the module's client assets (its own JS/CSS plus the vendored copies
// of Leaflet and Leaflet.markercluster). trust_path/ is outside the web root, so these cannot ship
// beside the module's PHP; they live in vendor/ rather than in a theme, which keeps installation to
// "copy one class directory and three vendor directories" with no theme edit either.
\define("TFISH_RANGEFINDER_ASSET_URL", TFISH_URL . 'vendor/');

// Hard ceiling on map zoom, applied on top of whatever the active tile provider allows (D-2a).
// Two reasons, and the second is the load-bearing one: it keeps tile requests inside the free
// tiers, and it stops the map implying a precision the data does not have. Coordinate precision in
// this dataset runs ~31 m to ~1850 m and localities are gridded to ~1 km at import, so a marker
// zoomed to street level would look like a surveyed point when it is a rounded centroid.
\define("TFISH_RANGEFINDER_MAX_ZOOM", 11);

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
