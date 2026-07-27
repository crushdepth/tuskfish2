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
\define("TFISH_RANGEFINDER_MAP_TITLE", "Global Artemia Biodiversity and Conservation Mapping System");
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
\define("TFISH_RANGEFINDER_HOLDING_ANY", "---"); // Three hyphens: filter disabled.
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
// Two paragraphs, not one string with a break in it: a translator must be able to reorder or split
// them, and the second is a different claim from the first. The verified note carries the temporal
// caveat — a determination is correct as of its own date, and Artemia synonymy has moved since, so
// the record's date is part of reading it.
// Each note is split into the term it defines and the definition, because the term is emphasised in
// the markup. The split is here rather than as HTML inside one string so the strings stay pure text
// and can keep being escaped on output. A translation whose term does not lead the sentence should
// put the whole sentence in the _TERM half and leave the other empty.
\define("TFISH_RANGEFINDER_LEGEND_NOTE_VERIFIED_TERM", "Verified records");
\define("TFISH_RANGEFINDER_LEGEND_NOTE_VERIFIED", "are species-level determinations made by a domain expert using the taxonomic understanding of the day. Artemia taxonomy has changed over time, so it is advisable to check the date of individual observations.");
\define("TFISH_RANGEFINDER_LEGEND_NOTE_UNVERIFIED_TERM", "Unverified reports");
\define("TFISH_RANGEFINDER_LEGEND_NOTE_UNVERIFIED", "are records not identified to species, or made by sources of unknown taxonomic authority. They are most suitable as indicators of Artemia presence and leads for further survey.");

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
// Per-record accuracy control on a popup record line. The figure is the source's own declared
// coordinate uncertainty, or (IATS records) the resolution of the verbatim DMS reading — never an
// inference from decimal places, which measure how a number was written and not how well the site
// was located. A record that declares nothing gets no control and no circle: silence is the honest
// rendering of "unknown". Toggling draws one circle of that radius around the marker.
\define("TFISH_RANGEFINDER_ACCURACY_M", "\u{00b1}{radius} m");
\define("TFISH_RANGEFINDER_ACCURACY_KM", "\u{00b1}{radius} km");
\define("TFISH_RANGEFINDER_ACCURACY_SHOW", "Show this record's accuracy on the map");
\define("TFISH_RANGEFINDER_ACCURACY_HIDE", "Hide accuracy circle");
\define("TFISH_RANGEFINDER_DATA_ATTRIBUTION", "Occurrence data: IATS-CSIC Artemia cyst bank and GBIF contributors, CC BY-NC 4.0");
// Map control tooltips. Fullscreen shows the map only; filters stay set but are hidden, so the map
// carries a note that they are changed after exiting fullscreen.
\define("TFISH_RANGEFINDER_FULLSCREEN", "Full screen");
\define("TFISH_RANGEFINDER_EXIT_FULLSCREEN", "Exit full screen");

// -----------------------------------------------------------------------------------------------
// /explore: the tabular search surface (Phase 4). Shares the map's vocabulary wherever it asks the
// same question — the confidence, species, country and holding labels above are reused verbatim, so
// a filter carried between the two surfaces is called the same thing on both.
// -----------------------------------------------------------------------------------------------
\define("TFISH_RANGEFINDER_EXPLORE_TITLE", "Explore Artemia occurrence records");
\define("TFISH_RANGEFINDER_EXPLORE_DESCRIPTION", "Search and filter the full curated Artemia occurrence dataset as a table: species and lineage, taxonomic confidence, country, physical material, date range, source dataset and molecular sequences, with every record's source and licence.");

