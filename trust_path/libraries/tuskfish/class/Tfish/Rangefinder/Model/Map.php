<?php

declare(strict_types=1);

namespace Tfish\Rangefinder\Model;

/**
 * \Tfish\Rangefinder\Model\Map class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */

/**
 * Model for the Rangefinder occurrence map (/map/).
 *
 * Reads the occurrence database through its published views only, never the base tables: markers
 * come from v_map_markers, the species/lineage dropdown from v_species_facet, and the country
 * dropdown (with its bounding boxes) from v_country_facet. The views encode the load-bearing
 * domain rules, so going through them is what keeps those rules enforced:
 *
 * - Taxonomy is the *verbatim* determination as supplied by the original source. The GBIF backbone
 *   name (backbone_species) encodes a contested synonymy that rewrites ~70% of the curated IATS
 *   determinations, and the views do not expose it. Never filter, group or label on it.
 * - Records carry a layer: 'species' (verified determination) or 'presence' (genus-only, from the
 *   broad GBIF sweep). Presence records must stay visually distinct and must never be presented as
 *   species claims.
 * - Coordinates on a marker are the *locality centroid* (localities are first-class sites gridded
 *   to ~1km at import), so every occurrence at a site stacks on one marker.
 * - Records with no coordinates are quarantined in ungeoreferenced_occurrences and are excluded
 *   from v_map_markers by construction. No map query reads that table.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Rangefinder\Traits\RangefinderDatabase  Read-only occurrence database access.
 */
class Map
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Rangefinder\Traits\RangefinderDatabase;

    private $database;
    private $preference;
    private $session;
    private \Tfish\Logger $logger;

    private array $markers = [];
    private array $speciesFacet = [];
    private array $countryFacet = [];
    private array $summary = [];

    public function __construct(
        \Tfish\Database $database,
        \Tfish\Entity\Preference $preference,
        \Tfish\Session $session,
        \Tfish\Logger $logger
    ) {
        $this->database = $database;
        $this->preference = $preference;
        $this->session = $session;
        $this->logger = $logger;
        $this->connect();
    }

    /**
     * Load everything the map page needs for its initial render.
     */
    public function loadMap(): void
    {
        $this->markers = $this->getMarkers();
        $this->speciesFacet = $this->getSpeciesFacet();
        $this->countryFacet = $this->getCountryFacet();
        $this->summary = $this->buildSummary();
    }

    /**
     * The minimal marker payload: one lean row per geolocated occurrence.
     *
     * Deliberately not the full record. This ships to the browser to drive client-side filtering,
     * so it carries only marker coordinates plus the four filter keys (verbatim name + ploidy,
     * layer, country_code, holding_type). Full occurrence detail is fetched on demand from
     * v_occurrence_detail by a bounded, parameterised query, so the whole curated dataset is never
     * harvestable in a single request.
     *
     * @return  array List of marker rows.
     */
    public function getMarkers(): array
    {
        return $this->select(
            "SELECT occurrence_id,
                    locality_id,
                    locality_name,
                    decimal_latitude,
                    decimal_longitude,
                    coordinate_precision_m,
                    verbatim_scientific_name,
                    ploidy,
                    determination_confidence,
                    layer,
                    country_code,
                    holding_type
             FROM   v_map_markers
             ORDER  BY locality_id, occurrence_id"
        );
    }

    /**
     * Species / lineage filter facet.
     *
     * Distinct verbatim determinations split by ploidy, so a differently-ploid parthenogenetic
     * lineage is a separate choice. Species layer only: presence (genus-only) records are never
     * offered as species names; they surface through the presence toggle instead.
     *
     * @return  array List of ['verbatim_scientific_name', 'ploidy', 'n_records', 'n_mapped'].
     */
    public function getSpeciesFacet(): array
    {
        return $this->select(
            "SELECT verbatim_scientific_name, ploidy, n_records, n_mapped
             FROM   v_species_facet
             ORDER  BY verbatim_scientific_name, ploidy"
        );
    }

    /**
     * Country filter facet, with per-country bounding boxes for map reframing.
     *
     * Keys on country_code, the only reliable key: the display country name is NULL for every GBIF
     * row, so country_name here is best-available and the client fills the gaps from a static
     * ISO-3166 code -> name map.
     *
     * @return  array List of country facet rows.
     */
    public function getCountryFacet(): array
    {
        return $this->select(
            "SELECT country_code, country_name, n_records, n_species, n_presence, n_mapped,
                    min_lat, max_lat, min_lng, max_lng
             FROM   v_country_facet
             ORDER  BY (country_name IS NULL), country_name, country_code"
        );
    }

    /**
     * Headline counts for the page, derived from the loaded marker payload.
     *
     * Derived in PHP rather than by extra round trips: the marker set is already in memory and the
     * counts are exactly its composition, so they cannot drift from what the map actually plots.
     *
     * @return  array ['records', 'localities', 'species', 'presence', 'countries'].
     */
    private function buildSummary(): array
    {
        $localities = [];
        $countries = [];
        $species = 0;
        $presence = 0;

        foreach ($this->markers as $marker) {
            $localities[(string) $marker['locality_id']] = true;

            if (!empty($marker['country_code'])) {
                $countries[(string) $marker['country_code']] = true;
            }

            if ($marker['layer'] === 'species') {
                ++$species;
            } else {
                ++$presence;
            }
        }

        return [
            'records' => \count($this->markers),
            'localities' => \count($localities),
            'species' => $species,
            'presence' => $presence,
            'countries' => \count($countries),
        ];
    }

    /**
     * Return the loaded marker payload.
     *
     * @return  array List of marker rows.
     */
    public function markers(): array
    {
        return $this->markers;
    }

    /**
     * Return the loaded species / lineage facet.
     *
     * @return  array List of species facet rows.
     */
    public function speciesFacet(): array
    {
        return $this->speciesFacet;
    }

    /**
     * Return the loaded country facet.
     *
     * @return  array List of country facet rows.
     */
    public function countryFacet(): array
    {
        return $this->countryFacet;
    }

    /**
     * Return the headline counts for the loaded payload.
     *
     * @return  array ['records', 'localities', 'species', 'presence', 'countries'].
     */
    public function summary(): array
    {
        return $this->summary;
    }
}
