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
    use \Tfish\Rangefinder\Traits\RangefinderCountries;

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
    private array $taxa = [];
    private array $countryOptions = [];
    private array $countryBounds = [];
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
        $this->buildCountryFacet($this->getCountryFacet());
        $this->summary = $this->buildSummary();
    }

    /**
     * The minimal marker payload: one lean row per geolocated occurrence.
     *
     * Deliberately not the full record. It carries marker coordinates, the filter keys
     * (canonical_taxon + ploidy, layer, taxon_rank, country_code, holding_type) and the card fields
     * the popup drill-down renders (D-20) — and nothing else. Everything wider stays in
     * v_occurrence_detail for the Phase 3 CSV / Phase 4 explore queries, which read it directly.
     *
     * Select only what is used: a column added here is shipped to every visitor on every page load,
     * once per record.
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
                    coordinate_radius_m,
                    canonical_taxon,
                    ploidy,
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
     * row, so country_name here is best-available and buildCountryFacet() fills the gaps from the
     * ISO-3166 table in RangefinderCountries.
     *
     * n_mapped is the only tally selected. The view also offers n_records / n_species / n_presence,
     * but nothing renders them — the headline counts come from the marker payload itself
     * (buildSummary), so counting the same thing twice from two sources could only drift.
     *
     * @return  array List of country facet rows.
     */
    public function getCountryFacet(): array
    {
        return $this->select(
            "SELECT country_code, country_name, n_mapped,
                    min_lat, max_lat, min_lng, max_lng
             FROM   v_country_facet
             ORDER  BY (country_name IS NULL), country_name, country_code"
        );
    }

    /**
     * Resolve the country facet into the filter's option list and the client's bounding boxes.
     *
     * Two products, because they serve two different consumers and only one of them has to reach
     * the browser. The option list is what templates/map.html renders into the <select>: it is
     * static from the moment the page is built, so it is built here rather than assembled in
     * JavaScript from a shipped facet. The bounding boxes do have to ship, because they are read at
     * interaction time — when a country selection plots nothing, the client falls back to the
     * country's box to move the map somewhere meaningful (see frame() in rangefinder.js).
     *
     * Zero-mapped countries are dropped from the option list. v_country_facet already excludes them;
     * this guards an older database where it did not, so a visitor cannot pick a country and be
     * shown an empty map.
     *
     * Names come from the database where it has one and from the ISO-3166 table only where it does
     * not (17 of 65 countries on the live dataset) — a source-recorded name is never overwritten
     * with a standardised one. Ordering is by the accent-folded name, so an accented initial files
     * with its letter instead of after Z.
     *
     * @param   array $rows Country facet rows from v_country_facet.
     */
    private function buildCountryFacet(array $rows): void
    {
        $options = [];
        $bounds = [];

        foreach ($rows as $row) {
            $code = (string) $row['country_code'];

            $bounds[] = [
                'code' => $code,
                'min_lat' => $row['min_lat'],
                'max_lat' => $row['max_lat'],
                'min_lng' => $row['min_lng'],
                'max_lng' => $row['max_lng'],
            ];

            if (empty($row['n_mapped'])) continue;

            $name = $this->countryName($code, $row['country_name']);

            $options[] = [
                'code' => $code,
                'name' => $name,
                'sort' => $this->countrySortKey($name),
                'n_mapped' => (int) $row['n_mapped'],
            ];
        }

        \usort($options, static function (array $a, array $b): int {
            return $a['sort'] <=> $b['sort'];
        });

        $this->countryOptions = $options;
        $this->countryBounds = $bounds;
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
     *   localities[]  = [site_key, name, latitude, longitude]
     *   occurrences[] = [localityIndex, canonical_taxon, ploidy, layer, country_code, holding_type,
     *                    taxon_rank, category]
     *   details[]     = [event_date, recorded_by, sourceIdx, catalog_number, disposition,
     *                    references_url, holder_url, radius_m]
     *   taxa{}        = canonical_taxon -> display form
     *
     * category is the confidence bucket (D-18) — verified / reported / unidentified — decided here
     * and shipped as a value, so the filter, the popup and the headline counts all read one field
     * rather than each re-deriving the rule. taxa{} likewise carries the display form of every name
     * that can appear in a popup, so scientific-name formatting stays a PHP concern. details[] is
     * aligned to occurrences[] by construction (built in the same pass) and carries the record-level
     * card fields inline (D-20) — no detail endpoint, no fetch.
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
        $taxa = [];

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
                ];
            }

            $canonical = $row['canonical_taxon'];
            $ploidy = $row['ploidy'];
            $layer = $row['layer'];
            $rank = $row['taxon_rank'];

            // Demote an unrecognised reported name to a genus-level lead so its name does not appear
            // on the map. Only ever narrows a presence-layer claim; verified determinations are left
            // exactly as the database has them.
            //
            // The whitelist is checked for EVERY presence-layer record, whatever rank it declares.
            // Testing rank === 'species' first would let anything filed at genus keep its name, and
            // the broad-GBIF layer files plenty of non-names there: BOLD BIN codes ('BOLD:AAD2313'),
            // and pipeline non-assignments ('unclassified.Artemia urmiana', which put an epithet on
            // screen against a record whose own label says it could not be classified). Rank is the
            // publisher's claim about their name; the whitelist is our test of it, and the test has
            // to run on the name itself.
            if ($layer === 'presence' && !$this->isAcceptedTaxon($canonical)) {
                $rank = 'genus';
                $canonical = null;
            }

            // Confidence bucket (D-18), decided here and nowhere else. This is the load-bearing
            // rule of the whole interface — it is what keeps an unverified lead from being read as
            // an expert determination — so it exists in exactly one place and is shipped as a
            // value. The client does not re-derive it, and neither does buildSummary(): the
            // headline counts and the map are counting the same field, so they cannot drift apart.
            //
            // Derived AFTER the demotion above, so a lead whose species name was not recognised
            // (rank forced to genus) correctly falls to 'unidentified' rather than 'reported'.
            if ($layer === 'species') {
                $category = 'verified';      // An expert determination.
            } elseif (($rank ?? 'genus') === 'genus') {
                $category = 'unidentified';  // A lead reaching only the genus, or rank unrecorded.
            } else {
                $category = 'reported';      // A species name, but not from an authoritative source.
            }

            $occurrences[] = [
                $index[$localityId],
                $canonical,
                $ploidy,
                $layer,
                $row['country_code'],
                $row['holding_type'],
                $rank,
                $category,
            ];

            // Display form of every name that can appear in a popup, resolved once per taxon rather
            // than repeated on each of the 2,000-odd records that carry it. Shipping this is what
            // lets the client render a name without knowing how a scientific name is formatted.
            if ($canonical !== null) {
                $taxa[$canonical] = $this->displayTaxon($canonical);
            }

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
                // Accuracy radius in metres, per record. Nullable and load-bearing: NULL means the
                // record declares no accuracy, and the client must then draw no circle rather than
                // a default-radius one. Casting it to 0 here would invent a certainty the data
                // lacks. It rides in details[] rather than localities[] because it belongs to one
                // record: a site is a ~1 km cluster and its members' radii can differ by four
                // orders of magnitude, so a per-marker circle would describe none of them.
                isset($row['coordinate_radius_m']) ? (int) $row['coordinate_radius_m'] : null,
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
                        // The checkbox value, and the key matches() compares against. Ploidy is
                        // part of the identity, not a detail of it: parthenogenetic lineages of
                        // differing ploidy share a name while being biologically distinct, so
                        // collapsing them would merge populations the dataset keeps apart. Mirrors
                        // taxonKey() in rangefinder.js — the two must agree exactly or a selection
                        // matches nothing.
                        'key' => $canonical . '~' . ($ploidy ?? ''),
                        // Display form: the canonical is already citation-free and lower-cased. For a
                        // binomial, abbreviate the genus to its initial ("artemia franciscana" ->
                        // "A. franciscana") to keep the filter list compact; a single-word lineage just
                        // gets its initial capitalised. Taxon-agnostic: no genus name is hard-coded.
                        'display_name' => $this->abbreviateTaxon($canonical),
                        // v_map_markers is mapped-only, so every row counted here plots. There is no
                        // separate total: an unmapped record cannot reach this loop.
                        'n_mapped' => 0,
                    ];
                }

                ++$facet[$fkey]['n_mapped'];
            }
        }

        // Group by name, then order a lineage's variants by increasing ploidy level with unknown
        // (no ploidy word) first — A. parthenogenetica, then diploid, triploid, tetraploid — rather
        // than by the ploidy word's alphabetical order, which is meaningless.
        $facet = \array_values($facet);
        \usort($facet, static function (array $a, array $b): int {
            return [$a['canonical_taxon'], self::ploidyRank($a['ploidy'])]
                <=> [$b['canonical_taxon'], self::ploidyRank($b['ploidy'])];
        });

        \ksort($taxa);

        $this->localities = $localities;
        $this->occurrences = $occurrences;
        $this->details = $details;
        $this->speciesFacet = $facet;
        $this->taxa = $taxa;
    }

    /**
     * Sort rank for a ploidy word.
     *
     * Unknown (none) first, then by increasing chromosome-set count, with the open-ended
     * "polyploid" last. Only orders a lineage's variants within the filter list; an unrecognised
     * value sorts after the known set rather than throwing.
     *
     * @param   string|null $ploidy Ploidy word as stored.
     * @return  int
     */
    private static function ploidyRank(?string $ploidy): int
    {
        $order = ['diploid' => 1, 'triploid' => 2, 'tetraploid' => 3, 'pentaploid' => 4, 'polyploid' => 5];

        if ($ploidy === null || $ploidy === '') return 0;

        return $order[$ploidy] ?? 6;
    }

    /**
     * Full display form of a canonical taxon, for popups and record lines.
     *
     * Names arrive as the canonical_taxon: already citation-free, rank-marker-free and lower-cased
     * at import (the one place normalisation happens). Display just restores binomial case by
     * capitalising the initial — genus up, epithet down — so there is nothing to parse.
     *
     * The compact filter list uses abbreviateTaxon() instead, which shortens the genus to an
     * initial. Two deliberately different forms, both decided here, so how a scientific name is
     * written is a PHP concern only and cannot drift between the page and the map.
     *
     * @param   string $canonical Lower-cased, citation-free canonical taxon name.
     * @return  string
     */
    private function displayTaxon(string $canonical): string
    {
        return \ucfirst($canonical);
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
     * The three confidence buckets are counted straight off the category assigned in
     * buildMarkerPayload — the same field the map filters on — so the headline figures and the
     * markers are counting one thing and cannot disagree. The rule that decides the category is
     * documented there and lives only there. verified + reported + unidentified = records.
     *
     * @return  array ['records', 'localities', 'verified', 'reported', 'unidentified', 'countries'].
     */
    private function buildSummary(): array
    {
        $countries = [];
        $buckets = ['verified' => 0, 'reported' => 0, 'unidentified' => 0];

        foreach ($this->occurrences as $occurrence) {
            if (!empty($occurrence[4])) {
                $countries[(string) $occurrence[4]] = true;
            }

            ++$buckets[$occurrence[7]];
        }

        return [
            'records' => \count($this->occurrences),
            'localities' => \count($this->localities),
            'verified' => $buckets['verified'],
            'reported' => $buckets['reported'],
            'unidentified' => $buckets['unidentified'],
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
     * @return  array List of [site_key, name, latitude, longitude] tuples.
     */
    public function localities(): array
    {
        $this->loadMap();

        return $this->localities;
    }

    /**
     * Return the loaded occurrence tuples, each referencing a locality by index.
     *
     * @return  array List of [localityIndex, canonical_taxon, ploidy, layer, country_code,
     *          holding_type, taxon_rank, category] tuples.
     */
    public function occurrences(): array
    {
        $this->loadMap();

        return $this->occurrences;
    }

    /**
     * Return the display form of every taxon name that can appear in a popup.
     *
     * @return  array Map of canonical_taxon => display form.
     */
    public function taxa(): array
    {
        $this->loadMap();

        return $this->taxa;
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
     * Return the loaded species / lineage facet, ordered for the filter list.
     *
     * @return  array List of ['canonical_taxon', 'ploidy', 'key', 'display_name', 'n_mapped'] rows.
     */
    public function speciesFacet(): array
    {
        $this->loadMap();

        return $this->speciesFacet;
    }

    /**
     * Return the country filter's options: mapped countries only, name-resolved and name-ordered.
     *
     * @return  array List of ['code', 'name', 'sort', 'n_mapped'] rows.
     */
    public function countryOptions(): array
    {
        $this->loadMap();

        return $this->countryOptions;
    }

    /**
     * Return per-country bounding boxes, for reframing the map when a selection plots nothing.
     *
     * @return  array List of ['code', 'min_lat', 'max_lat', 'min_lng', 'max_lng'] rows.
     */
    public function countryBounds(): array
    {
        $this->loadMap();

        return $this->countryBounds;
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