// Table-only filter controls.
\define("TFISH_RANGEFINDER_SEARCH", "Place name");
\define("TFISH_RANGEFINDER_SEARCH_HELP", "Matches the locality name, the publisher's own place text and the state or province.");
// Ploidy is a filter in its own right here, not fused into the species key as it is on the map, so
// "every tetraploid record whatever its name" becomes askable. The NULL warning is not optional:
// most records state no ploidy, and reading silence as diploid would invent data.
\define("TFISH_RANGEFINDER_PLOIDY", "Ploidy");
\define("TFISH_RANGEFINDER_PLOIDY_ANY", "---");
\define("TFISH_RANGEFINDER_PLOIDY_HELP", "Selects only records that state a ploidy. Most do not \u{2014} an empty ploidy means \u{201c}not recorded\u{201d}, never \u{201c}diploid\u{201d}.");
\define("TFISH_RANGEFINDER_PLOIDY_NOT_RECORDED", "Not recorded");
\define("TFISH_RANGEFINDER_DATASET", "Source dataset");
\define("TFISH_RANGEFINDER_DATASET_ANY", "All sources");
\define("TFISH_RANGEFINDER_BASIS", "Evidence");
\define("TFISH_RANGEFINDER_BASIS_ANY", "Any evidence");
\define("TFISH_RANGEFINDER_BASIS_HELP", "What the record is based on \u{2014} a preserved specimen, a living sample, a sequenced tissue sample or a human observation.");
\define("TFISH_RANGEFINDER_YEARS", "Years");
\define("TFISH_RANGEFINDER_YEAR_FROM", "From");
\define("TFISH_RANGEFINDER_YEAR_TO", "To");
// Stated in words because it is invisible in a result count: a year range can only be satisfied by
// a record that carries a date, so the undated ones drop out of any range at all.
\define("TFISH_RANGEFINDER_YEARS_HELP", "A year range excludes records with no date. {count} of the {total} records are undated.");
\define("TFISH_RANGEFINDER_SEQUENCES", "Molecular data");
\define("TFISH_RANGEFINDER_SEQUENCES_ONLY", "Records with sequence accessions");
\define("TFISH_RANGEFINDER_GEO", "Mapping");
\define("TFISH_RANGEFINDER_GEO_ANY", "Mapped and unmapped");
\define("TFISH_RANGEFINDER_GEO_MAPPED", "Mapped only");
\define("TFISH_RANGEFINDER_GEO_UNMAPPED", "Unmapped only");
\define("TFISH_RANGEFINDER_LOCALITY", "Locality");
\define("TFISH_RANGEFINDER_RECORD_SET", "Record set");
\define("TFISH_RANGEFINDER_QUARANTINE_SET", "Place-name-only records");
// The D-6 quarantine banner. These records are held deliberately: they name a place but carry no
// coordinates, so they cannot be mapped without a geocoding pass, and they must never be presented
// as though they had a position.
\define("TFISH_RANGEFINDER_QUARANTINE_NOTICE", "These {count} records name a place but carry no coordinates, so they cannot be plotted on the map. They are listed here so nothing is lost; a future georeferencing pass may resolve some of them to a position.");
\define("TFISH_RANGEFINDER_QUARANTINE_SHOW", "Show place-name-only records");
\define("TFISH_RANGEFINDER_CURATED_SHOW", "Back to curated records");

// Filter summary ("showing" line, and the CSV export preamble).
\define("TFISH_RANGEFINDER_FILTER_NONE", "none");
\define("TFISH_RANGEFINDER_FILTER_YES", "yes");
\define("TFISH_RANGEFINDER_FILTER_ACTIVE", "Filtered by");
\define("TFISH_RANGEFINDER_FILTER_APPLY", "Apply filters");

// Table headings.
\define("TFISH_RANGEFINDER_COL_NAME", "Scientific name");
\define("TFISH_RANGEFINDER_COL_CONFIDENCE", "Confidence");
\define("TFISH_RANGEFINDER_COL_PLOIDY", "Ploidy");
\define("TFISH_RANGEFINDER_COL_LOCALITY", "Locality");
\define("TFISH_RANGEFINDER_COL_COUNTRY", "Country");
\define("TFISH_RANGEFINDER_COL_COORDINATES", "Coordinates");
\define("TFISH_RANGEFINDER_COL_DATE", "Date");
\define("TFISH_RANGEFINDER_COL_MATERIAL", "Material");
\define("TFISH_RANGEFINDER_COL_SOURCE", "Source");
\define("TFISH_RANGEFINDER_COL_DETAIL", "Record");

// Cell contents.
\define("TFISH_RANGEFINDER_NOT_MAPPED", "Not mapped");
\define("TFISH_RANGEFINDER_UNDATED", "Undated");
// A corrected coordinate says so (D-21). The registry is the only path by which a published
// coordinate is overridden, and a record that went through it is not presented as source-original.
\define("TFISH_RANGEFINDER_COORD_CORRECTED", "Coordinate corrected from the published value");
\define("TFISH_RANGEFINDER_COORD_SUPPRESSED", "Published coordinate withdrawn as erroneous");
\define("TFISH_RANGEFINDER_CANONICAL_AS", "Grouped as {name}");
\define("TFISH_RANGEFINDER_SHOW_DETAIL", "Full record");
\define("TFISH_RANGEFINDER_NO_VALUE", "\u{2014}"); // Em dash: the source recorded nothing here.

// Full-record disclosure labels.
\define("TFISH_RANGEFINDER_DETAIL_VERBATIM", "Name as published");
\define("TFISH_RANGEFINDER_DETAIL_CANONICAL", "Grouped as");
\define("TFISH_RANGEFINDER_DETAIL_RANK", "Rank");
\define("TFISH_RANGEFINDER_DETAIL_QUALIFIER", "Identification qualifier");
\define("TFISH_RANGEFINDER_DETAIL_DETERMINATION", "Determination confidence");
\define("TFISH_RANGEFINDER_DETAIL_BASIS", "Evidence");
\define("TFISH_RANGEFINDER_DETAIL_INSTITUTION", "Institution code");
\define("TFISH_RANGEFINDER_DETAIL_COLLECTION", "Collection code");
\define("TFISH_RANGEFINDER_DETAIL_ACCESSION", "Catalogue number");
\define("TFISH_RANGEFINDER_DETAIL_PREPARATIONS", "Preparations");
\define("TFISH_RANGEFINDER_DETAIL_DISPOSITION", "Material status");
\define("TFISH_RANGEFINDER_DETAIL_COUNT", "Individuals");
\define("TFISH_RANGEFINDER_DETAIL_SEQUENCES", "Sequence accessions");
\define("TFISH_RANGEFINDER_DETAIL_REMARKS", "Occurrence remarks");
\define("TFISH_RANGEFINDER_DETAIL_RECORDER", "Recorded by");
\define("TFISH_RANGEFINDER_DETAIL_CITATION", "Citation");
\define("TFISH_RANGEFINDER_DETAIL_DOI", "DOI");
\define("TFISH_RANGEFINDER_DETAIL_LICENSE", "Licence");
\define("TFISH_RANGEFINDER_DETAIL_PLACE_TEXT", "Place as published");
\define("TFISH_RANGEFINDER_DETAIL_WATER_BODY", "Water body");
\define("TFISH_RANGEFINDER_DETAIL_GEOREF_STATUS", "Georeferencing");

