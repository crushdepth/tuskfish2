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
// The three confidence buckets (D-18). One set of labels, reused for the summary counts, the
// filter checkboxes and the popup section headings, so the same words name the same thing
// everywhere. "Verified" is an expert determination; the other two are leads (unverified reports)
// that differ only in how far the name reaches — to a species, or only to the genus.
\define("TFISH_RANGEFINDER_VERIFIED", "Verified to species");
\define("TFISH_RANGEFINDER_REPORTED", "Reported to species");
\define("TFISH_RANGEFINDER_UNIDENTIFIED", "Not identified to species");
\define("TFISH_RANGEFINDER_COUNTRIES", "Countries");

// Filter controls.
\define("TFISH_RANGEFINDER_FILTERS", "Filters");
\define("TFISH_RANGEFINDER_CONFIDENCE", "Taxonomic confidence");
\define("TFISH_RANGEFINDER_SPECIES_FILTER", "Artemia species / lineage");
\define("TFISH_RANGEFINDER_COUNTRY", "Country");
\define("TFISH_RANGEFINDER_ALL_COUNTRIES", "All countries");
// The holding filter exposes the *kind* of physical material a record represents, derived at import
// into holding_type. "Any obtainable material" is the union of the three kinds that still physically
// exist (live cysts, preserved specimens, tissue/DNA); the three narrower options select one kind
// each. "Depleted" is material that existed and is used up. Records that are observations only carry
// no holding and appear under no option but the disabled default.
\define("TFISH_RANGEFINDER_HOLDING", "Physical holding");
\define("TFISH_RANGEFINDER_HOLDING_ANY", "\u{2014}\u{2014}\u{2014}"); // Three em dashes: filter disabled.
\define("TFISH_RANGEFINDER_HOLDING_OBTAINABLE", "Any obtainable material");
\define("TFISH_RANGEFINDER_HOLDING_LIVE_CYSTS", "Cysts");
\define("TFISH_RANGEFINDER_HOLDING_PRESERVED", "Preserved specimen");
\define("TFISH_RANGEFINDER_HOLDING_TISSUE", "Tissue or DNA");
\define("TFISH_RANGEFINDER_HOLDING_EXHAUSTED", "Depleted");
\define("TFISH_RANGEFINDER_HOLDING_HELP", "The kind of physical material behind a record. \u{201c}Any obtainable material\u{201d} is anything still held \u{2014} cysts, preserved specimens or tissue/DNA; only cysts can be hatched and cultured. \u{201c}Depleted\u{201d} once existed but is used up.");
\define("TFISH_RANGEFINDER_GAP_MAP", "Gap map");
\define("TFISH_RANGEFINDER_GAP_MAP_HELP", "Shows only localities with no verified species determination \u{2014} only reported or genus-level leads \u{2014} the survey gaps where no expert has yet confirmed which Artemia is present.");
\define("TFISH_RANGEFINDER_RESET", "Reset filters");

// Legend. Always on screen, never behind a control: this is the one piece of information that
// stops a visitor reading an unverified genus-level report as a species determination.
// Marker colour is deliberately two-tone: a verified determination, or an unverified lead. The lead
// colour covers both "reported to species" and "not identified to species" — the filter and the
// popup draw that finer distinction, but on the map the load-bearing line is verified vs not.
\define("TFISH_RANGEFINDER_LEGEND_SPECIES", "Verified species determination");
\define("TFISH_RANGEFINDER_LEGEND_PRESENCE", "Unverified report (reported or not identified to species)");
\define("TFISH_RANGEFINDER_LEGEND_BOTH", "Both at this locality");
\define("TFISH_RANGEFINDER_LEGEND_NOTE", "Unverified reports say Artemia was found near a place. Some carry a species name (reported to species), others reach only the genus (not identified to species); neither has been checked by an Artemia expert. They are leads for further survey, not determinations of which species is present.");

// Strings used by the client-side map (passed to JavaScript as a translated bundle).
// {token} placeholders are substituted in the browser; keep them intact when translating.
\define("TFISH_RANGEFINDER_UNNAMED_LOCALITY", "Unnamed locality");
\define("TFISH_RANGEFINDER_UNVERIFIED", "Unverified");
\define("TFISH_RANGEFINDER_GENUS_ONLY", "Genus-level record");
// Popup section headings reuse the three confidence-bucket labels defined above
// (TFISH_RANGEFINDER_VERIFIED / _REPORTED / _UNIDENTIFIED).
\define("TFISH_RANGEFINDER_RECORD_TALLY", "\u{00d7} {count}"); // Multiplication sign.
// Per-record detail lines under each popup taxon (D-20). Sourced inline, no server round-trip.
// "Recorded by" carries the source-supplied collector/observer under CC-BY attribution.
\define("TFISH_RANGEFINDER_RECORDED_BY", "Recorded by {name}");
\define("TFISH_RANGEFINDER_DATE_UNKNOWN", "Date not recorded");
// Accession is a source-record identifier, NOT a claim that material is held. "View record" links
// out to the source's own page (iNaturalist observation, museum record) where one exists.
\define("TFISH_RANGEFINDER_ACCESSION", "Accession {id}");
\define("TFISH_RANGEFINDER_VIEW_RECORD", "View record");
// Toggle under a capped "not identified to species" popup line. Opens an in-place accordion of the
// records not shown inline (each still carrying its date). {count} is the number folded away.
// Becomes the /explore deep-link once that surface exists (Phase 4).
\define("TFISH_RANGEFINDER_MORE_RECORDS", "+{count} more not identified to species");
\define("TFISH_RANGEFINDER_FEWER_RECORDS", "Show fewer");
\define("TFISH_RANGEFINDER_MAPPED_TALLY", "({mapped} mapped)");
\define("TFISH_RANGEFINDER_SHOWING", "Showing {shown} of {total} localities \u{2014} {records} records");
\define("TFISH_RANGEFINDER_COORDS_PRECISION", "{coords} \u{00b1}{precision} m");
\define("TFISH_RANGEFINDER_DATA_ATTRIBUTION", "Occurrence data: IATS-CSIC Artemia cyst bank and GBIF contributors, CC BY-NC 4.0");

// Errors.
\define("TFISH_RANGEFINDER_DB_UNAVAILABLE", "The occurrence database is currently unavailable. Please try again later.");
\define("TFISH_RANGEFINDER_NO_TILE_PROVIDER", "No basemap is configured, so the map cannot be drawn. This is a configuration problem, not an absence of occurrence records.");
