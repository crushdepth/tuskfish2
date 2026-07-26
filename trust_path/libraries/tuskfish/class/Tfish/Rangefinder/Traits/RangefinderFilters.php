<?php

declare(strict_types=1);

namespace Tfish\Rangefinder\Traits;

/**
 * \Tfish\Rangefinder\Traits\RangefinderFilters trait file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */

/**
 * Parse, validate and compile the occurrence filter vocabulary. The security boundary.
 *
 * This is the only place in the module where visitor input reaches a database query, so the whole
 * discipline is here rather than spread across the callers:
 *
 *   - parseFilters() turns a request array into a fully normalised state array. Every KEY is
 *     whitelisted — an unknown one is dropped and cannot reach a query, a link or a cache key — and
 *     every absent value gets an explicit default. Values are handled by class: a mode filter falls
 *     back to its default, a value filter is length-capped and carried through to be bound (see
 *     requestOpaque(), which sets out why discarding a bad value would be the less safe choice).
 *     Downstream code sees only the normalised array, never $_REQUEST/$_GET.
 *   - whereSql() compiles the state into a WHERE fragment in which every *value* is a bound
 *     placeholder. The only text this class generates is placeholder names and column names taken
 *     from its own constants — no input is ever concatenated into SQL, including input that has
 *     already been validated (validation is defence in depth here, not the mechanism).
 *
 * The state array is also what every other representation of the filterset is derived from: the
 * query string for a link, the reduced query string for the map handoff, the human sentence for a
 * page title or a CSV preamble, and the cache key for the ETag. Deriving them all from one
 * normalised structure is what keeps the URL a visitor shares, the rows they were shown and the file
 * they download describing the same set.
 *
 * Two surfaces are served (see $surface): the mapped/curated set in v_occurrence_detail, and the
 * D-6 place-text quarantine in v_unmapped_records. They are switched between, never unioned, so a
 * pagination total always describes one population; the column maps below record where the two views
 * differ and which filters simply do not apply to the quarantine.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 * @uses        trait \Tfish\Traits\ValidateString  Host class MUST also use this (trimString()).
 * @uses        trait \Tfish\Rangefinder\Traits\RangefinderConfidence  Host class MUST also use this:
 *              the confidence bucket, in its SQL form.
 */
trait RangefinderFilters
{
    /**
     * Physical-material filter: request value => the holding_type values it admits.
     *
     * Mirrors HOLDING in vendor/rangefinder/rangefinder.js exactly. The two must agree or the same
     * URL describes different sets on the map and in the table — the failure the shared vocabulary
     * exists to prevent. 'obtainable' is the union of the three classes that represent surviving
     * material; 'exhausted' is deliberately its own choice, because "we held this and it is gone" is
     * a scientifically meaningful answer, not an absence.
     */
    private const HOLDING = [
        'obtainable' => ['live_cysts', 'preserved_specimen', 'tissue_or_dna'],
        'live_cysts' => ['live_cysts'],
        'preserved_specimen' => ['preserved_specimen'],
        'tissue_or_dna' => ['tissue_or_dna'],
        'exhausted' => ['exhausted'],
    ];

    /**
     * Ploidy values the schema's CHECK constraint permits, in biological order.
     *
     * A ploidy selection is a selection over the records that STATE one. The column is NULL for most
     * records, and NULL means "not recorded" — never "diploid". The template says so beside the
     * control; assuming otherwise here would invent data.
     */
    private const PLOIDY = ['diploid', 'triploid', 'tetraploid', 'pentaploid', 'polyploid'];

    /**
     * Sort keys => [column expression, secondary column]. Whitelisted: `sort` never reaches SQL.
     *
     * Keyed by a short public name so the URL does not publish column names, and so a schema rename
     * cannot break a bookmarked link. 'confidence' is not a column at all — it resolves to the
     * derived bucket expression (see orderBySql()).
     */
    private const SORTS = [
        'name' => 'canonical_taxon',
        'date' => 'year',
        'locality' => 'locality',
        'country' => 'country_code',
        'dataset' => 'dataset_key',
        'confidence' => 'confidence',
    ];

    /** Page sizes offered. Anything else falls back to the first entry. */
    private const PER_PAGE = [25, 50, 100];

    /** Free-text search is capped: a LIKE over a 100-char needle is bounded work. */
    private const SEARCH_MAX = 100;

    /** Cap on a single value filter. Longer than any real code, short enough to bound the work. */
    private const VALUE_MAX = 80;

    /** Year bounds. The dataset runs 1863-2026; the window is wide enough to be no constraint. */
    private const YEAR_MIN = 1500;
    private const YEAR_MAX = 2200;

