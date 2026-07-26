<?php

declare(strict_types=1);

namespace Tfish\Rangefinder\ViewModel;

/**
 * \Tfish\Rangefinder\ViewModel\Explore class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */

/**
 * ViewModel for the Rangefinder occurrence table (/explore/).
 *
 * Implements \Tfish\Interface\Listable, so core pagination applies to this route with no bespoke
 * paging code: \Tfish\View\Listing reads contentCount()/limit()/start()/extraParams() and renders the
 * control. extraParams() is what carries the filter state through the page links — see the note
 * there, because it is also the one place a user-supplied value is handed to a core class that
 * throws rather than sanitises.
 *
 * Everything the template renders is exposed as an accessor here, and every filter decision has
 * already been made by RangefinderFilters (the security boundary) before this class sees it. The
 * offset clamp is the exception and belongs here: only this layer knows both the requested offset and
 * the total, and paging past the end should land on the last page rather than on an empty table with
 * live pagination beneath it.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 * @uses        trait \Tfish\Traits\Listable  Standard accessors required by the Listable interface.
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 * @var         string $description Meta description for this page.
 */
class Explore implements \Tfish\Interface\Listable
{
    use \Tfish\Traits\Listable;
    use \Tfish\Traits\ValidateString;
    // The same check \Tfish\Pagination applies to extraParams(), used here to guarantee a value
    // passes it before Pagination sees it — see sanitiseLinkValue().
    use \Tfish\Traits\TraversalCheck;

    private object $model;
    private \Tfish\Entity\Preference $preference;
    private string $description = '';
    private array $state = [];

    public function __construct(
        object $model,
        \Tfish\Entity\Preference $preference
    ) {
        $this->model = $model;
        $this->preference = $preference;
        $this->theme = $preference->defaultTheme();
        // $modulePath has no setter in the Listable trait; assign the property directly, as
        // ViewModel\Map does. It lets the module ship its own templates, so installing it needs no
        // theme edit.
        $this->modulePath = TFISH_RANGEFINDER_TEMPLATE_PATH;
        $this->pageTitle = TFISH_RANGEFINDER_EXPLORE_TITLE;
        $this->description = TFISH_RANGEFINDER_EXPLORE_DESCRIPTION;
    }

    /**
     * Render the occurrence table.
     *
     * The filter state arrives already normalised from the controller. Rows are not loaded here: the
     * model's accessors load on first use, so a cache hit — where the template is never rendered —
     * queries the occurrence database not at all.
     *
     * @param   array $state Normalised filter state from RangefinderFilters::parseFilters().
     */
    public function displayExplore(array $state): void
    {
        $this->model->setState($state);

        // Fold an offset past the end back onto the last page that has rows. Done here, before
        // anything is queried for display, so the ROWS and the PAGINATION agree: clamping only the
        // pagination control would leave the query running at the original offset and render an empty
        // table with live page links beneath it — which reads as data loss rather than a bad URL.
        //
        // The `> 0` test comes first deliberately: the bare page never reaches recordCount(), so a
        // cache hit still queries the database not at all.
        if ($state['start'] > 0 && $state['start'] >= $this->model->recordCount()) {
            $state['start'] = $this->lastPageOffset($this->model->recordCount(), (int) $state['per']);
            $this->model->setState($state);
        }

        $this->state = $state;
        $this->template = 'explore';
        $this->buildMetadata();
    }

    /**
     * Offset of the last page that contains rows.
     *
     * @param   int $count Total records matching the filters.
     * @param   int $limit Page size.
     * @return  int
     */
    private function lastPageOffset(int $count, int $limit): int
    {
        if ($count < 1 || $limit < 1) return 0;

        return (int) \floor(($count - 1) / $limit) * $limit;
    }

    /**
     * Section key for the module's navigation and canonical URL.
     *
     * @return  string
     */
    public function pageKey(): string
    {
        return 'explore';
    }

