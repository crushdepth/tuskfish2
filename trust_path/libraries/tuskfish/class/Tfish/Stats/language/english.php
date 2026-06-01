<?php

declare(strict_types=1);

namespace Tfish\Stats;

/**
 * Tuskfish User module language constants (English).
 *
 * Translate this file to convert Tuskfish User module to another language.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     User
 */
// Language constants.
\define("TFISH_STATS", "Stats");

// Separator used when appending active filters (country, species, year) to a page title.
\define("TFISH_STATS_TITLE_SEPARATOR", "\u{2014}"); // Em dash.

// Page titles and meta descriptions.
\define("TFISH_STATS_GLOBAL_OVERVIEW", "Global Overview");
\define("TFISH_STATS_OVERVIEW_TITLE", "Global Fisheries and Aquaculture Statistics");
\define("TFISH_STATS_OVERVIEW_DESCRIPTION", "Explore global fisheries and aquaculture production at a glance: wild catch and farmed output by country and species, drawn from FAO data.");

\define("TFISH_STATS_SPECIES", "Species");
\define("TFISH_STATS_SPECIES_TITLE", "Aquaculture: Farmed Species");
\define("TFISH_STATS_SPECIES_DESCRIPTION", "See which aquatic species are farmed, where, and in what quantities, based on FAO aquaculture statistics.");

\define("TFISH_STATS_PRODUCTION", "Production");
\define("TFISH_STATS_PRODUCTION_TITLE", "Aquaculture: Where is it Farmed?");
\define("TFISH_STATS_PRODUCTION_DESCRIPTION", "Discover where specific commodities are farmed, by country and year, drawn from FAO data.");

\define("TFISH_STATS_ENVIRONMENT", "Environment");
\define("TFISH_STATS_ENVIRONMENT_TITLE", "Aquaculture: Farming Environments");
\define("TFISH_STATS_ENVIRONMENT_DESCRIPTION", "Data on aquaculture production in freshwater, brackish and marine environments across countries, based on FAO statistics.");