    /**
     * Per-surface column names, for the columns whose names differ or that only one surface has.
     *
     * v_unmapped_records has no coordinates, no locality cluster and no site_key — its rows are
     * exactly the records that could not be placed — so the filters that address a mapped position
     * (geo, gaps, locality) have nothing to act on there and are dropped rather than silently
     * ignored. Its place text lives in different columns, which is why free-text search consults a
     * per-surface list.
     *
     * @param   string $surface 'occurrence' or 'unmapped'.
     * @return  array ['view', 'searchColumns', 'localityColumn', 'supports' => [...]].
     */
    private function surfaceMap(string $surface): array
    {
        if ($surface === 'unmapped') {
            return [
                'view' => 'v_unmapped_records',
                // Ordered widest-to-narrowest: the interpreted place name first, then the
                // publisher's verbatim string, then the administrative unit.
                'searchColumns' => ['locality_text', 'verbatim_locality', 'state_province', 'water_body'],
                'localityColumn' => 'locality_text',
                'supports' => ['geo' => false, 'gaps' => false, 'locality' => false],
            ];
        }

        return [
            'view' => 'v_occurrence_detail',
            'searchColumns' => ['locality_name', 'locality_text', 'state_province'],
            'localityColumn' => 'locality_name',
            'supports' => ['geo' => true, 'gaps' => true, 'locality' => true],
        ];
    }

    /**
     * Normalise a request array into the canonical filter state.
     *
     * Whitelist-only: a key this method does not know is not in the result, so it cannot reach a
     * query, a link or a cache key. Every value is validated here and bound later — both, not
     * either.
     *
     * The parameter names are the map's (rangefinder.js readUrl/writeUrl) wherever the meaning is
     * the same, so a filter state crosses between the two surfaces by carrying the query string
     * across unchanged.
     *
     * @param   array $request Typically $_GET. Values may be any type; nothing is trusted.
     * @return  array Normalised state, every key present.
     */
    public function parseFilters(array $request): array
    {
        $state = [
            // Confidence buckets are opt-OUT (`verified=0`), matching the map's checkboxes: the
            // default view shows everything, and a URL only carries what was turned off.
            'buckets' => [],
            'species' => [],
            'country' => '',
            'holding' => 'any',
            'gaps' => false,
            'locality' => '',
            'q' => '',
            'ploidy' => '',
            'dataset' => '',
            'basis' => '',
            'from' => null,
            'to' => null,
            'seq' => false,
            'geo' => 'any',
            'unmapped' => false,
            'sort' => 'name',
            'dir' => 'asc',
            'start' => 0,
            'per' => self::PER_PAGE[0],
        ];

        foreach ($this->confidenceBuckets() as $bucket) {
            if ($this->requestString($request, $bucket) !== '0') {
                $state['buckets'][] = $bucket;
            }
        }

        // A species/lineage key is 'canonical~ploidy', as the facet builds it and as taxonKey() in
        // rangefinder.js builds it. The halves are split and carried through as opaque values (see
        // requestOpaque() for why an unrecognised value is kept rather than discarded): a key that
        // is not in the facet is bound and matches no row, which is what the map does with the same
        // URL. Only an empty key is dropped — the map drops those too.
        // Two wire forms, one meaning. A link built by filterQuery() carries the comma-joined form
        // ('species=a~,b~diploid'), because that is what the map's URL vocabulary uses and the two
        // surfaces must read each other's links. A plain HTML checkbox group can only submit
        // 'species[]=a~&species[]=b~diploid'. Both are accepted here so the form needs no JavaScript
        // to rewrite itself into the link form.
        $species = $this->requestString($request, 'species');

        if (isset($request['species']) && \is_array($request['species'])) {
            $items = [];

            foreach ($request['species'] as $item) {
                if (\is_string($item)) $items[] = $this->trimString($item);
            }

            $species = \implode(',', $items);
        }

        if ($species !== '') {
            $seen = [];

            foreach (\explode(',', $species) as $key) {
                $key = \mb_substr(\trim($key), 0, self::VALUE_MAX);

                if ($key === '' || isset($seen[$key])) continue;

                $seen[$key] = true;
                $halves = \explode('~', $key, 2);
                $state['species'][] = [
                    'canonical_taxon' => $halves[0],
                    'ploidy' => $halves[1] ?? '',
                ];
            }
        }

        // ISO-3166-1 alpha-2, upper-cased. A value that is not a country code is still carried and
        // still bound: it selects nothing, which is the truthful answer and the same answer the map
        // gives.
        $state['country'] = \strtoupper($this->requestOpaque($request, 'country'));

        $holding = $this->requestString($request, 'holding');

        if (isset(self::HOLDING[$holding])) {
            $state['holding'] = $holding;
        }

        $state['gaps'] = $this->requestString($request, 'gaps') === '1';
        $state['seq'] = $this->requestString($request, 'seq') === '1';
        $state['unmapped'] = $this->requestString($request, 'unmapped') === '1';

        // site_key (D-22), the only public site identifier — 'geo:<lat>,<lng>' or 'site:<cc>:<slug>'.
        // locality_id is deliberately not accepted on any URL: it renumbers on rebuild, so a link
        // carrying it would silently point at a different place after an import.
        $state['locality'] = $this->requestOpaque($request, 'locality');

        $state['q'] = \mb_substr($this->requestString($request, 'q'), 0, self::SEARCH_MAX);

        // Ploidy, dataset_key and basis_of_record are all value filters over source-supplied
        // vocabularies, so they take the same treatment as country: carried, bound, and selecting
        // nothing if the value is not in the data.
        $state['ploidy'] = $this->requestOpaque($request, 'ploidy');
        $state['dataset'] = $this->requestOpaque($request, 'dataset');
        $state['basis'] = $this->requestOpaque($request, 'basis');

        $state['from'] = $this->requestYear($request, 'from');
        $state['to'] = $this->requestYear($request, 'to');

        // A reversed range is a typo, not a request for nothing: swap it rather than returning an
        // empty page the visitor cannot explain.
        if ($state['from'] !== null && $state['to'] !== null && $state['from'] > $state['to']) {
            [$state['from'], $state['to']] = [$state['to'], $state['from']];
        }

        $geo = $this->requestString($request, 'geo');

        if (\in_array($geo, ['any', 'mapped', 'unmapped'], true)) {
            $state['geo'] = $geo;
        }

        $sort = $this->requestString($request, 'sort');

        if (isset(self::SORTS[$sort])) {
            $state['sort'] = $sort;
        }

        if ($this->requestString($request, 'dir') === 'desc') {
            $state['dir'] = 'desc';
        }

        $per = (int) $this->requestString($request, 'per');

        if (\in_array($per, self::PER_PAGE, true)) {
            $state['per'] = $per;
        }

        // Negative or non-numeric offsets fold to the first page. An offset past the end is clamped
        // in the ViewModel, which is the only place that knows the total.
        $start = (int) $this->requestString($request, 'start');
        $state['start'] = $start > 0 ? $start : 0;

        // Filters that address a mapped position cannot apply to the quarantine surface. Cleared
        // here, once, so no caller has to remember the exception.
        if ($state['unmapped']) {
            $state['geo'] = 'any';
            $state['gaps'] = false;
            $state['locality'] = '';
        }

        return $state;
    }

