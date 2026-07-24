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

// Filter controls.
\define("TFISH_RANGEFINDER_FILTERS", "Filters");
\define("TFISH_RANGEFINDER_LAYERS", "Layers");
\define("TFISH_RANGEFINDER_SPECIES_FILTER", "Species / lineage");
\define("TFISH_RANGEFINDER_COUNTRY", "Country");
\define("TFISH_RANGEFINDER_ALL_COUNTRIES", "All countries");
\define("TFISH_RANGEFINDER_HOLDING", "Physical holding");
\define("TFISH_RANGEFINDER_HOLDING_ANY", "Any");
\define("TFISH_RANGEFINDER_HOLDING_OBTAINABLE", "Obtainable holding");
\define("TFISH_RANGEFINDER_HOLDING_LIVE", "Live cysts only");
\define("TFISH_RANGEFINDER_HOLDING_EXHAUSTED", "Held but depleted");
\define("TFISH_RANGEFINDER_GAPS_ONLY", "Localities with no verified species");
\define("TFISH_RANGEFINDER_GAP_MAP", "Gap map");
\define("TFISH_RANGEFINDER_RESET", "Reset filters");

// Legend. Always on screen, never behind a control: this is the one piece of information that
// stops a visitor reading an unverified genus-level report as a species determination.
\define("TFISH_RANGEFINDER_LEGEND_SPECIES", "Verified species determination");
\define("TFISH_RANGEFINDER_LEGEND_PRESENCE", "Genus-only record, unverified");
\define("TFISH_RANGEFINDER_LEGEND_BOTH", "Both at this locality");
\define("TFISH_RANGEFINDER_LEGEND_NOTE", "Genus-only records report that Artemia was found near a place. They are leads for further survey, not determinations of which species is present.");

// Strings used by the client-side map (passed to JavaScript as a translated bundle).
// {token} placeholders are substituted in the browser; keep them intact when translating.
\define("TFISH_RANGEFINDER_UNNAMED_LOCALITY", "Unnamed locality");
\define("TFISH_RANGEFINDER_UNVERIFIED", "Unverified");
\define("TFISH_RANGEFINDER_GENUS_ONLY", "Genus-level record");
\define("TFISH_RANGEFINDER_SPECIES_HEADING", "Verified species");
\define("TFISH_RANGEFINDER_PRESENCE_HEADING", "Genus-only records");
\define("TFISH_RANGEFINDER_RECORD_TALLY", "\u{00d7} {count}"); // Multiplication sign.
\define("TFISH_RANGEFINDER_MAPPED_TALLY", "({mapped} mapped)");
\define("TFISH_RANGEFINDER_SHOWING", "Showing {shown} of {total} localities \u{2014} {records} records");
\define("TFISH_RANGEFINDER_COORDS_PRECISION", "{coords} \u{00b1}{precision} m");
\define("TFISH_RANGEFINDER_DATA_ATTRIBUTION", "Occurrence data: IATS-CSIC Artemia cyst bank and GBIF contributors, CC BY-NC 4.0");

// Errors.
\define("TFISH_RANGEFINDER_DB_UNAVAILABLE", "The occurrence database is currently unavailable. Please try again later.");
\define("TFISH_RANGEFINDER_NO_TILE_PROVIDER", "No basemap is configured, so the map cannot be drawn. This is a configuration problem, not an absence of occurrence records.");