// Results furniture.
\define("TFISH_RANGEFINDER_RESULT_COUNT", "{count} records");
\define("TFISH_RANGEFINDER_RESULT_ONE", "1 record");
\define("TFISH_RANGEFINDER_RESULT_NONE", "No records match these filters.");
\define("TFISH_RANGEFINDER_RESULT_NONE_HELP", "Try removing a filter. A year range excludes undated records, and a ploidy selection excludes records that state no ploidy.");
\define("TFISH_RANGEFINDER_PER_PAGE", "Per page");

// The two handoff links (map <-> table). Each names what it carries, and the map link names what it
// cannot carry: a link that silently widened the set would look like a data error, not a lost filter.
\define("TFISH_RANGEFINDER_VIEW_AS_TABLE", "View as table");
\define("TFISH_RANGEFINDER_VIEW_AS_TABLE_HELP", "Opens these records in the Explore table, carrying the current filters.");
\define("TFISH_RANGEFINDER_SHOW_ON_MAP", "Show on map");
\define("TFISH_RANGEFINDER_SHOW_ON_MAP_HELP", "Plots these records on the map, carrying the filters the map can express.");
\define("TFISH_RANGEFINDER_SHOW_ON_MAP_DROPPED", "The map cannot filter by {filters}, so it will show a wider set than this table.");
\define("TFISH_RANGEFINDER_LOCALITY_ON_MAP", "Show this locality on the map");

// CSV export (D-7). The preamble states what the file is and on what terms it may be used; the
// per-row licence/citation columns repeat it, because a preamble is stripped by anything that parses
// the file as data and a pasted subset loses it entirely.
\define("TFISH_RANGEFINDER_EXPORT", "Download CSV");
\define("TFISH_RANGEFINDER_EXPORT_HELP", "Downloads these records, with the filters currently applied, as a CSV file including per-record source and licence.");
\define("TFISH_RANGEFINDER_EXPORT_TITLE", "Artemia occurrence records \u{2014} filtered extract");
\define("TFISH_RANGEFINDER_EXPORT_DATE", "Exported");
\define("TFISH_RANGEFINDER_EXPORT_SOURCE", "Source");
\define("TFISH_RANGEFINDER_EXPORT_RECORDS", "Records in this file");
\define("TFISH_RANGEFINDER_EXPORT_FILTERS", "Filters applied");
\define("TFISH_RANGEFINDER_EXPORT_NO_FILTERS", "none (complete dataset)");
\define("TFISH_RANGEFINDER_EXPORT_TERMS", "Terms for this file as a whole");
\define("TFISH_RANGEFINDER_EXPORT_LICENSES", "Licences present in this file");
// The combined figure is the most restrictive licence present: a mixed extract cannot be offered under
// its most permissive terms, because that would licence away rights the restrictive rows never granted.
\define("TFISH_RANGEFINDER_EXPORT_TERMS_NC", "CC-BY-NC-4.0 (non-commercial: the most restrictive licence present governs the combined file)");
\define("TFISH_RANGEFINDER_EXPORT_TERMS_MIXED", "mixed \u{2014} see the per-record license column; the most restrictive terms present govern the combined file");
\define("TFISH_RANGEFINDER_EXPORT_TERMS_UNKNOWN", "not stated by the sources in this extract \u{2014} treat as all rights reserved until confirmed");
\define("TFISH_RANGEFINDER_EXPORT_ATTRIBUTION", "Attribution: cite the per-record source_citation and source_doi columns, not this file. Each record's licence applies to that record.");
\define("TFISH_RANGEFINDER_EXPORT_GBIF_TERMS", "GBIF-sourced records are subject to the GBIF terms of use: https://www.gbif.org/terms");
// The one caveat the file must carry, because a spreadsheet strips every visual cue the interface uses
// to keep a lead from reading as a determination.
\define("TFISH_RANGEFINDER_EXPORT_CAVEAT", "The confidence column is load-bearing: only 'verified' rows are expert species determinations. 'reported' and 'unidentified' rows are unverified leads and must not be cited as species records.");

// Errors.
\define("TFISH_RANGEFINDER_DB_UNAVAILABLE", "The occurrence database is currently unavailable. Please try again later.");
\define("TFISH_RANGEFINDER_NO_TILE_PROVIDER", "No basemap is configured, so the map cannot be drawn. This is a configuration problem, not an absence of occurrence records.");