    /**
     * Read one request value as a trimmed, UTF-8-validated string.
     *
     * Arrays and objects yield '' rather than a type error: a query string can supply `q[]=x`, and
     * that must be a no-op, not a 500.
     *
     * @param   array $request The request array.
     * @param   string $key Key to read.
     * @return  string
     */
    private function requestString(array $request, string $key): string
    {
        if (!isset($request[$key]) || \is_array($request[$key]) || \is_object($request[$key])) {
            return '';
        }

        return $this->trimString((string) $request[$key]);
    }

    /**
     * Read one request value as an opaque, length-capped value filter.
     *
     * The filters split into two classes, and the distinction is not cosmetic — it decides what a
     * hand-edited URL does:
     *
     *   - **Mode filters** (holding, geo, sort, dir, per, the bucket toggles) change the SHAPE of the
     *     query. An unrecognised value there falls back to the default, because there is no query to
     *     build otherwise. The map does the same with the same parameters.
     *   - **Value filters** (country, ploidy, dataset, basis, locality, species) are compared against
     *     source data. An unrecognised value there is CARRIED, not discarded: it is bound, it matches
     *     no row, and the visitor is shown an empty result — the truthful answer to a question about
     *     something not in the dataset.
     *
     * Discarding a bad value filter instead would drop the whole condition and show the visitor
     * MORE records than they asked for, while the map — which binds the same junk into a
     * client-side comparison and matches nothing — showed them none. One URL, two answers, and the
     * wider one silently wrong. Hence: cap the length (so the work stays bounded), validate nothing
     * else, bind everything.
     *
     * @param   array $request The request array.
     * @param   string $key Key to read.
     * @return  string Trimmed, length-capped value. Empty string if absent.
     */
    private function requestOpaque(array $request, string $key): string
    {
        return \mb_substr($this->requestString($request, $key), 0, self::VALUE_MAX);
    }