    /**
     * Assemble page title, description and canonical URL metadata.
     *
     * The canonical URL is always the bare /explore/ page, even when filters are active. A filtered
     * table is a view of one dataset, not a distinct document, and every filter permutation is
     * reachable — so consolidating them keeps a combinatorial number of near-identical URLs out of
     * the index. The title, by contrast, does name the active filters, because that is what a person
     * looking at their own browser tab or a shared link needs to see.
     */
    private function buildMetadata(): void
    {
        $title = $this->pageTitle;
        $summary = $this->model->filterSummary($this->state);

        if (!empty($summary)) {
            $terms = [];

            foreach ($summary as $item) {
                $terms[] = $item['value'];
            }

            $title .= ' ' . TFISH_RANGEFINDER_TITLE_SEPARATOR . ' ' . \implode(', ', $terms);
        }

        $this->metadata = [
            'title' => $title,
            'description' => $this->description,
            'canonicalUrl' => TFISH_URL . $this->pageKey() . '/',
        ];
    }

    /* Listable: pagination. */

    /**
     * Total records matching the current filters.
     *
     * @return  int
     */
    public function contentCount(): int
    {
        return $this->model->recordCount();
    }

    /**
     * Page size.
     *
     * From the whitelisted `per` values, not from the site's content-pagination preference: this is a
     * data table, and a visitor scanning 2,000 records has a legitimate reason to ask for 100 rows.
     *
     * @return  int
     */
    public function limit(): int
    {
        return (int) $this->state['per'];
    }

    /**
     * Offset of the current page.
     *
     * Already clamped in displayExplore(), so this is the same offset the query ran at — the two
     * cannot disagree.
     *
     * @return  int
     */
    public function start(): int
    {
        return (int) $this->state['start'];
    }

    /**
     * Tag filter. Not used: this module has no taxonomy of its own.
     *
     * @return  int
     */
    public function tag(): int
    {
        return 0;
    }

    /**
     * Filter state to carry through the pagination links.
     *
     * The one place a visitor-supplied value is handed to a core class that VALIDATES rather than
     * sanitises: \Tfish\Pagination::setExtraParams() throws InvalidArgumentException on a NUL byte or
     * a '..' path segment. A visitor searching for '../' is doing nothing wrong and must not be shown
     * an error page, so those two patterns are neutralised here — in the link builder, not in the
     * filter itself, because they are perfectly legitimate things to search a place name for and the
     * query handles them safely.
     *
     * Keys are this module's own whitelist (Pagination's docblock asks for exactly that); `start` is
     * excluded because pagination sets it itself.
     *
     * @return  array Key => value pairs for the pagination links.
     */
    public function extraParams(): array
    {
        $params = [];
        $query = $this->model->filterQuery($this->state, ['start' => null]);

        if ($query === '') return $params;

        \parse_str($query, $parsed);

        foreach ($parsed as $key => $value) {
            if (!\is_string($value)) continue;

            $safe = $this->sanitiseLinkValue($value);

            // A value that still fails is dropped rather than risked. Losing one filter from the
            // pagination links is a visible, recoverable annoyance; an uncaught exception is an error
            // page in place of the visitor's results.
            if ($this->hasTraversalorNullByte($key) || $this->hasTraversalorNullByte($safe)) continue;

            $params[$key] = $safe;
        }

        return $params;
    }

    /**
     * Make a filter value safe to hand to \Tfish\Pagination::setExtraParams().
     *
     * That method THROWS on a NUL byte or a '..' path segment, and it looks for them after decoding
     * percent-escapes and HTML entities twice — so a single pass of pattern-collapsing is not enough
     * ('../../x' still contains a '..' segment after one non-overlapping replace, and '%2e%2e/' only
     * becomes one once decoded).
     *
     * So this does not try to out-clever the check: in the rare case a value fails it, the characters
     * the check can build a traversal out of are removed outright. With no NUL, no '.', no '%' and no
     * '&' left, the check's own fast path returns false — safe by construction rather than by argument.
     * Ordinary values are untouched, and the affected value is only the copy in the pagination links:
     * the search itself still runs on exactly what the visitor typed.
     *
     * @param   string $value Raw filter value.
     * @return  string Value safe to pass to \Tfish\Pagination::setExtraParams().
     */
    private function sanitiseLinkValue(string $value): string
    {
        $value = \str_replace("\0", '', $value);

        if (!$this->hasTraversalorNullByte($value)) return $value;

        return \str_replace(['.', '%', '&'], '', $value);
    }

