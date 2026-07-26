<?php

declare(strict_types=1);

namespace Tfish\Rangefinder\ViewModel;

/**
 * \Tfish\Rangefinder\ViewModel\Map class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */

/**
 * ViewModel for the Rangefinder occurrence map (/map/).
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\Viewable  Provides standard accessors required by the Viewable interface.
 * @var         string $description Meta description for this page.
 */
class Map implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;

    private object $model;
    private \Tfish\Entity\Preference $preference;
    private string $description = '';

    public function __construct(
        object $model,
        \Tfish\Entity\Preference $preference
    ) {
        $this->model = $model;
        $this->preference = $preference;
        $this->theme = $preference->defaultTheme();
        // $modulePath has no setter in the Viewable trait; assign the property directly. It lets
        // the module ship its own templates, so no theme file has to be edited to install it.
        $this->modulePath = TFISH_RANGEFINDER_TEMPLATE_PATH;
        $this->pageTitle = TFISH_RANGEFINDER_MAP_TITLE;
        $this->description = TFISH_RANGEFINDER_MAP_DESCRIPTION;
    }

    /**
     * Render the occurrence map.
     *
     * Sets up the page but does not load its data. The model's accessors load on first use, so on a
     * cache hit — where the template is never rendered and no accessor is ever called — the
     * occurrence database is not queried at all.
     */
    public function displayMap(): void
    {
        $this->template = 'map';
        $this->buildMetadata();
    }

    /**
     * Section key for the module's navigation and canonical URL.
     *
     * @return  string
     */
    public function pageKey(): string
    {
        return 'map';
    }

    /**
     * Assemble page title, description and canonical URL metadata.
     *
     * The canonical URL always points at the bare /map/ page. Filter and locality states are
     * expressed as deep-link parameters over the same server-rendered HTML, so they consolidate to
     * one indexable URL rather than being treated as distinct pages.
     */
    private function buildMetadata(): void
    {
        $this->metadata = [
            'title' => $this->pageTitle,
            'description' => $this->description,
            'canonicalUrl' => TFISH_URL . $this->pageKey() . '/',
        ];
    }

    /**
     * Headline counts for the loaded marker payload.
     *
     * @return  array ['records', 'localities', 'verified', 'reported', 'unidentified', 'countries'].
     */
    public function summary(): array
    {
        return $this->model->summary();
    }

    /**
     * Marker payload as JSON, for the client-side map and filters.
     *
     * Emitted as distinct localities plus lean occurrence tuples that reference them by index —
     * the shape the client needs anyway, since one locality is one marker. See
     * \Tfish\Rangefinder\Model\Map::buildMarkerPayload() for the tuple layouts.
     *
     * This whole payload ships once with the page rather than being fetched per viewport, because
     * clustering is computed in the browser and a cluster count is only truthful if every marker is
     * in the group. Load markers by viewport and a world-zoom bubble over Iran reads "12" when the
     * answer is 340 — wrong, and wrong silently. It also makes every filter interaction
     * round-trip-free.
     *
     * Size, measured 2026-07-26 for 548 localities / 2,127 records: 415 KB of JSON — details[] 44%,
     * occurrences[] 40%, localities[] 8%, sources[] 7% — in a 469 KB page that gzips to 59 KB on the
     * wire. The ~100 KB threshold D-12 set for revisiting the shape is a transfer figure, and 59 KB
     * clears it; the 415 KB is what the browser holds in memory, which is not the same cost. Shape
     * settled: keep it embedded. Splitting could only move details[] + sources[] (~30 KB gzipped)
     * behind a fetch, since localities[] + occurrences[] must be in memory for cluster counts to be
     * truthful — so it would buy half the payload at the price of a round trip per popup and a
     * detail endpoint to maintain. Revisit if the dataset grows several-fold (10,000+ records) or a
     * large new source lands, not on the raw byte count alone.
     *
     * Escaped with the JSON_HEX_* flags so it is safe to embed directly in a <script> block.
     *
     * details[] and sources[] add the record-level card layer (D-20): details[] is aligned
     * index-identically to occurrences[] and carries the per-record card fields (date, recorder,
     * accession, accuracy radius, …); sources[] is the deduped attribution lookup its sourceIdx
     * points into. Both ship inline so the popup drill-down renders with no server round-trip and
     * no detail endpoint.
     *
     * taxa{} maps each canonical name to its display form, so the client renders a scientific name
     * by looking it up rather than by knowing how one is written.
     *
     * @return  string JSON object of {localities, occurrences, details, sources, taxa}.
     */
    public function markersJson(): string
    {
        return $this->encode([
            'localities' => $this->model->localities(),
            'occurrences' => $this->model->occurrences(),
            'details' => $this->model->details(),
            'sources' => $this->model->sources(),
            'taxa' => $this->model->taxa(),
        ]);
    }

    /**
     * The active basemap tile provider as JSON.
     *
     * Only the active provider is emitted. The browser needs exactly one, and shipping the whole
     * registry would publish the API key of every configured-but-inactive provider to anyone who
     * views source.
     *
     * @return  string JSON object of {key, label, url, maxZoom, attribution, subdomains}.
     */
    public function tileProviderJson(): string
    {
        return $this->encode($this->model->tileProvider());
    }

    /**
     * Hard ceiling on map zoom, independent of what the tile provider allows.
     *
     * @return  int Maximum zoom level.
     */
    public function maxZoom(): int
    {
        return TFISH_RANGEFINDER_MAX_ZOOM;
    }

    /**
     * Species / lineage facet rows, for the server-rendered species filter.
     *
     * Not JSON and not shipped to the browser: the facet is fixed once the page is built, so the
     * checkbox list is rendered in templates/map.html and the client only ever reads which boxes
     * are checked.
     *
     * @return  array List of ['canonical_taxon', 'ploidy', 'key', 'display_name', 'n_mapped'] rows.
     */
    public function speciesFacet(): array
    {
        return $this->model->speciesFacet();
    }

    /**
     * Country filter options, for the server-rendered country select.
     *
     * Mapped countries only, names resolved and ordered. Same reasoning as speciesFacet(): static
     * at render time, so it is markup rather than a payload.
     *
     * @return  array List of ['code', 'name', 'sort', 'n_mapped'] rows.
     */
    public function countryOptions(): array
    {
        return $this->model->countryOptions();
    }

    /**
     * Per-country bounding boxes as JSON.
     *
     * The one part of the country facet that must reach the browser: it is read at interaction
     * time, to reframe the map on a country whose current filter combination plots no markers.
     *
     * @return  string JSON array of ['code', 'min_lat', 'max_lat', 'min_lng', 'max_lng'] rows.
     */
    public function countryBoundsJson(): string
    {
        return $this->encode($this->model->countryBounds());
    }

    /**
     * Encode a payload for safe embedding in an inline script block.
     *
     * @param   array $payload Data to encode.
     * @return  string JSON, or an empty array literal if encoding fails.
     */
    private function encode(array $payload): string
    {
        try {
            return \json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            );
        } catch (\JsonException $e) {
            return '[]';
        }
    }
}