    /**
     * Read one request value as a year within the accepted window, or null.
     *
     * @param   array $request The request array.
     * @param   string $key Key to read.
     * @return  int|null
     */
    private function requestYear(array $request, string $key): ?int
    {
        $raw = $this->requestString($request, $key);

        if ($raw === '' || \preg_match('/^\d{1,4}$/', $raw) !== 1) return null;

        // Clamped into the window rather than dropped, for the reason set out in requestOpaque():
        // dropping a bound out relaxes the range and shows more than was asked for. A year of 9999
        // clamps to the top of the window and selects nothing, which is what asking for records
        // from the year 9999 deserves.
        return \max(self::YEAR_MIN, \min(self::YEAR_MAX, (int) $raw));
    }

    /**
     * Compile the normalised state into a bound WHERE fragment.
     *
     * Returns the fragment without the 'WHERE' keyword and with no leading/trailing space, plus the
     * parameters to bind. An unfiltered state returns ('', []) — the caller composes.
     *
     * Every filter is ANDed: the controls narrow, they never widen. Two are more than a column
     * comparison and are worth reading closely:
     *
     *   - the species selection reproduces matches() in rangefinder.js, which excludes genus-only
     *     leads whenever a species is selected (a record naming no species cannot satisfy a
     *     species question) and matches on canonical name + ploidy so a verified and a reported
     *     record of the same taxon share a key. The name compared is the DEMOTED name, so an
     *     unrecognised presence-layer name matches nothing here just as it is suppressed on the map.
     *   - `gaps` (FR-7) is a property of a SITE, not of a record: localities carrying presence
     *     reports and no determination at all. It is judged on the locality's FULL record set — the
     *     same rule as render() in rangefinder.js — because judging it on the filtered set would
     *     make every locality look like a gap the moment the verified layer was hidden.
     *
     * @param   array $state Normalised state from parseFilters().
     * @param   string $surface 'occurrence' or 'unmapped'.
     * @param   string $alias Alias the view is queried under.
     * @return  array ['sql' => string, 'params' => array].
     */
    public function whereSql(array $state, string $surface = 'occurrence', string $alias = 'o'): array
    {
        $map = $this->surfaceMap($surface);
        $q = $alias === '' ? '' : $alias . '.';
        $terms = [];
        $params = [];

        $bucket = $this->bucketSql($state['buckets'], $alias, 'fb');

        if ($bucket['sql'] !== '') {
            $terms[] = $bucket['sql'];
            $params += $bucket['params'];
        }

        if (!empty($state['species'])) {
            // "Names a species at all" — reuse of the bucket rule rather than a second spelling of
            // it, so the species filter cannot disagree with the confidence filter about what a
            // record claims.
            $named = $this->bucketSql(['verified', 'reported'], $alias, 'fs');
            $taxon = $this->effectiveTaxonSql($alias, 'fe');
            $keys = [];

            foreach ($state['species'] as $i => $selection) {
                $nameKey = ':fsn' . $i;
                $ploidyKey = ':fsp' . $i;
                $keys[] = '(' . $nameKey . ' || \'~\' || ' . $ploidyKey . ')';
                $params[$nameKey] = $selection['canonical_taxon'];
                $params[$ploidyKey] = $selection['ploidy'];
            }

            $terms[] = $named['sql'];
            // The demoted name concatenated with ploidy, compared against the selected keys. A
            // demoted (NULL) name makes the whole expression NULL, which is not equal to anything —
            // so a suppressed name is excluded without a separate test.
            $terms[] = '((' . $taxon['sql'] . ") || '~' || COALESCE(" . $q . "ploidy, '')) IN ("
                . \implode(', ', $keys) . ')';
            $params += $named['params'] + $taxon['params'];
        }

        if ($state['country'] !== '') {
            $terms[] = $q . 'country_code = :fcountry';
            $params[':fcountry'] = $state['country'];
        }

        if ($state['holding'] !== 'any') {
            $admitted = [];

            foreach (self::HOLDING[$state['holding']] as $i => $value) {
                $admitted[] = ':fh' . $i;
                $params[':fh' . $i] = $value;
            }

            $terms[] = $q . 'holding_type IN (' . \implode(', ', $admitted) . ')';
        }

        if ($state['ploidy'] !== '') {
            $terms[] = $q . 'ploidy = :fploidy';
            $params[':fploidy'] = $state['ploidy'];
        }

        if ($state['dataset'] !== '') {
            $terms[] = $q . 'dataset_key = :fdataset';
            $params[':fdataset'] = $state['dataset'];
        }

        if ($state['basis'] !== '') {
            $terms[] = $q . 'basis_of_record = :fbasis';
            $params[':fbasis'] = $state['basis'];
        }

        // A year range can only be satisfied by a dated record, so it excludes the undated ones.
        // That is arithmetic, not a policy choice — but it is invisible in a result count, which is
        // why the control carries the sentence saying so.
        if ($state['from'] !== null) {
            $terms[] = $q . 'year >= :ffrom';
            $params[':ffrom'] = $state['from'];
        }

        if ($state['to'] !== null) {
            $terms[] = $q . 'year <= :fto';
            $params[':fto'] = $state['to'];
        }

        if ($state['seq']) {
            $terms[] = '(' . $q . 'associated_sequences IS NOT NULL AND TRIM(' . $q
                . "associated_sequences) <> '')";
        }

        if ($state['q'] !== '') {
            $needle = $this->escapeLike($state['q']);
            $clauses = [];

            foreach ($map['searchColumns'] as $i => $column) {
                $clauses[] = $q . $column . ' LIKE :fq' . $i . " ESCAPE '\\'";
                $params[':fq' . $i] = '%' . $needle . '%';
            }

            $terms[] = '(' . \implode(' OR ', $clauses) . ')';
        }

        if ($map['supports']['locality'] && $state['locality'] !== '') {
            $terms[] = $q . 'site_key = :flocality';
            $params[':flocality'] = $state['locality'];
        }

        if ($map['supports']['geo'] && $state['geo'] !== 'any') {
            $terms[] = $state['geo'] === 'mapped'
                ? $q . 'decimal_latitude IS NOT NULL'
                : $q . 'decimal_latitude IS NULL';
        }

        if ($map['supports']['gaps'] && $state['gaps']) {
            $terms[] = '(' . $q . 'locality_id IS NOT NULL AND ' . $q . 'locality_id NOT IN'
                . ' (SELECT g.locality_id FROM ' . $map['view'] . ' g'
                . " WHERE g.layer = 'species' AND g.locality_id IS NOT NULL))";
        }

        return ['sql' => \implode(' AND ', $terms), 'params' => $params];
    }

