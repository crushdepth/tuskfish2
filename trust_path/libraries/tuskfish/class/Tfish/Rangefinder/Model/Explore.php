<?php

declare(strict_types=1);

namespace Tfish\Rangefinder\Model;

/**
 * \Tfish\Rangefinder\Model\Explore class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */

/**
 * Model for the Rangefinder occurrence table (/explore/).
 *
 * The map's opposite number. /map/ ships the whole marker set once and filters it in the browser,
 * because a cluster count is only truthful if every marker is in the group. A table cannot work that
 * way: it paginates, so the filtering and the counting must both happen in the database, or a page of
 * 25 is sliced from the wrong population and the total describes a set the visitor is not seeing.
 * Everything here is therefore a bounded server query — one page of rows, one count, and the facets
 * behind the controls.
 *
 * Reads published views only (v_occurrence_detail, v_unmapped_records, v_source_acknowledgement,
 * v_country_facet), never a base table, so the load-bearing domain rules stay where the schema
 * enforces them:
 *
 * - the verbatim determination is the authority and is what the table prints; canonical_taxon is the
 *   filter/grouping key and is shown as such; backbone_species is not in any view and never appears.
 * - a presence-layer record is a lead, not a determination, and carries its confidence bucket in
 *   every row so nothing can render without it.
 * - rejected records (D-23) are in rejected_occurrences, which no view exposes — unreachable here by
 *   construction, not by a filter that could be forgotten.
 * - the coordinate-less quarantine (D-6) is reachable through exactly one view, on exactly this
 *   route, and is switched to rather than unioned in, so a pagination total always describes one
 *   population.
 *
 * All input arrives as the normalised filter state from RangefinderFilters (the security boundary);
 * this class never reads the request.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Rangefinder\Traits\RangefinderDatabase  Read-only occurrence database access.
 * @uses        trait \Tfish\Rangefinder\Traits\RangefinderTaxonomy  Accepted-taxa whitelist.
 * @uses        trait \Tfish\Rangefinder\Traits\RangefinderConfidence  The D-18 confidence bucket.
 * @uses        trait \Tfish\Rangefinder\Traits\RangefinderCountries  ISO-3166 name resolution.
 * @uses        trait \Tfish\Rangefinder\Traits\RangefinderFilters  Filter parsing and compilation.
 */