    /* Data for the template. */

    /**
     * One page of records, in display shape.
     *
     * @return  array List of display rows (see Model\Explore::displayRow()).
     */
    public function records(): array
    {
        return $this->model->rows();
    }

    /**
     * The active filter state, for the form controls.
     *
     * @return  array
     */
    public function filters(): array
    {
        return $this->state;
    }

    /**
     * Whether a species/lineage key is currently selected.
     *
     * @param   string $key Facet key ('canonical~ploidy').
     * @return  bool
     */
    public function isSelectedSpecies(string $key): bool
    {
        foreach ($this->state['species'] as $selection) {
            if ($selection['canonical_taxon'] . '~' . $selection['ploidy'] === $key) return true;
        }

        return false;
    }

    /**
     * Whether a confidence bucket is currently shown.
     *
     * @param   string $bucket 'verified', 'reported' or 'unidentified'.
     * @return  bool
     */
    public function isSelectedBucket(string $bucket): bool
    {
        return \in_array($bucket, $this->state['buckets'], true);
    }

    /**
     * The active filters as label/value pairs, for the "filtered by" line.
     *
     * @return  array List of ['label', 'value'].
     */
    public function filterSummary(): array
    {
        return $this->model->filterSummary($this->state);
    }

    /**
     * Species / lineage facet rows for the filter list.
     *
     * @return  array List of ['key', 'canonical_taxon', 'ploidy', 'display_name', 'n'].
     */
    public function speciesFacet(): array
    {
        return $this->model->speciesFacet();
    }

    /**
     * Country options for the filter.
     *
     * @return  array List of ['code', 'name', 'n'].
     */
    public function countryOptions(): array
    {
        return $this->model->countryOptions();
    }

    /**
     * Source-dataset options for the filter.
     *
     * @return  array List of ['key', 'label', 'n'].
     */
    public function datasetOptions(): array
    {
        return $this->model->datasetOptions();
    }

    /**
     * Evidence-type options for the filter.
     *
     * @return  array List of ['value', 'n'].
     */
    public function basisOptions(): array
    {
        return $this->model->basisOptions();
    }

    /**
     * Ploidy options for the filter, with counts.
     *
     * @return  array List of ['value', 'n'].
     */
    public function ploidyOptions(): array
    {
        return $this->model->ploidyOptions();
    }

    /**
     * Material-holding options for the filter, as value => label.
     *
     * @return  array
     */
    public function holdingOptions(): array
    {
        $options = ['any' => $this->model->holdingLabel('any')];

        foreach ($this->model->holdingKeys() as $key) {
            $options[$key] = $this->model->holdingLabel($key);
        }

        return $options;
    }

    /**
     * Mapping (geo) options for the filter, as value => label.
     *
     * @return  array
     */
    public function geoOptions(): array
    {
        $options = [];

        foreach (['any', 'mapped', 'unmapped'] as $value) {
            $options[$value] = $this->model->geoLabel($value);
        }

        return $options;
    }

    /**
     * Page-size options.
     *
     * @return  array<int>
     */
    public function pageSizes(): array
    {
        return $this->model->pageSizes();
    }

    /**
     * Sort keys offered by the table headers.
     *
     * @return  array<string>
     */
    public function sortKeys(): array
    {
        return $this->model->sortKeys();
    }

    /**
     * The three confidence buckets with their counts under the current filters.
     *
     * @return  array List of ['bucket', 'label', 'n', 'selected'].
     */
    public function confidenceFacet(): array
    {
        $counts = $this->model->bucketCounts();
        $facet = [];

        foreach ($this->model->confidenceBuckets() as $bucket) {
            $facet[] = [
                'bucket' => $bucket,
                'label' => $this->model->bucketLabel($bucket),
                'n' => $counts[$bucket] ?? 0,
                'selected' => $this->isSelectedBucket($bucket),
            ];
        }

        return $facet;
    }

