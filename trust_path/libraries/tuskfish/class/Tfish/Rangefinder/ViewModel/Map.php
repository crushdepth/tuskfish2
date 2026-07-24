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
     * @return  array ['records', 'localities', 'species', 'presence', 'countries'].
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
     * answer is 340 — wrong, and wrong silently. At 577 localities it is ~18 KB on the wire, which
     * also makes every filter interaction round-trip-free.
     *
     * Escaped with the JSON_HEX_* flags so it is safe to embed directly in a <script> block.
     *
     * @return  string JSON object of {localities, occurrences}.
     */
    public function markersJson(): string
    {
        return $this->encode([
            'localities' => $this->model->localities(),
            'occurrences' => $this->model->occurrences(),
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
     * Species / lineage facet as JSON, for the species filter.
     *
     * @return  string JSON array of facet rows.
     */
    public function speciesFacetJson(): string
    {
        return $this->encode($this->model->speciesFacet());
    }

    /**
     * Country facet (with bounding boxes) as JSON, for the country filter.
     *
     * @return  string JSON array of facet rows.
     */
    public function countryFacetJson(): string
    {
        return $this->encode($this->model->countryFacet());
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