class Explore
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Rangefinder\Traits\RangefinderDatabase;
    use \Tfish\Rangefinder\Traits\RangefinderTaxonomy;
    use \Tfish\Rangefinder\Traits\RangefinderConfidence;
    use \Tfish\Rangefinder\Traits\RangefinderCountries;
    use \Tfish\Rangefinder\Traits\RangefinderFilters;

    private $database;
    private $preference;
    private $session;
    private \Tfish\Logger $logger;

    private array $state = [];
    private ?int $recordCount = null;
    private ?array $rows = null;

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
     * Set the normalised filter state this model answers for.
     *
     * Called once by the controller with the output of parseFilters(). Everything below reads it;
     * nothing below reads the request.
     *
     * @param   array $state Normalised state from \Tfish\Rangefinder\Traits\RangefinderFilters.
     * @return  void
     */
    public function setState(array $state): void
    {
        $this->state = $state;
        $this->recordCount = null;
        $this->rows = null;
    }

    /**
     * The active filter state.
     *
     * @return  array
     */
    public function state(): array
    {
        return $this->state;
    }

    /**
     * How many records match the current filters, across all pages.
     *
     * Counted by the database over the same WHERE clause the page uses, so the figure and the rows
     * cannot disagree. Memoised: the ViewModel reads it more than once (the count line, the
     * pagination control, the offset clamp) and it is one query.
     *
     * @return  int
     */
    public function recordCount(): int
    {
        if ($this->recordCount !== null) return $this->recordCount;

        $where = $this->whereSql($this->state, $this->surfaceKey($this->state), 'o');
        $sql = 'SELECT COUNT(*) FROM ' . $this->surfaceView($this->state) . ' o'
            . ($where['sql'] === '' ? '' : ' WHERE ' . $where['sql']);

        $this->recordCount = (int) $this->selectValue($sql, $where['params']);

        return $this->recordCount;
    }

    /**
     * One page of records, in display shape.
     *
     * The two surfaces have different columns — the quarantine set has no coordinates, no locality
     * cluster and no site_key, because its rows are exactly the records that could not be placed —
     * so each is selected with its own column list and then normalised to one row shape. That is
     * what lets one template render both without asking which table it is looking at, and what lets
     * the CSV export have one column set.
     *
     * LIMIT and OFFSET are cast integers, not bound parameters: they are derived from a whitelisted
     * page size and a clamped offset, and SQLite will not accept a placeholder in every position
     * these appear in across driver versions. Every *value* remains bound.
     *
     * @return  array List of display rows.
     */
    public function rows(): array
    {
        if ($this->rows !== null) return $this->rows;

        $surface = $this->surfaceKey($this->state);
        $query = $this->buildQuery($surface);
        $sql = $query['sql'] . ' LIMIT ' . (int) $this->state['per']
            . ' OFFSET ' . (int) $this->state['start'];

        $this->rows = [];

        foreach ($this->select($sql, $query['params']) as $row) {
            $this->rows[] = $this->displayRow($row, $surface);
        }

        return $this->rows;
    }

    /**
     * Every matching record, streamed, for the CSV export.
     *
     * The one query in this class with no LIMIT — which is the point of an export — so it streams
     * rather than materialising: the filtered set can be the whole dataset, and buffering 2,000-odd
     * 40-column rows to write them out one at a time would be pure overhead.
     *
     * Deliberately the SAME query builder as the page: identical columns, identical filters, identical
     * order. A file that did not match the table it was downloaded from would be worse than no export
     * at all, because nothing in the file would reveal the discrepancy.
     *
     * @return  \Generator Display rows, in the table's order.
     */
    public function exportRows(): \Generator
    {
        $surface = $this->surfaceKey($this->state);
        $query = $this->buildQuery($surface);

        foreach ($this->selectStream($query['sql'], $query['params']) as $row) {
            yield $this->displayRow($row, $surface);
        }
    }

    /**
     * The distinct licences present in the filtered set.
     *
     * The export states the terms that govern the FILE, and that cannot be a fixed string: licence is
     * per row (an iNaturalist dataset carries two, because observers licence their own observations),
     * so the terms of a given extract depend on which rows it contains. The combined figure is derived
     * from this list — see Controller\Explore::combinedTerms().
     *
     * @return  array<string> Licence identifiers, ordered.
     */
    public function licensesInSet(): array
    {
        $where = $this->whereSql($this->state, $this->surfaceKey($this->state), 'o');
        $sql = 'SELECT DISTINCT license FROM ' . $this->surfaceView($this->state) . ' o'
            . ($where['sql'] === '' ? '' : ' WHERE ' . $where['sql'])
            . ' ORDER BY license';

        $licenses = [];

        foreach ($this->select($sql, $where['params']) as $row) {
            if (!empty($row['license'])) $licenses[] = (string) $row['license'];
        }

        return $licenses;
    }

    /**
     * Assemble the row query for a surface: columns, joins, filters and order.
     *
     * Shared by the page and the export so the two cannot diverge. Returns the statement without
     * LIMIT/OFFSET, which is the only thing that differs between them.
     *
     * @param   string $surface 'occurrence' or 'unmapped'.
     * @return  array ['sql' => string, 'params' => array].
     */
    private function buildQuery(string $surface): array
    {
        $view = $this->surfaceMapView($surface);
        $where = $this->whereSql($this->state, $surface, 'o');
        $order = $this->orderBySql($this->state, $surface, 'o');
        $bucket = $this->bucketExpressionSql('o', 'rb');
        $taxon = $this->effectiveTaxonSql('o', 'rt');

        // Source attribution is per dataset (D-20): the citable unit is the dataset, not the holder
        // institution, and a publication is modelled as a dataset with a 'lit:' key so literature and
        // institutional sources share one rendering path.
        $columns = [
            'o.occurrence_id',
            'o.verbatim_scientific_name',
            'o.canonical_taxon',
            'o.taxon_rank',
            'o.ploidy',
            'o.layer',
            'o.source',
            'o.basis_of_record',
            'o.determination_confidence',
            'o.identification_qualifier',
            'o.country_code',
            'o.state_province',
            'o.locality_text',
            'o.event_date',
            'o.year',
            'o.institution_code',
            'o.collection_code',
            'o.catalog_number',
            'o.preparations',
            'o.disposition',
            'o.holding_type',
            'o.holder',
            'o.holder_acronym',
            'o.license',
            'o.references_url',
            'o.associated_sequences',
            'o.recorded_by',
            'o.occurrence_remarks',
            'o.dataset_key',
            $bucket['sql'] . ' AS confidence',
            $taxon['sql'] . ' AS display_taxon',
            's.dataset AS source_dataset',
            's.citation AS source_citation',
            's.doi AS source_doi',
        ];

        if ($surface === 'unmapped') {
            // The quarantine's own columns: the place text that makes a row listable at all, and the
            // georeferencing slots that a future pass will fill in when it promotes the row out of
            // here.
            $columns[] = 'o.verbatim_locality';
            $columns[] = 'o.water_body';
            $columns[] = 'o.georeference_status';
        } else {
            $columns[] = 'o.locality_id';
            $columns[] = 'o.site_key';
            $columns[] = 'o.locality_name';
            $columns[] = 'o.country';
            $columns[] = 'o.decimal_latitude';
            $columns[] = 'o.decimal_longitude';
            $columns[] = 'o.coordinate_precision_m';
            $columns[] = 'o.coordinate_status';
            $columns[] = 'o.individual_count';
            $columns[] = 'o.holder_url';
        }

        $sql = 'SELECT ' . \implode(', ', $columns)
            . ' FROM ' . $view . ' o'
            . ' LEFT JOIN v_source_acknowledgement s ON s.dataset_key = o.dataset_key'
            . ($where['sql'] === '' ? '' : ' WHERE ' . $where['sql'])
            . ' ORDER BY ' . $order['sql'];

        return [
            'sql' => $sql,
            'params' => $where['params'] + $order['params'] + $bucket['params'] + $taxon['params'],
        ];
    }

    /**
     * The view name for a surface key.
     *
     * @param   string $surface 'occurrence' or 'unmapped'.
     * @return  string
     */
    private function surfaceMapView(string $surface): string
    {
        return $surface === 'unmapped' ? 'v_unmapped_records' : 'v_occurrence_detail';
    }

    /**
     * Normalise one database row into the shape the template renders.
     *
     * Keys are always present, so a template never tests which surface it is on. Two things happen
     * here and nowhere else:
     *
     *   - the name shown is the DEMOTED name (display_taxon, from the confidence trait's SQL form):
     *     an unrecognised presence-layer name is suppressed here exactly as it is suppressed on the
     *     map, so the same record cannot make a species claim in the table that the map refuses it.
     *     The verbatim string is still printed, because it is the authority and the traceback — but
     *     it is labelled as published, never as a determination.
     *   - the accuracy figure is per record and nullable. NULL means the source declared no accuracy;
     *     it is left NULL rather than defaulted, because a default would invent a certainty.
     *
     * @param   array $row Raw database row.
     * @param   string $surface 'occurrence' or 'unmapped'.
     * @return  array Display row.
     */
    private function displayRow(array $row, string $surface): array
    {
        $canonical = $row['display_taxon'] ?? null;

        return [
            'occurrence_id' => $row['occurrence_id'],
            // As published: the authority, printed verbatim, always.
            'verbatim_scientific_name' => $row['verbatim_scientific_name'],
            // Our grouping key, suppressed where the name is not recognised. Shown beside the
            // verbatim name only where the two actually differ (the template decides).
            'canonical_taxon' => $canonical,
            'display_taxon' => $canonical === null ? null : \ucfirst((string) $canonical),
            'taxon_rank' => $row['taxon_rank'],
            'identification_qualifier' => $row['identification_qualifier'],
            'confidence' => $row['confidence'],
            'confidence_label' => $this->bucketLabel((string) $row['confidence']),
            'determination_confidence' => $row['determination_confidence'],
            'layer' => $row['layer'],
            'ploidy' => $row['ploidy'],
            'source' => $row['source'],
            'basis_of_record' => $row['basis_of_record'],
            // Place. locality_name is the cluster's name (mapped surface only); locality_text is the
            // publisher's own string, kept separately because they are different claims.
            'site_key' => $row['site_key'] ?? null,
            'locality_name' => $row['locality_name'] ?? null,
            'locality_text' => $row['locality_text'],
            'verbatim_locality' => $row['verbatim_locality'] ?? null,
            'water_body' => $row['water_body'] ?? null,
            'state_province' => $row['state_province'],
            'country_code' => $row['country_code'],
            'country' => $this->countryName((string) ($row['country_code'] ?? ''), $row['country'] ?? null),
            'latitude' => isset($row['decimal_latitude']) ? (float) $row['decimal_latitude'] : null,
            'longitude' => isset($row['decimal_longitude']) ? (float) $row['decimal_longitude'] : null,
            'accuracy_m' => isset($row['coordinate_precision_m']) ? (int) $row['coordinate_precision_m'] : null,
            'coordinate_status' => $row['coordinate_status'] ?? null,
            'georeference_status' => $row['georeference_status'] ?? null,
            'is_mapped' => isset($row['decimal_latitude']),
            'event_date' => $row['event_date'],
            'year' => isset($row['year']) ? (int) $row['year'] : null,
            'institution_code' => $row['institution_code'],
            'collection_code' => $row['collection_code'],
            'catalog_number' => $row['catalog_number'],
            'preparations' => $row['preparations'],
            'disposition' => $row['disposition'],
            'holding_type' => $row['holding_type'],
            'holder' => $row['holder'],
            'holder_acronym' => $row['holder_acronym'],
            'holder_url' => $row['holder_url'] ?? null,
            'individual_count' => $row['individual_count'] ?? null,
            'associated_sequences' => $row['associated_sequences'],
            'recorded_by' => $row['recorded_by'],
            'occurrence_remarks' => $row['occurrence_remarks'],
            'references_url' => $row['references_url'],
            // Provenance and terms, per row. Licence is per record, not per dataset: iNaturalist
            // observers licence their own observations, so one dataset carries several licences and a
            // dataset-level figure would be wrong for some of its rows.
            'dataset_key' => $row['dataset_key'],
            'source_dataset' => $row['source_dataset'],
            'source_citation' => $row['source_citation'],
            'source_doi' => $row['source_doi'],
            'license' => $row['license'],
        ];
    }

    /**
     * Species / lineage facet, keyed as the map's is.
     *
     * Derived in PHP over the canonical names rather than queried as a GROUP BY, for the same reason
     * the map derives it: the facet must offer every VERIFIED taxon plus every ACCEPTED reported one,
     * and "accepted" is decided by the whitelist in RangefinderTaxonomy, which is PHP. A demoted name
     * is absent here exactly as it is suppressed everywhere else, so the filter cannot offer a
     * species claim the interface refuses to make.
     *
     * Counts are of the whole dataset, not of the current filter state: a facet that recounted itself
     * under its own selection would show 0 beside every option the visitor had not chosen.
     *
     * @return  array List of ['key', 'canonical_taxon', 'ploidy', 'display_name', 'n'] rows.
     */
    public function speciesFacet(): array
    {
        $rows = $this->select(
            "SELECT canonical_taxon, ploidy, layer, taxon_rank, COUNT(*) AS n
             FROM   v_occurrence_detail
             WHERE  canonical_taxon IS NOT NULL
             GROUP  BY canonical_taxon, ploidy, layer, taxon_rank"
        );

        $facet = [];

        foreach ($rows as $row) {
            $effective = $this->effectiveTaxon($row['layer'], $row['canonical_taxon'], $row['taxon_rank']);

            if ($effective['canonical_taxon'] === null) continue;
            if ($this->categoryOf($row['layer'], $effective['taxon_rank']) === 'unidentified') continue;

            $canonical = (string) $effective['canonical_taxon'];
            $ploidy = (string) ($row['ploidy'] ?? '');
            $key = $canonical . '~' . $ploidy;

            if (!isset($facet[$key])) {
                $facet[$key] = [
                    'key' => $key,
                    'canonical_taxon' => $canonical,
                    'ploidy' => $ploidy,
                    'display_name' => $this->abbreviate($canonical),
                    'n' => 0,
                ];
            }

            $facet[$key]['n'] += (int) $row['n'];
        }

        $facet = \array_values($facet);
        \usort($facet, static function (array $a, array $b): int {
            $order = ['' => 0, 'diploid' => 1, 'triploid' => 2, 'tetraploid' => 3, 'pentaploid' => 4,
                'polyploid' => 5];

            return [$a['canonical_taxon'], $order[$a['ploidy']] ?? 6]
                <=> [$b['canonical_taxon'], $order[$b['ploidy']] ?? 6];
        });

        return $facet;
    }

    /**
     * Abbreviated display form of a canonical taxon ('artemia salina' -> 'A. salina').
     *
     * Taxon-agnostic string work: no genus name is hard-coded, so the engine redeploys to another
     * taxon unchanged. Mirrors Model\Map::abbreviateTaxon().
     *
     * @param   string $canonical Lower-cased, citation-free canonical name.
     * @return  string
     */
    private function abbreviate(string $canonical): string
    {
        $parts = \explode(' ', $canonical, 2);

        if (isset($parts[1]) && $parts[0] !== '') {
            return \strtoupper($parts[0][0]) . '. ' . $parts[1];
        }

        return \ucfirst($canonical);
    }

    /**
     * Country options for the filter, name-resolved and name-ordered.
     *
     * Not v_country_facet: that view drops countries whose records cannot be plotted (correctly, for
     * a map filter — offering a country that shows an empty map is a bug). The table CAN show those
     * records, so it must offer those countries; excluding them here would hide 4 countries' records
     * behind a control that never lists them.
     *
     * @return  array List of ['code', 'name', 'n'] rows.
     */
    public function countryOptions(): array
    {
        // The quarantine view carries no interpreted country NAME — only the code, because a
        // coordinate-less GBIF row has nothing else. Selected as NULL there and resolved from the
        // ISO-3166 table below, which is the same fallback the mapped surface uses for the 17 of 65
        // countries whose name no source recorded.
        $name = $this->surfaceKey($this->state) === 'unmapped' ? 'NULL' : 'MAX(country)';

        $rows = $this->select(
            'SELECT country_code, ' . $name . ' AS country_name, COUNT(*) AS n
             FROM   ' . $this->surfaceView($this->state) . '
             WHERE  country_code IS NOT NULL
             GROUP  BY country_code'
        );

        $options = [];

        foreach ($rows as $row) {
            $code = (string) $row['country_code'];
            $name = $this->countryName($code, $row['country_name']);
            $options[] = [
                'code' => $code,
                'name' => $name,
                'sort' => $this->countrySortKey($name),
                'n' => (int) $row['n'],
            ];
        }

        \usort($options, static function (array $a, array $b): int {
            return $a['sort'] <=> $b['sort'];
        });

        return $options;
    }

    /**
     * Source-dataset options for the filter, largest contributor first.
     *
     * Ordered by record count rather than alphabetically: the distribution is long-tailed (the cyst
     * bank alone is 937 of 2,192, and half the datasets contribute fewer than 10 records), so
     * frequency order is what makes the list usable.
     *
     * @return  array List of ['key', 'label', 'n'] rows.
     */
    public function datasetOptions(): array
    {
        $rows = $this->select(
            'SELECT o.dataset_key, COUNT(*) AS n, MAX(s.dataset) AS label
             FROM   ' . $this->surfaceView($this->state) . ' o
             LEFT   JOIN v_source_acknowledgement s ON s.dataset_key = o.dataset_key
             WHERE  o.dataset_key IS NOT NULL
             GROUP  BY o.dataset_key
             ORDER  BY n DESC, label'
        );

        $options = [];

        foreach ($rows as $row) {
            $options[] = [
                'key' => (string) $row['dataset_key'],
                // A dataset with no acknowledgement row would otherwise render as a blank option;
                // fall back to the key so the option is at least selectable and identifiable.
                'label' => $row['label'] ?? (string) $row['dataset_key'],
                'n' => (int) $row['n'],
            ];
        }

        return $options;
    }

    /**
     * Evidence-type (basisOfRecord) options, largest first.
     *
     * @return  array List of ['value', 'n'] rows.
     */
    public function basisOptions(): array
    {
        $rows = $this->select(
            'SELECT basis_of_record AS value, COUNT(*) AS n
             FROM   ' . $this->surfaceView($this->state) . '
             WHERE  basis_of_record IS NOT NULL
             GROUP  BY basis_of_record
             ORDER  BY n DESC, value'
        );

        $options = [];

        foreach ($rows as $row) {
            $options[] = ['value' => (string) $row['value'], 'n' => (int) $row['n']];
        }

        return $options;
    }

    /**
     * Ploidy options with their record counts, in biological order.
     *
     * Only values the data actually carries are offered, and the count is the point: a visitor
     * choosing 'tetraploid' can see it selects 120 records out of 2,192, which is the honest framing
     * of a column that is NULL for most rows.
     *
     * @return  array List of ['value', 'n'] rows.
     */
    public function ploidyOptions(): array
    {
        $counts = [];

        foreach ($this->select(
            'SELECT ploidy, COUNT(*) AS n FROM ' . $this->surfaceView($this->state)
            . ' WHERE ploidy IS NOT NULL GROUP BY ploidy'
        ) as $row) {
            $counts[(string) $row['ploidy']] = (int) $row['n'];
        }

        $options = [];

        foreach ($this->ploidyValues() as $value) {
            if (!isset($counts[$value])) continue;

            $options[] = ['value' => $value, 'n' => $counts[$value]];
        }

        return $options;
    }

    /**
     * Dataset-wide tallies for the interface's honest-framing notes.
     *
     * Each figure exists because a control would otherwise mislead: `undated` is the number of
     * records a year range silently excludes, `unmapped` the number the map cannot show, and
     * `quarantined` the size of the place-text set the D-6 switch reveals. They are properties of the
     * dataset, not of the current filter, so they are counted once and unfiltered.
     *
     * @return  array ['total', 'undated', 'mapped', 'unmapped', 'quarantined'].
     */
    public function datasetTotals(): array
    {
        return [
            'total' => (int) $this->selectValue('SELECT COUNT(*) FROM v_occurrence_detail'),
            'undated' => (int) $this->selectValue(
                'SELECT COUNT(*) FROM v_occurrence_detail WHERE year IS NULL'
            ),
            'mapped' => (int) $this->selectValue(
                'SELECT COUNT(*) FROM v_occurrence_detail WHERE decimal_latitude IS NOT NULL'
            ),
            'unmapped' => (int) $this->selectValue(
                'SELECT COUNT(*) FROM v_occurrence_detail WHERE decimal_latitude IS NULL'
            ),
            'quarantined' => (int) $this->selectValue('SELECT COUNT(*) FROM v_unmapped_records'),
        ];
    }

    /**
     * Per-bucket counts under the current filters, with the bucket toggles themselves ignored.
     *
     * The tally beside each confidence checkbox: "how many records would this bucket contribute to
     * what I am otherwise asking for". Computed with the bucket selection removed, or every unchecked
     * box would read 0 and the control would be unusable.
     *
     * @return  array Bucket name => count.
     */
    public function bucketCounts(): array
    {
        $state = $this->state;
        $state['buckets'] = $this->confidenceBuckets();

        $where = $this->whereSql($state, $this->surfaceKey($state), 'o');
        $bucket = $this->bucketExpressionSql('o', 'gb');

        $sql = 'SELECT ' . $bucket['sql'] . ' AS bucket, COUNT(*) AS n'
            . ' FROM ' . $this->surfaceView($state) . ' o'
            . ($where['sql'] === '' ? '' : ' WHERE ' . $where['sql'])
            . ' GROUP BY bucket';

        $counts = \array_fill_keys($this->confidenceBuckets(), 0);

        foreach ($this->select($sql, $where['params'] + $bucket['params']) as $row) {
            $counts[(string) $row['bucket']] = (int) $row['n'];
        }

        return $counts;
    }
}
