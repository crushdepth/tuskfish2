<?php

declare(strict_types=1);

namespace Tfish\Rangefinder;

/**
 * Tuskfish Rangefinder module language constants (English).
 *
 * Translate this file to convert the Tuskfish Rangefinder module to another language.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */
// Language constants.
\define("TFISH_RANGEFINDER", "Rangefinder");

// Separator used when appending active filters (species, country) to a page title.
\define("TFISH_RANGEFINDER_TITLE_SEPARATOR", "\u{2014}"); // Em dash.

// Page titles and meta descriptions.
\define("TFISH_RANGEFINDER_MAP", "Map");
\define("TFISH_RANGEFINDER_MAP_TITLE", "ArtemiaBase \u{2014} Global Artemia Occurrence Map");
\define("TFISH_RANGEFINDER_MAP_DESCRIPTION", "A curated map of Artemia (brine shrimp) occurrence records worldwide: verified species determinations from the IATS-CSIC cyst bank, plus genus-level presence records aggregated from GBIF.");

// Map furniture.
\define("TFISH_RANGEFINDER_LOCALITIES", "Localities");
\define("TFISH_RANGEFINDER_RECORDS", "Records");
\define("TFISH_RANGEFINDER_SPECIES_LAYER", "Verified species");
\define("TFISH_RANGEFINDER_PRESENCE_LAYER", "Genus-only, unverified");
\define("TFISH_RANGEFINDER_COUNTRIES", "Countries");

// Errors.
\define("TFISH_RANGEFINDER_DB_UNAVAILABLE", "The occurrence database is currently unavailable. Please try again later.");
