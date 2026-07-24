/**
 * Rangefinder occurrence map client.
 *
 * Plain ES5-compatible browser JavaScript in one IIFE. No framework, no bundler, no build step:
 * the module installs by copying directories, and adding a toolchain would trade that away for
 * nothing this page needs.
 *
 * Reads everything from window.rangefinder, which templates/map.html populates server-side:
 *
 *   markers        {localities: [...], occurrences: [...]}  see expandPayload() for the tuples
 *   speciesFacet   [{verbatim_scientific_name, ploidy, n_records, n_mapped}, ...]
 *   countryFacet   [{country_code, country_name, n_records, min_lat, max_lat, min_lng, max_lng}, ...]
 *   tileProvider   {key, label, url, maxZoom, attribution, subdomains} -- the ACTIVE one only
 *   maxZoom        hard zoom ceiling, applied on top of the provider's own
 *   strings        translated fragments for text this file generates
 *
 * The whole marker set arrives with the page, so every filter interaction is local and there is no
 * loading state anywhere in this file. That is deliberate and is what makes the cluster counts
 * trustworthy -- see buildClusterGroups().
 *
 * TWO-LAYER MODEL. Occurrences carry layer = 'species' or 'presence'. A species record is a
 * taxonomic determination; a presence record is a genus-level report that says nothing about which
 * species. They must never look alike, never be totalled together, and a presence record must
 * never be labelled with a species name. Every place this file could blur them is marked.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @package     Rangefinder
 */
