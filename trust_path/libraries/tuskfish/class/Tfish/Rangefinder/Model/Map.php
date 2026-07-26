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
 * come from v_map_markers and the country dropdown (with its bounding boxes) from v_country_facet.
 * The species/lineage dropdown is derived in PHP from the marker rows (see buildMarkerPayload), not
 * queried, so it can key on the derived canonical_taxon and include accepted reported taxa. The
 * views encode the load-bearing domain rules, so going through them is what keeps those rules
 * enforced:
 *
 * - Taxonomy is the *verbatim* determination as supplied by the original source. The GBIF backbone
 *   name (backbone_species) encodes a contested synonymy that rewrites ~70% of the curated IATS
 *   determinations, and the views do not expose it. Never filter, group or label on it.
 * - Records carry a layer: 'species' (verified determination) or 'presence' (a lead, from the broad
 *   GBIF sweep). Presence records must stay visually distinct and must never be presented as species
 *   claims. The client subdivides the lead layer by taxon_rank into "reported to species" (a species
 *   name from a non-authoritative source) and "not identified to species" (genus only) — D-18.
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
    use \Tfish\Rangefinder\Traits\RangefinderTaxonomy;

    private $database;
    private $preference;
    private $session;
    private \Tfish\Logger $logger;

    private bool $loaded = false;
    private array $localities = [];
    private array $occurrences = [];
    private array $details = [];
    private array $sources = [];
    private array $sourceIndex = [];
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
        // Sources first: buildMarkerPayload interns each occurrence's dataset_key to a sources[]
        // index while it walks the marker rows, so the lookup must exist before that pass.
        $this->buildSources($this->getSources());
        // Builds the marker payload (with the aligned details[] layer), the species/lineage facet
        // and the summary in one pass over the marker rows (the facet is derived here, not queried,
        // so it can key on the derived canonical_taxon and include accepted reported taxa — see
        // buildMarkerPayload).
        $this->buildMarkerPayload($this->getMarkers());
        $this->countryFacet = $this->getCountryFacet();
        $this->summary = $this->buildSummary();
    }

    /**
     * The minimal marker payload: one lean row per geolocated occurrence.
     *
     * Deliberately not the full record. This ships to the browser to drive client-side filtering,
     * so it carries only marker coordinates plus the filter keys (verbatim name + ploidy,
     * layer, taxon_rank, country_code, holding_type). Full occurrence detail is fetched on demand from
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
                    site_key,
                    locality_name,
                    decimal_latitude,
                    decimal_longitude,
                    coordinate_precision_m,
                    canonical_taxon,
                    ploidy,
                    determination_confidence,
                    layer,
                    taxon_rank,
                    country_code,
                    holding_type,
                    event_date,
                    year,
                    recorded_by,
                    dataset_key,
                    catalog_number,
                    disposition,
                    references_url,
                    holder_url
             FROM   v_map_markers
             ORDER  BY locality_id, occurrence_id"
        );
    }

    /**
     * The source-attribution lookup: one row per dataset that a mapped occurrence cites.
     *
     * Every occurrence carries a dataset_key; this resolves it through v_source_acknowledgement to
     * the citable source — its display label, full citation, DOI and licence. A record's provenance
     * is its *dataset*, not the coarse holder institution (which has only two values and cannot tell
     * a Field Museum record from a Naturalis one). A publication is modelled the same way: a dataset
     * with a 'lit:' key whose citation holds the "Author et al. year" form, so literature and
     * institutional sources share one rendering path with no special-casing (D-18/D-20).
     *
     * Deduped and interned to an index in buildSources so the (≤89) distinct sources are shipped once
     * and each occurrence references one by a small integer, not by repeating the citation inline.
     *
     * @return  array List of source rows from v_source_acknowledgement.
     */
    public function getSources(): array
    {
        return $this->select(
            "SELECT dataset_key, dataset, citation, doi, license
             FROM   v_source_acknowledgement
             ORDER  BY dataset_key"
        );
    }

    /**
     * Intern the source rows into a shipped sources[] list plus a dataset_key -> index map.
     *
     * The map is what buildMarkerPayload uses to turn each occurrence's dataset_key into the small
     * integer its details[] tuple carries, so the (long) citation text is shipped once per source
     * rather than once per record.
     *
     *   sources[] = [ [key, label, citation, doi, license], … ]   // positional, mirrors details[]
     *
     * @param   array $rows Source rows from v_source_acknowledgement.
     */
    private function buildSources(array $rows): void
    {
        $sources = [];
        $index = [];

        foreach ($rows as $row) {
            $key = (string) $row['dataset_key'];
            $index[$key] = \count($sources);
            $sources[] = [
                $key,
                $row['dataset'],
                $row['citation'],
                $row['doi'],
                $row['license'],
            ];
        }

        $this->sources = $sources;
        $this->sourceIndex = $index;
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
     *   localities[]  = [site_key, name, latitude, longitude, precision_m]
     *   occurrences[] = [localityIndex, canonical_taxon, ploidy, layer, country_code, holding_type, taxon_rank]
     *   details[]     = [event_date, recorded_by, sourceIdx, catalog_number, disposition,
     *                    references_url, holder_url]
     *
     * taxon_rank rides last (on the occurrence tuple) so the client can split the lead layer into
     * "reported to species" (rank=species) and "not identified to species" (rank=genus) without a
     * second request (D-18). details[] is aligned to occurrences[] by construction (built in the same
     * pass) and carries the record-level card fields inline (D-20) — no detail endpoint, no fetch.
     *
     * The name shipped is the derived canonical_taxon (author citation and rank markers stripped,
     * lower-cased at import), not the verbatim string: it is both the filter key and the display
     * source, and keying on it is what lets a species selection match verified and reported records
     * of the same taxon whose verbatim spellings differ. The verbatim name stays in the database
     * (authority, traceback) and is served with the full record on demand; the GBIF backbone name is
     * never in the view and must never reach the client as a species claim.
     *
     * A reported (presence-layer) name that is not a currently-recognised taxon is demoted here: its
     * shipped taxon_rank is forced to 'genus' and its name suppressed, so it renders as "not
     * identified to species" and its unrecognised species name never appears on the map. This is a
     * UI decision made against the accepted-taxa whitelist (RangefinderTaxonomy), matched on the
     * stored canonical_taxon; verified determinations are never demoted. See the trait for the full
     * rationale.
     *
     * The species/lineage facet is assembled in the same pass (not a separate query) so it can key on
     * canonical_taxon and include accepted reported taxa alongside verified ones — a demoted reported
     * name is excluded, exactly as it is suppressed on the map. The facet is the mapped set only
     * (v_map_markers excludes coordinate-less rows), so its tally is what actually plots.
     *
     * @param   array $rows Marker rows from v_map_markers.
     */
    private function buildMarkerPayload(array $rows): void
    {
        $localities = [];
        $occurrences = [];
        $details = [];
        $index = [];
        $facet = [];

        foreach ($rows as $row) {
            $localityId = (int) $row['locality_id'];

            if (!isset($index[$localityId])) {
                $index[$localityId] = \count($localities);
                $localities[] = [
                    // Stable site identity (D-22). locality_id is assigned by cluster order at
                    // import and renumbers on any rebuild that changes the cluster set, so it
                    // groups rows within this payload and nothing more; site_key is what any
                    // permalink, bookmark or citation must use.
                    $row['site_key'],
                    $row['locality_name'],
                    (float) $row['decimal_latitude'],
                    (float) $row['decimal_longitude'],
                    // Nullable and load-bearing: a NULL precision means the source declared none,
                    // and the client must then draw no circle at all rather than a default-radius
                    // one. Casting it to 0 here would silently invent a certainty the data lacks.
                    isset($row['coordinate_precision_m']) ? (int) $row['coordinate_precision_m'] : null,
                ];
            }

            $canonical = $row['canonical_taxon'];
            $ploidy = $row['ploidy'];
            $layer = $row['layer'];
            $rank = $row['taxon_rank'];

            // Demote an unrecognised reported name to a genus-level lead so its species name does not
            // appear on the map. Only ever narrows a presence-layer species claim; verified
            // determinations and names already at genus are left exactly as the database has them.
            if ($layer === 'presence' && $rank === 'species' && !$this->isAcceptedTaxon($canonical)) {
                $rank = 'genus';
                $canonical = null;
            }

            $occurrences[] = [
                $index[$localityId],
                $canonical,
                $ploidy,
                $layer,
                $row['country_code'],
                $row['holding_type'],
                $rank,
            ];

            // details[] is aligned index-identically to occurrences[] (same order, same length), so
            // no join key is shipped — the card reads details[i] for occurrences[i]. A demoted lead
            // keeps its real provenance here; only its taxon name (in occurrences[] above) is
            // suppressed, so the card shows date/source with no species claim (no leak). event_date
            // falls back to year when the source gave no finer date; holding_type is not repeated
            // (it already rides in occurrences[] as filter key #6) but disposition is, as it carries
            // the finer material status the accession does not. basis_of_record and occurrence_remarks
            // are deliberately NOT shipped: the card does not render them, and shipping them inline
            // was ~93 KB of dead payload — they stay in v_occurrence_detail for the Phase 3 CSV /
            // Phase 4 explore queries, which read them directly rather than off this payload.
            $datasetKey = (string) ($row['dataset_key'] ?? '');
            $details[] = [
                $row['event_date'] ?? $row['year'],
                $row['recorded_by'],
                $this->sourceIndex[$datasetKey] ?? null,
                $row['catalog_number'],
                $row['disposition'],
                $row['references_url'],
                $row['holder_url'],
            ];

            // Facet: one choice per (canonical, ploidy) that carries a species-level name — every
            // verified taxon and every accepted reported taxon. Demoted (canonical now null) and
            // genus-only leads are excluded, so the list offers only names that make a species claim.
            if ($canonical !== null && ($layer === 'species' || $rank === 'species')) {
                $fkey = $canonical . "\0" . ($ploidy ?? '');

                if (!isset($facet[$fkey])) {
                    $facet[$fkey] = [
                        'canonical_taxon' => $canonical,
                        'ploidy' => $ploidy,
                        // Display form: the canonical is already citation-free and lower-cased. For a
                        // binomial, abbreviate the genus to its initial ("artemia franciscana" ->
                        // "A. franciscana") to keep the filter list compact; a single-word lineage just
                        // gets its initial capitalised. Taxon-agnostic: no genus name is hard-coded.
                        'display_name' => $this->abbreviateTaxon($canonical),
                        'n_records' => 0,
                        'n_mapped' => 0,
                    ];
                }

                ++$facet[$fkey]['n_records'];
                ++$facet[$fkey]['n_mapped']; // v_map_markers is mapped-only, so every row here plots.
            }
        }

        $facet = \array_values($facet);
        \usort($facet, static function (array $a, array $b): int {
            return [$a['canonical_taxon'], $a['ploidy'] ?? '']
                <=> [$b['canonical_taxon'], $b['ploidy'] ?? ''];
        });

        $this->localities = $localities;
        $this->occurrences = $occurrences;
        $this->details = $details;
        $this->speciesFacet = $facet;
    }

    /**
     * Abbreviated display form of a canonical taxon for the compact filter list.
     *
     * A binomial "genus epithet" becomes "G. epithet"; a single-word lineage is just capitalised.
     * The canonical is already lower-cased and citation-free, so this is pure string work — no genus
     * name is hard-coded, keeping the engine taxon-agnostic.
     *
     * @param   string $canonical Lower-cased, citation-free canonical taxon name.
     * @return  string
     */
    private function abbreviateTaxon(string $canonical): string
    {
        $parts = \explode(' ', $canonical, 2);

        if (isset($parts[1]) && $parts[0] !== '') {
            return \strtoupper($parts[0][0]) . '. ' . $parts[1];
        }

        return \ucfirst($canonical);
    }

    /**
     * Headline counts for the page, derived from the loaded marker payload.
     *
     * Derived in PHP rather than by extra round trips: the marker set is already in memory and the
     * counts are exactly its composition, so they cannot drift from what the map actually plots.
     *
     * The three confidence buckets mirror the map's filter (D-18): 'verified' is a determination
     * (layer=species); the two lead buckets split by rank — 'reported' carries a species name from a
     * non-authoritative source, 'unidentified' reaches only genus. verified + reported + unidentified
     * = records.
     *
     * @return  array ['records', 'localities', 'verified', 'reported', 'unidentified', 'countries'].
     */
    private function buildSummary(): array
    {
        $countries = [];
        $verified = 0;
        $reported = 0;
        $unidentified = 0;

        foreach ($this->occurrences as $occurrence) {
            if (!empty($occurrence[4])) {
                $countries[(string) $occurrence[4]] = true;
            }

            if ($occurrence[3] === 'species') {
                ++$verified;
            } elseif (($occurrence[6] ?? 'genus') === 'genus') {
                // Lead with no species claim: genus-only, or a rank the importer left null.
                ++$unidentified;
            } else {
                // Lead carrying a species (or below) name, but not from an authoritative source.
                ++$reported;
            }
        }

        return [
            'records' => \count($this->occurrences),
            'localities' => \count($this->localities),
            'verified' => $verified,
            'reported' => $reported,
            'unidentified' => $unidentified,
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
     * @return  array List of [site_key, name, latitude, longitude, precision_m] tuples.
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
     * Return the loaded record-level detail tuples, aligned index-identically to occurrences().
     *
     * @return  array List of [event_date, recorded_by, sourceIdx, catalog_number, disposition,
     *          references_url, holder_url] tuples.
     */
    public function details(): array
    {
        $this->loadMap();

        return $this->details;
    }

    /**
     * Return the interned source-attribution list (dataset label + citation per source).
     *
     * @return  array List of [key, label, citation, doi, license] tuples, indexed by details' sourceIdx.
     */
    public function sources(): array
    {
        $this->loadMap();

        return $this->sources;
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
     * @return  array ['records', 'localities', 'verified', 'reported', 'unidentified', 'countries'].
     */
    public function summary(): array
    {
        $this->loadMap();

        return $this->summary;
    }
}