    /**
     * Escape the LIKE wildcards in a search term.
     *
     * Without this a visitor searching for '_' matches every single character and '%' matches
     * everything — not an injection, but a search box that lies about what it found. The backslash
     * is escaped first, or it would escape the escapes.
     *
     * @param   string $needle Raw search term.
     * @return  string Term safe to wrap in '%…%' with ESCAPE '\'.
     */
    private function escapeLike(string $needle): string
    {
        return \str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle);
    }

    /**
     * Compile the sort into a bound ORDER BY clause.
     *
     * The column comes from the SORTS whitelist and the direction resolves to the literal 'ASC' or
     * 'DESC' — the request's own strings never appear in the SQL. Two rules beyond the visitor's
     * choice:
     *
     *   - NULLs sort last in either direction. An undated or unnamed record is the least
     *     informative row on the page and should not be the first thing on it.
     *   - occurrence_id is the final tiebreak, always. Pagination over a non-unique sort key is
     *     otherwise unstable: rows can repeat on one page and vanish from another between requests.
     *
     * @param   array $state Normalised state from parseFilters().
     * @param   string $surface 'occurrence' or 'unmapped'.
     * @param   string $alias Alias the view is queried under.
     * @return  array ['sql' => string, 'params' => array] — sql excludes the 'ORDER BY' keyword.
     */
    public function orderBySql(array $state, string $surface = 'occurrence', string $alias = 'o'): array
    {
        $map = $this->surfaceMap($surface);
        $q = $alias === '' ? '' : $alias . '.';
        $direction = $state['dir'] === 'desc' ? 'DESC' : 'ASC';
        $key = self::SORTS[$state['sort']] ?? self::SORTS['name'];
        $params = [];

        if ($key === 'canonical_taxon') {
            // Ordered on the DEMOTED name, not the stored one, so the sequence matches what the page
            // shows. Sorting on the raw column would order a row by a name the interface suppresses —
            // an unrecognised presence-layer name filing under its own initial while its row displays
            // no name at all, which looks like a broken sort and silently reveals the ordering of a
            // name we decline to publish.
            $taxon = $this->effectiveTaxonSql($alias, 'on');
            $column = '(' . $taxon['sql'] . ')';
            $params = $taxon['params'];
        } elseif ($key === 'confidence') {
            // Ordered by confidence, not alphabetically by its name: 'reported' < 'unidentified' <
            // 'verified' as strings, which would put the least reliable rows in the middle and the
            // determinations last. The bucket expression comes from the confidence trait, so this
            // sorts on the same rule the filter and the badge use.
            $bucket = $this->bucketExpressionSql($alias, 'ob');
            $column = "CASE (" . $bucket['sql'] . ") WHEN 'verified' THEN 0 WHEN 'reported' THEN 1"
                . ' ELSE 2 END';
            $params = $bucket['params'];
        } elseif ($key === 'locality') {
            $column = $q . $map['localityColumn'];
        } else {
            $column = $q . $key;
        }

        $clauses = [
            '(' . $column . ' IS NULL)',
            $column . ' ' . $direction,
        ];

        // Default view: name ascending, then most recent first within a name — the reading order for
        // "what is recorded for this taxon, latest first".
        if ($state['sort'] === 'name') {
            $clauses[] = '(' . $q . 'year IS NULL)';
            $clauses[] = $q . 'year DESC';
        }

        $clauses[] = $q . 'occurrence_id ASC';

        return ['sql' => \implode(', ', $clauses), 'params' => $params];
    }

    /**
     * The view a state addresses, so callers do not re-decide which table they are on.
     *
     * @param   array $state Normalised state from parseFilters().
     * @return  string View name.
     */
    public function surfaceView(array $state): string
    {
        return $this->surfaceMap($state['unmapped'] ? 'unmapped' : 'occurrence')['view'];
    }

    /**
     * The surface key ('occurrence' | 'unmapped') a state addresses.
     *
     * @param   array $state Normalised state from parseFilters().
     * @return  string
     */
    public function surfaceKey(array $state): string
    {
        return $state['unmapped'] ? 'unmapped' : 'occurrence';
    }

    /**
     * Render the state back to a query string.
     *
     * Emits only non-default values, so a shared URL carries the filters that were actually chosen
     * and nothing else — and so the bare URL stays bare and cacheable. $overrides lets a caller
     * change one value without rebuilding the state (a sort header, a page-size control); a value
     * of null removes a key.
     *
     * @param   array $state Normalised state from parseFilters().
     * @param   array $overrides Key => value pairs to set, or null to drop.
     * @return  string Query string without the leading '?', urlencoded. Empty if all defaults.
     */
    public function filterQuery(array $state, array $overrides = []): string
    {
        $params = [];

        foreach ($this->confidenceBuckets() as $bucket) {
            if (!\in_array($bucket, $state['buckets'], true)) {
                $params[$bucket] = '0';
            }
        }

        if (!empty($state['species'])) {
            $keys = [];

            foreach ($state['species'] as $selection) {
                $keys[] = $selection['canonical_taxon'] . '~' . $selection['ploidy'];
            }

            $params['species'] = \implode(',', $keys);
        }

        if ($state['country'] !== '') $params['country'] = $state['country'];
        if ($state['holding'] !== 'any') $params['holding'] = $state['holding'];
        if ($state['gaps']) $params['gaps'] = '1';
        if ($state['locality'] !== '') $params['locality'] = $state['locality'];
        if ($state['q'] !== '') $params['q'] = $state['q'];
        if ($state['ploidy'] !== '') $params['ploidy'] = $state['ploidy'];
        if ($state['dataset'] !== '') $params['dataset'] = $state['dataset'];
        if ($state['basis'] !== '') $params['basis'] = $state['basis'];
        if ($state['from'] !== null) $params['from'] = (string) $state['from'];
        if ($state['to'] !== null) $params['to'] = (string) $state['to'];
        if ($state['seq']) $params['seq'] = '1';
        if ($state['geo'] !== 'any') $params['geo'] = $state['geo'];
        if ($state['unmapped']) $params['unmapped'] = '1';
        if ($state['sort'] !== 'name') $params['sort'] = $state['sort'];
        if ($state['dir'] !== 'asc') $params['dir'] = $state['dir'];
        if ($state['per'] !== self::PER_PAGE[0]) $params['per'] = (string) $state['per'];
        if ($state['start'] > 0) $params['start'] = (string) $state['start'];

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($params[$key]);
            } else {
                $params[$key] = (string) $value;
            }
        }

        return \http_build_query($params);
    }

    /**
     * The same state reduced to what the map can express, for the "Show on map" handoff.
     *
     * The map filters a payload of eight fields per record; the table filters 43 columns. So the
     * crossing is lossy in one direction, and the dropped filters are RETURNED rather than quietly
     * discarded: the link says which ones the map cannot honour, because a link that silently
     * widened the set would show more records than the table it came from and look like a bug in
     * the data.
     *
     * Pagination and sort are dropped without comment — they describe a table, not a map.
     *
     * @param   array $state Normalised state from parseFilters().
     * @return  array ['query' => string, 'dropped' => array<string>] — dropped holds param names.
     */
    public function mapQuery(array $state): array
    {
        $dropped = [];
        $reduced = $state;

        // Table-only filters, in the order the UI lists them.
        foreach (['q', 'ploidy', 'dataset', 'basis', 'seq'] as $key) {
            $empty = $key === 'seq' ? false : '';

            if ($state[$key] !== $empty) {
                $dropped[] = $key;
                $reduced[$key] = $empty;
            }
        }

        if ($state['from'] !== null || $state['to'] !== null) {
            $dropped[] = 'years';
            $reduced['from'] = null;
            $reduced['to'] = null;
        }

        // The quarantine surface has no map representation at all: every row in it is a record that
        // could not be placed. Crossing from there resets to the mapped set.
        if ($state['unmapped']) {
            $dropped[] = 'unmapped';
            $reduced['unmapped'] = false;
        }

        // geo=unmapped is the mapped surface's coordinate-less rows — likewise unplottable.
        if ($state['geo'] !== 'any') {
            if ($state['geo'] === 'unmapped') $dropped[] = 'geo';

            $reduced['geo'] = 'any';
        }

        $reduced['sort'] = 'name';
        $reduced['dir'] = 'asc';
        $reduced['start'] = 0;
        $reduced['per'] = self::PER_PAGE[0];

        return ['query' => $this->filterQuery($reduced), 'dropped' => $dropped];
    }

    /**
     * A stable fingerprint of the filter state, for the ETag.
     *
     * Derived from the normalised state rather than from the raw query string, so two URLs that
     * describe the same set (different parameter order, a default stated explicitly, a dropped junk
     * key) share one cache entry instead of two.
     *
     * @param   array $state Normalised state from parseFilters().
     * @return  string Short hex digest.
     */
    public function filterStateKey(array $state): string
    {
        return \substr(\hash('sha256', $this->filterQuery($state)), 0, 16);
    }

    /**
     * Whether a state is the bare default view (no filter, no sort, first page).
     *
     * The caching decision reads this: the bare page is one document every visitor shares and is
     * server-cached, while a filtered page is per-visitor and is not (else the cache fills with
     * permutations).
     *
     * @param   array $state Normalised state from parseFilters().
     * @return  bool
     */
    public function isDefaultState(array $state): bool
    {
        return $this->filterQuery($state) === '';
    }

    /**
     * The active filters as label => value pairs, for the "showing" line and the CSV preamble.
     *
     * Describes the state in the vocabulary of the interface, not of the schema, and returns pairs
     * rather than a sentence so the caller can escape and mark them up. An export that carries this
     * in its preamble is self-describing: the file says which subset it is, which is the difference
     * between a citable extract and an anonymous CSV.
     *
     * @param   array $state Normalised state from parseFilters().
     * @return  array Ordered list of ['label' => string, 'value' => string].
     */
    public function filterSummary(array $state): array
    {
        $summary = [];
        $buckets = $state['buckets'];

        if (\count($buckets) !== \count($this->confidenceBuckets())) {
            $labels = [];

            foreach ($buckets as $bucket) {
                $labels[] = $this->bucketLabel($bucket);
            }

            $summary[] = [
                'label' => TFISH_RANGEFINDER_CONFIDENCE,
                'value' => empty($labels) ? TFISH_RANGEFINDER_FILTER_NONE : \implode(', ', $labels),
            ];
        }

        if (!empty($state['species'])) {
            $names = [];

            foreach ($state['species'] as $selection) {
                $names[] = \ucfirst($selection['canonical_taxon'])
                    . ($selection['ploidy'] !== '' ? ' (' . $selection['ploidy'] . ')' : '');
            }

            $summary[] = ['label' => TFISH_RANGEFINDER_SPECIES_FILTER, 'value' => \implode(', ', $names)];
        }

        if ($state['country'] !== '') {
            $summary[] = [
                'label' => TFISH_RANGEFINDER_COUNTRY,
                'value' => $this->countryName($state['country'], null),
            ];
        }

        if ($state['holding'] !== 'any') {
            $summary[] = ['label' => TFISH_RANGEFINDER_HOLDING, 'value' => $this->holdingLabel($state['holding'])];
        }

        if ($state['ploidy'] !== '') {
            $summary[] = ['label' => TFISH_RANGEFINDER_PLOIDY, 'value' => $state['ploidy']];
        }

        if ($state['dataset'] !== '') {
            $summary[] = ['label' => TFISH_RANGEFINDER_DATASET, 'value' => $state['dataset']];
        }

        if ($state['basis'] !== '') {
            $summary[] = ['label' => TFISH_RANGEFINDER_BASIS, 'value' => $state['basis']];
        }

        if ($state['from'] !== null || $state['to'] !== null) {
            $summary[] = [
                'label' => TFISH_RANGEFINDER_YEARS,
                'value' => ($state['from'] ?? '…') . '–' . ($state['to'] ?? '…'),
            ];
        }

        if ($state['seq']) {
            $summary[] = ['label' => TFISH_RANGEFINDER_SEQUENCES, 'value' => TFISH_RANGEFINDER_FILTER_YES];
        }

        if ($state['gaps']) {
            $summary[] = ['label' => TFISH_RANGEFINDER_GAP_MAP, 'value' => TFISH_RANGEFINDER_FILTER_YES];
        }

        if ($state['locality'] !== '') {
            $summary[] = ['label' => TFISH_RANGEFINDER_LOCALITY, 'value' => $state['locality']];
        }

        if ($state['geo'] !== 'any') {
            $summary[] = ['label' => TFISH_RANGEFINDER_GEO, 'value' => $this->geoLabel($state['geo'])];
        }

        if ($state['q'] !== '') {
            $summary[] = ['label' => TFISH_RANGEFINDER_SEARCH, 'value' => $state['q']];
        }

        if ($state['unmapped']) {
            $summary[] = ['label' => TFISH_RANGEFINDER_RECORD_SET, 'value' => TFISH_RANGEFINDER_QUARANTINE_SET];
        }

        return $summary;
    }

    /**
     * Display label for a confidence bucket.
     *
     * The same three strings the map's summary, filter and popups use, so one bucket is called one
     * thing across the whole interface.
     *
     * @param   string $bucket 'verified', 'reported' or 'unidentified'.
     * @return  string
     */
    public function bucketLabel(string $bucket): string
    {
        $labels = [
            'verified' => TFISH_RANGEFINDER_VERIFIED,
            'reported' => TFISH_RANGEFINDER_REPORTED,
            'unidentified' => TFISH_RANGEFINDER_UNIDENTIFIED,
        ];

        return $labels[$bucket] ?? $bucket;
    }

    /**
     * Display label for a material-holding choice.
     *
     * @param   string $holding A key of self::HOLDING, or 'any'.
     * @return  string
     */
    public function holdingLabel(string $holding): string
    {
        $labels = [
            'any' => TFISH_RANGEFINDER_HOLDING_ANY,
            'obtainable' => TFISH_RANGEFINDER_HOLDING_OBTAINABLE,
            'live_cysts' => TFISH_RANGEFINDER_HOLDING_LIVE_CYSTS,
            'preserved_specimen' => TFISH_RANGEFINDER_HOLDING_PRESERVED,
            'tissue_or_dna' => TFISH_RANGEFINDER_HOLDING_TISSUE,
            'exhausted' => TFISH_RANGEFINDER_HOLDING_EXHAUSTED,
        ];

        return $labels[$holding] ?? $holding;
    }

    /**
     * Display label for the mapped/unmapped choice.
     *
     * @param   string $geo 'any', 'mapped' or 'unmapped'.
     * @return  string
     */
    public function geoLabel(string $geo): string
    {
        $labels = [
            'any' => TFISH_RANGEFINDER_GEO_ANY,
            'mapped' => TFISH_RANGEFINDER_GEO_MAPPED,
            'unmapped' => TFISH_RANGEFINDER_GEO_UNMAPPED,
        ];

        return $labels[$geo] ?? $geo;
    }

    /**
     * The page sizes offered, for the per-page control.
     *
     * @return  array<int>
     */
    public function pageSizes(): array
    {
        return self::PER_PAGE;
    }

    /**
     * The sort keys offered, for the table headers.
     *
     * @return  array<string>
     */
    public function sortKeys(): array
    {
        return \array_keys(self::SORTS);
    }

    /**
     * The material-filter choices offered, for the holding control.
     *
     * @return  array<string>
     */
    public function holdingKeys(): array
    {
        return \array_keys(self::HOLDING);
    }

    /**
     * The ploidy values offered, in biological order.
     *
     * @return  array<string>
     */
    public function ploidyValues(): array
    {
        return self::PLOIDY;
    }
}