(function () {
    'use strict';

    var rf = window.rangefinder = window.rangefinder || {};

    /**
     * Marker palette. Mirrored by the legend swatches in rangefinder.css -- change both together.
     *
     * Passed to Leaflet as path options rather than applied from the stylesheet, because Leaflet
     * writes SVG presentation attributes directly onto the path and CSS would have to fight it.
     *
     * Species and presence differ in hue, in fill and in stroke pattern, not merely in shade: the
     * distinction has to survive a small screen, a projector and colour-blind vision, because the
     * failure it guards against (a lead read as a determination) is silent.
     */
    var PALETTE = {
        species: {
            radius: 7,
            color: '#0d3c61',
            weight: 2,
            opacity: 1,
            fillColor: '#1b6ca8',
            fillOpacity: 0.85
        },
        presence: {
            radius: 6,
            color: '#8a5207',
            weight: 2,
            opacity: 1,
            dashArray: '3,3',
            fillColor: '#c2740a',
            fillOpacity: 0.15
        },
        // A locality holding both. Species fill (a determination does exist here) inside a dashed
        // presence-coloured ring (so does an unverified report). Resolvable from either neighbour.
        both: {
            radius: 7,
            color: '#8a5207',
            weight: 2.5,
            opacity: 1,
            dashArray: '4,3',
            fillColor: '#1b6ca8',
            fillOpacity: 0.85
        },
        // Precision circle. Faint by design: it is a statement about uncertainty, not a feature.
        precision: {
            weight: 1,
            color: '#1b6ca8',
            opacity: 0.35,
            fillColor: '#1b6ca8',
            fillOpacity: 0.06,
            interactive: false
        }
    };

    // Holding filter option -> the holding_type values it admits. 'exhausted' is a holding that
    // existed and is used up: still evidence the material was collected, but not obtainable now,
    // so it is deliberately excluded from 'obtainable'.
    var HOLDING = {
        obtainable: ['live_cysts', 'preserved_specimen', 'tissue_or_dna'],
        live: ['live_cysts'],
        exhausted: ['exhausted']
    };

    var strings = rf.strings || {};
    var map = null;
    var clusters = {};
    var precisionLayer = null;
    var localities = [];
    var markersById = {};
    var elements = {};

    var state = {
        speciesLayer: true,
        presenceLayer: true,
        species: [],
        country: '',
        holding: 'any',
        gapsOnly: false,
        locality: 0
    };

    /**
     * Look up a translated fragment, substituting {token} placeholders.
     *
     * @param   {string} key
     * @param   {Object} [tokens]
     * @returns {string}
     */
    function text(key, tokens) {
        var value = strings[key] || '';

        if (tokens) {
            Object.keys(tokens).forEach(function (token) {
                value = value.split('{' + token + '}').join(String(tokens[token]));
            });
        }

        return value;
    }

    /**
     * Create an element, setting its text with textContent.
     *
     * Every value this file puts in the DOM is source-supplied free text from external archives --
     * locality names, verbatim determinations, remarks -- so it goes in as text, never as markup.
     * The payload itself is JSON_HEX_* escaped server-side, which closes injection into the script
     * block; this closes the other end, where that data is written back out into the page.
     *
     * @param   {string} tag
     * @param   {string} [className]
     * @param   {string} [content]
     * @returns {Element}
     */
    function el(tag, className, content) {
        var node = document.createElement(tag);

        if (className) node.className = className;
        if (content !== undefined && content !== null && content !== '') node.textContent = content;

        return node;
    }

    /**
     * Expand the wire payload into locality objects, each owning its occurrences.
     *
     * The server ships distinct localities plus positional occurrence tuples that reference them by
     * index, which removes the repetition of re-sending a locality's name and coordinates once per
     * record (~779 KB flat down to ~186 KB). Rehydrating here costs one pass and gives the rest of
     * this file readable property names.
     *
     *   locality tuple    [locality_id, name, latitude, longitude, precision_m]
     *   occurrence tuple  [localityIndex, verbatim_name, ploidy, layer, country_code, holding_type]
     */
    function expandPayload() {
        var payload = rf.markers || {};
        var rawLocalities = payload.localities || [];
        var rawOccurrences = payload.occurrences || [];

        localities = rawLocalities.map(function (tuple) {
            return {
                id: tuple[0],
                name: tuple[1],
                lat: tuple[2],
                lng: tuple[3],
                // Null means the source declared no precision. It must stay null: drawing a
                // default-radius circle would invent a certainty the record does not carry.
                precision: tuple[4],
                occurrences: [],
                hasSpecies: false
            };
        });

        rawOccurrences.forEach(function (tuple) {
            var locality = localities[tuple[0]];

            if (!locality) return;

            locality.occurrences.push({
                name: tuple[1],
                ploidy: tuple[2],
                layer: tuple[3],
                country: tuple[4],
                holding: tuple[5]
            });

            if (tuple[3] === 'species') locality.hasSpecies = true;
        });
    }

    /**
     * Key identifying one species/lineage choice: name plus ploidy.
     *
     * Ploidy is part of the identity, not a detail of it. Parthenogenetic lineages of differing
     * ploidy share a name while being biologically distinct, so collapsing them would merge
     * populations that the dataset deliberately keeps apart.
     *
     * @param   {?string} name
     * @param   {?string} ploidy
     * @returns {string}
     */
    function taxonKey(name, ploidy) {
        return (name || '') + '~' + (ploidy || '');
    }

    /**
     * Does one occurrence pass the current filters?
     *
     * @param   {Object} occurrence
     * @returns {boolean}
     */
    function matches(occurrence) {
        var isSpecies = occurrence.layer === 'species';

        if (isSpecies && !state.speciesLayer) return false;
        if (!isSpecies && !state.presenceLayer) return false;

        // The species/lineage selection constrains species records only. Presence records carry a
        // reported name, but it is genus-level and unverified, so it is not a species claim and
        // must not answer a species query. An empty selection means "no species restriction".
        if (isSpecies && state.species.length &&
            state.species.indexOf(taxonKey(occurrence.name, occurrence.ploidy)) === -1) {
            return false;
        }

        if (state.country && occurrence.country !== state.country) return false;

        if (state.holding !== 'any') {
            var admitted = HOLDING[state.holding];

            if (!admitted || admitted.indexOf(occurrence.holding) === -1) return false;
        }

        return true;
    }

    /**
     * Build the popup for a locality from the occurrences currently shown at it.
     *
     * Species and presence entries are listed separately and counted separately. A combined total
     * would read as "n records of Artemia here", which for the presence half is not known.
     *
     * @param   {Object} locality
     * @param   {Array} shown  Occurrences passing the filters.
     * @returns {Element}
     */
    function buildPopup(locality, shown) {
        var wrapper = el('div', 'rangefinder-popup');
        var coords = locality.lat.toFixed(4) + ', ' + locality.lng.toFixed(4);

        wrapper.appendChild(el('h2', null, locality.name || text('unnamedLocality')));
        wrapper.appendChild(el('p', 'rangefinder-coords', locality.precision
            ? text('coordsWithPrecision', { coords: coords, precision: locality.precision })
            : coords));

        ['species', 'presence'].forEach(function (layer) {
            var entries = {};
            var order = [];

            shown.forEach(function (occurrence) {
                if (occurrence.layer !== layer) return;

                var key = taxonKey(occurrence.name, occurrence.ploidy);

                if (!entries[key]) {
                    entries[key] = { name: occurrence.name, ploidy: occurrence.ploidy, count: 0 };
                    order.push(key);
                }

                entries[key].count += 1;
            });

            if (!order.length) return;

            var heading = el('p', 'rangefinder-filter-heading', text(layer === 'species'
                ? 'speciesHeading'
                : 'presenceHeading'));

            wrapper.appendChild(heading);

            var list = el('ul');

            order.forEach(function (key) {
                var entry = entries[key];
                var item = el('li');

                if (layer === 'species') {
                    item.appendChild(el('span', 'rangefinder-taxon', entry.name));

                    if (entry.ploidy) {
                        item.appendChild(document.createTextNode(' '));
                        item.appendChild(el('span', 'rangefinder-ploidy', entry.ploidy));
                    }
                } else {
                    // Presence: show the reported name only with an explicit unverified badge, so
                    // it can never be read off the page as a determination made at this site.
                    item.appendChild(el('span', null, entry.name || text('genusOnly')));
                    item.appendChild(document.createTextNode(' '));
                    item.appendChild(el('span', 'rangefinder-badge', text('unverified')));
                }

                item.appendChild(document.createTextNode(' '));
                item.appendChild(el('span', 'rangefinder-tally',
                    text('recordTally', { count: entry.count })));
                list.appendChild(item);
            });

            wrapper.appendChild(list);
        });

        return wrapper;
    }

    /**
     * Rebuild every marker from the current filter state.
     *
     * Rebuilds rather than toggles visibility: at 577 localities the whole pass is imperceptible,
     * and a marker's appearance depends on which of its occurrences survive the filter, so there is
     * no stable per-marker identity to toggle. A locality showing both layers becomes species-only
     * the moment the presence layer is switched off.
     */
    function render() {
        var shownLocalities = 0;
        var shownRecords = 0;
        var bounds = [];

        clusters.species.clearLayers();
        clusters.presence.clearLayers();
        precisionLayer.clearLayers();
        markersById = {};

        localities.forEach(function (locality) {
            // FR-7 gap map: localities with presence reports and no determination at all -- the
            // prospecting targets. Judged on the locality's FULL record set, not the filtered one,
            // or hiding the species layer would make every locality look like a gap.
            if (state.gapsOnly && locality.hasSpecies) return;

            var shown = locality.occurrences.filter(matches);

            if (!shown.length) return;

            var hasSpecies = false;
            var hasPresence = false;

            shown.forEach(function (occurrence) {
                if (occurrence.layer === 'species') {
                    hasSpecies = true;
                } else {
                    hasPresence = true;
                }
            });

            var kind = hasSpecies ? (hasPresence ? 'both' : 'species') : 'presence';
            var marker = L.circleMarker([locality.lat, locality.lng], PALETTE[kind]);

            marker.bindPopup(function () {
                return buildPopup(locality, shown);
            });

            // A mixed locality goes in the species group: a determination does exist there, so
            // clustering it as presence would understate what is known.
            clusters[hasSpecies ? 'species' : 'presence'].addLayer(marker);
            markersById[locality.id] = marker;

            // Only where the source declared a precision. No circle is the honest rendering of
            // "unknown"; a default radius would be a fabricated one.
            if (locality.precision) {
                precisionLayer.addLayer(L.circle([locality.lat, locality.lng], {
                    radius: locality.precision,
                    weight: PALETTE.precision.weight,
                    color: PALETTE.precision.color,
                    opacity: PALETTE.precision.opacity,
                    fillColor: PALETTE.precision.fillColor,
                    fillOpacity: PALETTE.precision.fillOpacity,
                    interactive: PALETTE.precision.interactive
                }));
            }

            bounds.push([locality.lat, locality.lng]);
            shownLocalities += 1;
            shownRecords += shown.length;
        });

        elements.plotted.textContent = text('showing', {
            shown: shownLocalities,
            total: localities.length,
            records: shownRecords
        });

        return bounds;
    }

    /**
     * Frame the map on the current selection.
     *
     * Prefers the filtered markers' own extent, which is tighter and truer than a country bounding
     * box. Falls back to the country facet's server-side box when a filter combination plots
     * nothing, so choosing a country still moves the map somewhere meaningful.
     *
     * @param   {Array} bounds  Coordinates of the plotted markers.
     */
    function frame(bounds) {
        if (bounds.length) {
            map.fitBounds(bounds, { padding: [30, 30], maxZoom: rf.maxZoom || 11 });
            return;
        }

        if (!state.country) return;

        var country = (rf.countryFacet || []).filter(function (row) {
            return row.country_code === state.country;
        })[0];

        if (country && country.min_lat !== null && country.min_lat !== undefined) {
            map.fitBounds([
                [country.min_lat, country.min_lng],
                [country.max_lat, country.max_lng]
            ], { padding: [30, 30], maxZoom: rf.maxZoom || 11 });
        }
    }

    /**
     * Create the base map and its tile layer.
     *
     * Zoom is capped at the tighter of the provider's maximum and the module's own ceiling. The
     * module ceiling exists because the coordinates do not support close inspection: precision runs
     * from about 31 m to about 1850 m, and localities are gridded to roughly 1 km at import, so a
     * marker examined at street level would look like a surveyed position when it is a rounded
     * centroid. Enforcing it here means a mis-edited provider entry cannot unlock deeper zoom.
     *
     * @returns {boolean} False if there is no usable tile provider.
     */
    function buildMap(container) {
        var provider = rf.tileProvider || {};
        var ceiling = rf.maxZoom || 11;

        if (!provider.url) return false;

        var maxZoom = Math.min(provider.maxZoom || ceiling, ceiling);

        map = L.map(container, {
            maxZoom: maxZoom,
            minZoom: 1,
            worldCopyJump: true
        });

        L.tileLayer(provider.url, {
            maxZoom: maxZoom,
            subdomains: provider.subdomains || 'abc',
            // The credit for the tiles actually being served. It travels with the provider entry
            // rather than the page, so swapping provider swaps the attribution with it.
            attribution: provider.attribution || ''
        }).addTo(map);

        // Data attribution sits alongside the basemap credit: the occurrence records are separately
        // licensed from the tiles, and both are required.
        if (text('dataAttribution')) {
            map.attributionControl.addAttribution(text('dataAttribution'));
        }

        map.setView([20, 10], 2);

        return true;
    }

    /**
     * Create the two cluster groups.
     *
     * Clustering runs entirely in the browser, over the markers in the group -- which is why the
     * whole marker set ships with the page. Load markers per viewport instead and a bubble covering
     * Iran at world zoom would read "12" when the answer is 340: the count would silently describe
     * what had been fetched rather than what exists. Correct viewport loading needs server-side
     * pre-aggregation per zoom level as its paired half; at 577 localities neither is warranted,
     * and the tripwire for revisiting is around 15-20k records.
     *
     * One group per layer, so a cluster bubble carries layer the way a marker does. A single mixed
     * group would need an icon encoding composition, which looks better and costs more; this is the
     * version that cannot accidentally imply a species claim.
     *
     * Cluster counts are locality counts, because markers are one per locality. That is the honest
     * unit here -- a locality with 40 records is one place, not 40 -- so the library's own
     * childCount is already right and is deliberately not overridden with a record sum.
     */
    function buildClusterGroups() {
        ['species', 'presence'].forEach(function (layer) {
            clusters[layer] = L.markerClusterGroup({
                showCoverageOnHover: false,
                // Mandatory, not cosmetic: with zoom capped there is no "zoom in further" escape,
                // so without spiderfying, localities closer together than the cap can resolve would
                // be permanently unreachable inside a cluster.
                spiderfyOnMaxZoom: true,
                disableClusteringAtZoom: rf.maxZoom || 11,
                maxClusterRadius: 45,
                iconCreateFunction: function (cluster) {
                    return new L.DivIcon({
                        // getChildCount() is a number, so this concatenation carries no user data.
                        html: '<div><span>' + cluster.getChildCount() + '</span></div>',
                        className: 'marker-cluster rangefinder-cluster rangefinder-cluster-' + layer,
                        iconSize: new L.Point(40, 40)
                    });
                }
            });

            map.addLayer(clusters[layer]);
        });

        precisionLayer = L.layerGroup().addTo(map);
    }

    /**
     * Populate the species/lineage checkbox list from the facet.
     *
     * Species layer only. Presence records are never offered as species names -- they are reached
     * through the presence toggle, which is the whole point of keeping the two axes separate.
     */
    function buildSpeciesFilter() {
        var list = elements.speciesList;

        (rf.speciesFacet || []).forEach(function (row) {
            var key = taxonKey(row.verbatim_scientific_name, row.ploidy);
            var label = el('label', 'rangefinder-check');
            var input = document.createElement('input');

            input.type = 'checkbox';
            input.value = key;
            input.checked = state.species.indexOf(key) !== -1;
            input.addEventListener('change', onSpeciesChange);

            label.appendChild(input);
            label.appendChild(el('span', 'rangefinder-taxon', row.verbatim_scientific_name));

            if (row.ploidy) {
                label.appendChild(document.createTextNode(' '));
                label.appendChild(el('span', 'rangefinder-tally', row.ploidy));
            }

            label.appendChild(document.createTextNode(' '));
            label.appendChild(el('span', 'rangefinder-tally',
                text('mappedTally', { mapped: row.n_mapped })));

            list.appendChild(label);
        });
    }

    /**
     * Populate the country select.
     *
     * Falls back to the ISO 3166 table only where the database has no name for a code, so a name
     * recorded by the original source is never overwritten with a standardised one.
     */
    function buildCountryFilter() {
        var select = elements.country;
        var rows = (rf.countryFacet || []).map(function (row) {
            return {
                code: row.country_code,
                name: rf.countryName ? rf.countryName(row.country_code, row.country_name)
                                     : (row.country_name || row.country_code),
                mapped: row.n_mapped
            };
        });

        rows.sort(function (a, b) {
            return a.name.localeCompare(b.name);
        });

        rows.forEach(function (row) {
            var option = document.createElement('option');

            option.value = row.code;
            option.textContent = row.name + ' (' + row.mapped + ')';
            option.selected = state.country === row.code;
            select.appendChild(option);
        });
    }

    /**
     * Read filter state from the query string.
     *
     * Deep links carry the filters but deliberately not the map centre or zoom, so a shared link
     * still frames its own selection sensibly after a data rebuild has moved the markers.
     */
    function readUrl() {
        if (!window.URLSearchParams) return;

        var params = new URLSearchParams(window.location.search);

        if (params.has('specieslayer')) state.speciesLayer = params.get('specieslayer') !== '0';
        if (params.has('presence')) state.presenceLayer = params.get('presence') !== '0';
        if (params.get('gaps') === '1') state.gapsOnly = true;
        if (params.has('country')) state.country = params.get('country') || '';

        if (params.has('holding')) {
            var holding = params.get('holding');

            state.holding = (holding === 'any' || HOLDING[holding]) ? holding : 'any';
        }

        if (params.get('species')) {
            state.species = params.get('species').split(',').filter(function (key) {
                return key !== '';
            });
        }

        state.locality = parseInt(params.get('locality'), 10) || 0;
    }

    /**
     * Write the current filter state back to the query string.
     *
     * replaceState rather than pushState: filter changes are adjustments to one view, so making
     * each one a history entry would turn the back button into an undo stack for checkbox clicks.
     * The canonical URL stays the bare /map/, set server-side, so these variants do not fragment
     * the page's search identity.
     */
    function writeUrl() {
        if (!window.URLSearchParams || !window.history || !window.history.replaceState) return;

        var params = new URLSearchParams();

        if (!state.speciesLayer) params.set('specieslayer', '0');
        if (!state.presenceLayer) params.set('presence', '0');
        if (state.gapsOnly) params.set('gaps', '1');
        if (state.species.length) params.set('species', state.species.join(','));
        if (state.country) params.set('country', state.country);
        if (state.holding !== 'any') params.set('holding', state.holding);

        var query = params.toString();

        window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
    }

    /**
     * Apply the current state: redraw, reframe if asked, and update the URL.
     *
     * @param   {boolean} reframe  Whether to move the map to fit the new selection.
     */
    function apply(reframe) {
        var bounds = render();

        if (reframe) frame(bounds);

        writeUrl();
    }

    function onSpeciesChange() {
        state.species = Array.prototype.slice
            .call(elements.speciesList.querySelectorAll('input:checked'))
            .map(function (input) {
                return input.value;
            });

        apply(false);
    }

    /**
     * Sync every control to the current state. Used after a preset changes several at once.
     */
    function syncControls() {
        elements.speciesLayer.checked = state.speciesLayer;
        elements.presenceLayer.checked = state.presenceLayer;
        elements.gaps.checked = state.gapsOnly;
        elements.country.value = state.country;
        elements.holding.value = state.holding;

        Array.prototype.slice.call(elements.speciesList.querySelectorAll('input'))
            .forEach(function (input) {
                input.checked = state.species.indexOf(input.value) !== -1;
            });

        elements.gapMap.setAttribute('aria-pressed', state.gapsOnly ? 'true' : 'false');
    }

    function bindControls() {
        elements.speciesLayer.addEventListener('change', function () {
            state.speciesLayer = this.checked;
            apply(false);
        });

        elements.presenceLayer.addEventListener('change', function () {
            state.presenceLayer = this.checked;
            apply(false);
        });

        elements.gaps.addEventListener('change', function () {
            state.gapsOnly = this.checked;
            syncControls();
            apply(true);
        });

        elements.country.addEventListener('change', function () {
            state.country = this.value;
            apply(true);
        });

        elements.holding.addEventListener('change', function () {
            state.holding = this.value;
            apply(false);
        });

        // FR-7 preset: presence layer only, restricted to localities with no determination, framed
        // on what is left. A button rather than a separate page, so it is one click back out.
        elements.gapMap.addEventListener('click', function () {
            var enabling = !state.gapsOnly;

            state.gapsOnly = enabling;
            state.speciesLayer = !enabling;
            state.presenceLayer = true;
            state.species = [];
            syncControls();
            apply(true);
        });

        elements.reset.addEventListener('click', function () {
            state.speciesLayer = true;
            state.presenceLayer = true;
            state.species = [];
            state.country = '';
            state.holding = 'any';
            state.gapsOnly = false;
            syncControls();
            apply(true);
        });
    }

    /**
     * Open the popup for a deep-linked locality, if it is in the current selection.
     */
    function openLinkedLocality() {
        if (!state.locality) return;

        var marker = markersById[state.locality];

        if (!marker) return;

        // The marker may be inside a cluster; zoomToShowLayer expands to it first.
        var group = clusters.species.hasLayer(marker) ? clusters.species : clusters.presence;

        if (group.zoomToShowLayer) {
            group.zoomToShowLayer(marker, function () {
                marker.openPopup();
            });
        } else {
            marker.openPopup();
        }
    }

    function init() {
        var container = document.getElementById('rangefinderMap');

        if (!container || typeof L === 'undefined') return;

        elements = {
            speciesList: document.getElementById('rangefinderSpecies'),
            speciesLayer: document.getElementById('rangefinderSpeciesLayer'),
            presenceLayer: document.getElementById('rangefinderPresenceLayer'),
            gaps: document.getElementById('rangefinderGaps'),
            country: document.getElementById('rangefinderCountry'),
            holding: document.getElementById('rangefinderHolding'),
            gapMap: document.getElementById('rangefinderGapMap'),
            reset: document.getElementById('rangefinderReset'),
            plotted: document.getElementById('rangefinderPlotted')
        };

        var missing = Object.keys(elements).some(function (key) {
            return !elements[key];
        });

        if (missing) return;

        expandPayload();
        readUrl();

        if (!buildMap(container)) {
            // No usable tile provider. Say so rather than presenting an empty grey rectangle that
            // reads as "no occurrence records here".
            container.appendChild(el('p', 'rangefinder-empty', text('noTileProvider')));
            return;
        }

        buildClusterGroups();
        buildSpeciesFilter();
        buildCountryFilter();
        syncControls();
        bindControls();
        apply(state.country !== '' || state.gapsOnly);
        openLinkedLocality();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