    /**
     * Dataset-wide tallies behind the interface's honest-framing notes.
     *
     * @return  array ['total', 'undated', 'mapped', 'unmapped', 'quarantined'].
     */
    public function datasetTotals(): array
    {
        return $this->model->datasetTotals();
    }

    /* Links. */

    /**
     * URL of this page with one parameter changed.
     *
     * Used by the sort headers and the per-page control, so a click keeps every other filter. Always
     * returns to the first page: a re-sorted page 4 is a different set of rows, and staying on the
     * offset would look like the sort had failed.
     *
     * @param   array $overrides Parameters to change; null removes one.
     * @return  string
     */
    public function url(array $overrides = []): string
    {
        $overrides['start'] = null;
        $query = $this->model->filterQuery($this->state, $overrides);

        return TFISH_URL . $this->pageKey() . '/' . ($query === '' ? '' : '?' . $query);
    }

    /**
     * URL that downloads the current filterset as CSV.
     *
     * Pagination is dropped: an export is the whole filtered set, not the page on screen. Sort is
     * kept, because the row order of a file is information too.
     *
     * @return  string
     */
    public function exportUrl(): string
    {
        $query = $this->model->filterQuery($this->state, ['start' => null, 'per' => null, 'action' => 'export']);

        return TFISH_URL . $this->pageKey() . '/?' . $query;
    }

    /**
     * URL of the equivalent view on the map, for the "Show on map" handoff.
     *
     * @return  string
     */
    public function mapUrl(): string
    {
        $handoff = $this->model->mapQuery($this->state);

        return TFISH_URL . 'map/' . ($handoff['query'] === '' ? '' : '?' . $handoff['query']);
    }

    /**
     * The filters the map cannot express, so the handoff link can say what it drops.
     *
     * A link that silently widened the set would look like a fault in the data rather than a lost
     * filter, so this is rendered beside the link rather than kept internal.
     *
     * @return  array<string> Human-readable filter names. Empty if the map can honour all of them.
     */
    public function mapDropped(): array
    {
        $labels = [
            'q' => TFISH_RANGEFINDER_SEARCH,
            'ploidy' => TFISH_RANGEFINDER_PLOIDY,
            'dataset' => TFISH_RANGEFINDER_DATASET,
            'basis' => TFISH_RANGEFINDER_BASIS,
            'seq' => TFISH_RANGEFINDER_SEQUENCES,
            'years' => TFISH_RANGEFINDER_YEARS,
            'geo' => TFISH_RANGEFINDER_GEO,
            'unmapped' => TFISH_RANGEFINDER_RECORD_SET,
        ];

        $dropped = [];

        foreach ($this->model->mapQuery($this->state)['dropped'] as $key) {
            $dropped[] = $labels[$key] ?? $key;
        }

        return $dropped;
    }

    /**
     * URL of one locality on the map (D-22: keyed on site_key, never locality_id).
     *
     * @param   string $siteKey The locality's stable site key.
     * @return  string
     */
    public function localityUrl(string $siteKey): string
    {
        return TFISH_URL . 'map/?locality=' . \rawurlencode($siteKey);
    }

    /**
     * URL that switches between the curated set and the D-6 place-text quarantine.
     *
     * Switching resets pagination and the position filters, which is what parseFilters() does with
     * `unmapped=1` anyway — done through the same path here so the link and the query agree.
     *
     * @return  string
     */
    public function switchSetUrl(): string
    {
        return $this->url([
            'unmapped' => $this->state['unmapped'] ? null : '1',
            'geo' => null,
            'gaps' => null,
            'locality' => null,
        ]);
    }

    /**
     * Whether the D-6 quarantine set is being listed.
     *
     * The template shows the explanatory banner off this: these rows name a place but have no
     * coordinates, and must never be presented as though they had a position.
     *
     * @return  bool
     */
    public function isQuarantineSet(): bool
    {
        return (bool) $this->state['unmapped'];
    }

    /**
     * Whether any filter is active, for the "clear all" control and the empty state.
     *
     * @return  bool
     */
    public function isFiltered(): bool
    {
        return !$this->model->isDefaultState($this->state);
    }

    /**
     * The page's own meta description.
     *
     * @return  string
     */
    public function description(): string
    {
        return $this->description;
    }
}
