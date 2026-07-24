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

    private bool $loaded = false;
    private array $localities = [];
    private array $occurrences = [];
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
     *
     * Lazy and idempotent. The page is served from the cache whenever one is warm, and a cache hit
     * echoes the cached file and exits *after* the controller action has run — so anything loaded
     * eagerly in the action is queried, built, encoded and then thrown away. Deferring the work to
     * first use means a cache hit touches the occurrence database not at all, which is where
     * essentially the whole cost of this page lives.
     */
    public function loadMap(): void
    {
        if ($this->loaded) return;

        $this->loaded = true;
        $this->buildMarkerPayload($this->getMarkers());
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
     * Split the flat marker rows into distinct localities plus lean occurrence tuples.
     *
     * v_map_markers returns one row per occurrence, each repeating its locality's name and
     * coordinates — and localities are first-class sites, so 2,186 occurrence rows carry only 577
     * distinct localities between them. Emitting the site once and having occurrences reference it
     * by index removes that repetition: measured on the live dataset the payload falls from ~779 KB
     * to ~186 KB with no information lost.
     *
     * The shape is also the one the client actually wants. One locality = one marker (D-1b), so the
     * client's first act on a flat list would be to group it by locality_id; this hands it the
     * grouping already done.
     *
     * Positional tuples rather than objects, because repeating a dozen key names 2,186 times is
     * most of what is left. The index maps are documented in vendor/rangefinder/rangefinder.js,
     * which expands them back into objects on load.
     *
     *   localities[]  = [locality_id, name, latitude, longitude, precision_m]
     *   occurrences[] = [localityIndex, verbatim_name, ploidy, layer, country_code, holding_type]
     *
     * Note occurrences carry the *verbatim* determination and nothing else taxonomic: the GBIF
     * backbone name is not in the view and must never reach the client as a species claim.
     *
     * @param   array $rows Marker rows from v_map_markers.
     */
    private function buildMarkerPayload(array $rows): void
    {
        $localities = [];
        $occurrences = [];
        $index = [];

        foreach ($rows as $row) {
            $localityId = (int) $row['locality_id'];

            if (!isset($index[$localityId])) {
                $index[$localityId] = \count($localities);
                $localities[] = [
                    $localityId,
                    $row['locality_name'],
                    (float) $row['decimal_latitude'],
                    (float) $row['decimal_longitude'],
                    // Nullable and load-bearing: a NULL precision means the source declared none,
                    // and the client must then draw no circle at all rather than a default-radius
                    // one. Casting it to 0 here would silently invent a certainty the data lacks.
                    isset($row['coordinate_precision_m']) ? (int) $row['coordinate_precision_m'] : null,
                ];
            }

            $occurrences[] = [
                $index[$localityId],
                $row['verbatim_scientific_name'],
                $row['ploidy'],
                $row['layer'],
                $row['country_code'],
                $row['holding_type'],
            ];
        }

        $this->localities = $localities;
        $this->occurrences = $occurrences;
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
        $countries = [];
        $species = 0;
        $presence = 0;

        foreach ($this->occurrences as $occurrence) {
            if (!empty($occurrence[4])) {
                $countries[(string) $occurrence[4]] = true;
            }

            if ($occurrence[3] === 'species') {
                ++$species;
            } else {
                ++$presence;
            }
        }

        return [
            'records' => \count($this->occurrences),
            'localities' => \count($this->localities),
            'species' => $species,
            'presence' => $presence,
            'countries' => \count($countries),
        ];
    }

    /**
     * The active basemap tile provider, resolved from the module's config file.
     *
     * The provider is configuration, not code: each entry carries its own tile URL, maximum zoom
     * and attribution string, so switching basemap is a one-line edit of config/tile-providers.php
     * with nothing else to change. A first-class site preference would mean editing four core
     * files (\Tfish\Entity\Preference is a fixed-property class), and this module installs without
     * touching core or a theme at all — a property worth more than a settings screen.
     *
     * Resolution is deliberately forgiving: an unknown 'active' key, a missing entry or an entry
     * whose URL or API key is absent all fall back to the first usable keyless provider, so a typo
     * degrades to a working map rather than a blank one.
     *
     * Only the *active* provider is ever returned, and so only it is ever sent to the browser.
     * Shipping the whole registry would publish the API key of every configured-but-inactive
     * provider to anyone who views source.
     *
     * @return  array ['key', 'label', 'url', 'maxZoom', 'attribution', 'subdomains'], or empty if
     *          no usable provider is configured.
     */
    public function tileProvider(): array
    {
        $configFile = TFISH_RANGEFINDER_CONFIG_PATH . 'tile-providers.php';

        if (!\is_file($configFile)) {
            $this->logger->logError(0, 'Tile provider config not found: ' . $configFile, __FILE__, __LINE__);
            return [];
        }

        $config = include $configFile;

        if (!\is_array($config) || empty($config['providers']) || !\is_array($config['providers'])) {
            $this->logger->logError(0, 'Tile provider config is malformed: ' . $configFile, __FILE__, __LINE__);
            return [];
        }

        $active = (string) ($config['active'] ?? '');

        if (isset($config['providers'][$active])) {
            $resolved = $this->resolveProvider($active, $config['providers'][$active]);

            if (!empty($resolved)) return $resolved;
        }

        foreach ($config['providers'] as $key => $provider) {
            $resolved = $this->resolveProvider((string) $key, $provider);

            if (!empty($resolved)) {
                $this->logger->logError(
                    0,
                    'Tile provider "' . $active . '" is unusable; fell back to "' . $key . '".',
                    __FILE__,
                    __LINE__
                );

                return $resolved;
            }
        }

        $this->logger->logError(0, 'No usable tile provider configured.', __FILE__, __LINE__);

        return [];
    }

    /**
     * Validate one provider entry and substitute its API key into the tile URL.
     *
     * @param   string $key Registry key of the provider.
     * @param   mixed $provider The provider entry.
     * @return  array The resolved provider, or empty if the entry is unusable.
     */
    private function resolveProvider(string $key, $provider): array
    {
        if (!\is_array($provider) || empty($provider['url'])) return [];

        $url = (string) $provider['url'];
        $apiKey = \trim((string) ($provider['apiKey'] ?? ''));

        if (!empty($provider['requiresKey'])) {
            if ($apiKey === '') return [];

            $url = \str_replace('{apiKey}', \rawurlencode($apiKey), $url);
        }

        return [
            'key' => $key,
            'label' => (string) ($provider['label'] ?? $key),
            'url' => $url,
            'maxZoom' => (int) ($provider['maxZoom'] ?? 18),
            'attribution' => (string) ($provider['attribution'] ?? ''),
            'subdomains' => (string) ($provider['subdomains'] ?? 'abc'),
        ];
    }

    /**
     * Return the distinct localities of the loaded marker payload (one per map marker).
     *
     * @return  array List of [locality_id, name, latitude, longitude, precision_m] tuples.
     */
    public function localities(): array
    {
        $this->loadMap();

        return $this->localities;
    }

    /**
     * Return the loaded occurrence tuples, each referencing a locality by index.
     *
     * @return  array List of [localityIndex, verbatim_name, ploidy, layer, country_code,
     *          holding_type] tuples.
     */
    public function occurrences(): array
    {
        $this->loadMap();

        return $this->occurrences;
    }

    /**
     * Return the loaded species / lineage facet.
     *
     * @return  array List of species facet rows.
     */
    public function speciesFacet(): array
    {
        $this->loadMap();

        return $this->speciesFacet;
    }

    /**
     * Return the loaded country facet.
     *
     * @return  array List of country facet rows.
     */
    public function countryFacet(): array
    {
        $this->loadMap();

        return $this->countryFacet;
    }

    /**
     * Return the headline counts for the loaded payload.
     *
     * @return  array ['records', 'localities', 'species', 'presence', 'countries'].
     */
    public function summary(): array
    {
        $this->loadMap();

        return $this->summary;
    }
}
