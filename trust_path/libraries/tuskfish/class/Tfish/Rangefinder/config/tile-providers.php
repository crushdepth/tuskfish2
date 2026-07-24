<?php

declare(strict_types=1);

/**
 * Rangefinder basemap tile provider registry.
 *
 * THIS FILE IS MEANT TO BE EDITED. To change the basemap, change the value of 'active' below to
 * another key in 'providers' and reload. Nothing else needs touching, and there is nothing to
 * rebuild or redeploy.
 *
 * The provider is configuration rather than code because none of the free basemap sources carries
 * an SLA: openmaps.fr states outright that it may block or stop without notice, and a free tier can
 * change terms at any time. When that happens the fix should be a one-line edit, not a code change
 * — which is also what makes the self-hosted escape hatch below a config switch instead of a
 * rebuild.
 *
 * Attribution travels with the provider entry, not with the page, because the credit shown must be
 * the credit for the tiles actually being served. A hard-coded attribution line goes quietly wrong
 * the moment the provider is swapped, which is a licence breach rather than a cosmetic bug.
 *
 * Entry format:
 *   label        Human-readable name, shown only in logs and any future admin UI.
 *   url          Leaflet tile URL template. {z}/{x}/{y} are Leaflet's; {apiKey} (if the entry sets
 *                requiresKey) is substituted by \Tfish\Rangefinder\Model\Map::resolveProvider().
 *   maxZoom      Deepest zoom the provider actually serves. The map additionally caps zoom at
 *                TFISH_RANGEFINDER_MAX_ZOOM, so this only ever tightens the cap, never loosens it.
 *   attribution  Required credit string. HTML; keep it to anchors and plain text.
 *   requiresKey  If true, the entry is treated as unusable until apiKey is non-empty, and the map
 *                falls back to a keyless provider rather than requesting tiles that will 401.
 *   apiKey       Your key. Leave empty for keyless providers.
 *   subdomains   Optional; for {s} in the URL template.
 *
 * An unknown 'active' key, or an entry missing its URL or its required API key, falls back to the
 * first usable keyless provider and logs it. A typo here degrades to a working map, not a blank one.
 *
 * Only the active entry is ever sent to the browser, so an API key configured against an inactive
 * provider is not published in the page source.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */

return [

    // The one line an administrator edits. Must be a key of 'providers' below.
    'active' => 'openhikingmap',

    'providers' => [

        // Keyless default, so the module works out of the box with nothing to sign up for.
        // Topographic family: hillshade + contours + water + rivers. Busier than Stamen Terrain —
        // that is the trade-off for needing no key. Free for non-commercial use only, which suits a
        // CC-BY-NC dataset; under ~400k tiles/month, rate-limited per IP, bulk prefetch prohibited,
        // and no SLA at all. The styles are open source and self-hostable, so lock-in is low.
        'openhikingmap' => [
            'label' => 'OpenHikingMap (openmaps.fr)',
            'url' => 'https://tile.openmaps.fr/openhikingmap/{z}/{x}/{y}.png',
            'maxZoom' => 15,
            'attribution' => 'Tiles <a href="https://openmaps.fr/" target="_blank" rel="noopener">openmaps.fr</a> '
                . '(<a href="https://openmaps.fr/donate" target="_blank" rel="noopener">donate</a>) | '
                . 'Data &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">'
                . 'OpenStreetMap</a> contributors',
            'requiresKey' => false,
            'apiKey' => '',
        ],

        // Preferred look: relief + water + rivers without contour-line noise. Free non-commercial
        // tier is volume-capped rather than zoom-capped. Needs a free Stadia API key plus a domain
        // allowlist in production (keyless on localhost). Until a key is filled in below this entry
        // is skipped automatically, so setting 'active' to it prematurely is harmless.
        'stamen-terrain' => [
            'label' => 'Stamen Terrain (Stadia Maps)',
            'url' => 'https://tiles.stadiamaps.com/tiles/stamen_terrain/{z}/{x}/{y}.png?api_key={apiKey}',
            'maxZoom' => 16,
            'attribution' => '&copy; <a href="https://stadiamaps.com/" target="_blank" rel="noopener">Stadia Maps</a> | '
                . '&copy; <a href="https://stamen.com/" target="_blank" rel="noopener">Stamen Design</a> | '
                . '&copy; <a href="https://openmaptiles.org/" target="_blank" rel="noopener">OpenMapTiles</a> | '
                . '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">'
                . 'OpenStreetMap</a> contributors',
            'requiresKey' => true,
            'apiKey' => '',
        ],

        // Keyless fallback. Note ESRI's REST tile URLs are {z}/{y}/{x} — y before x, unlike every
        // other entry here. Serves to ~z13, comfortably past our cap. Attribution is required; the
        // formal free-use terms are less clear-cut than the others, so treat this as a solid backup
        // rather than a permanent primary.
        'esri-terrain' => [
            'label' => 'ESRI World Terrain Base',
            'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Terrain_Base/MapServer/tile/{z}/{y}/{x}',
            'maxZoom' => 13,
            'attribution' => 'Tiles &copy; <a href="https://www.esri.com/" target="_blank" rel="noopener">Esri</a> | '
                . 'Source: USGS, Esri, TANA, DeLorme, NAVTEQ',
            'requiresKey' => false,
            'apiKey' => '',
        ],

        // Independence escape hatch: pre-rendered raster tiles served from this site, over the
        // capped zoom range only (a capped range is a small tile count). Zero dependence on anyone
        // else's free-tier goodwill; costs a one-time rendering toolchain. Fill in the URL and
        // attribution, drop the tiles in place, and flip 'active' — no code change.
        'self-hosted' => [
            'label' => 'Self-hosted raster tiles',
            'url' => '',
            'maxZoom' => 11,
            'attribution' => '',
            'requiresKey' => false,
            'apiKey' => '',
        ],
    ],
];
