/**
 * Rangefinder occurrence map client.
 *
 * Plain ES5-compatible browser JavaScript in one IIFE. No framework, no bundler, no build step:
 * the module installs by copying directories, and adding a toolchain would trade that away for
 * nothing this page needs.
 *
 * Reads everything from window.rangefinder, which templates/map.html populates server-side:
 *
 *   markers        {localities, occurrences, details, sources}  see expandPayload() for the tuples
 *   speciesFacet   [{canonical_taxon, ploidy, display_name, n_records, n_mapped}, ...]
 *   countryFacet   [{country_code, country_name, n_records, min_lat, max_lat, min_lng, max_lng}, ...]
 *   tileProvider   {key, label, url, maxZoom, attribution, subdomains} -- the ACTIVE one only
 *   maxZoom        hard zoom ceiling, applied on top of the provider's own
 *   strings        translated fragments for text this file generates
 *
 * The whole marker set arrives with the page, so every filter interaction is local and there is no
 * loading state anywhere in this file. That is deliberate and is what makes the cluster counts
 * trustworthy -- see buildClusterGroups().
 *
 * CONFIDENCE MODEL (D-18). Every occurrence falls in one of three buckets, derived once from its
 * stored layer and taxon_rank (see expandPayload):
 *   verified     -- layer 'species': an expert determination.
 *   reported     -- layer 'presence', rank species: a species name from a non-authoritative source.
 *   unidentified -- layer 'presence', rank genus: a lead that reaches only the genus.
 * The filter, the popup and the summary all speak in these three. Marker colour, though, stays
 * TWO-TONE: a verified determination, or a lead (reported and unidentified share the lead colour).
 * That is the load-bearing line -- a lead must never look like a determination, never be totalled
 * with one, and never be labelled as a species claim. Every place this file could blur it is marked.
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
     * Species, presence and both differ in hue (blue / violet / green), not merely in shade: the
     * distinction has to survive a small screen, a projector and colour-blind vision, because the
     * failure it guards against (a lead read as a determination) is silent.
     */
    var PALETTE = {
        species: {
            radius: 7,
            color: '#000000',
            weight: 2,
            opacity: 1,
            fillColor: '#1b6ca8',
            fillOpacity: 0.85
        },
        // Solid violet fill with a black edge: a genus-only lead, unverified. The black border and
        // opaque fill keep it legible on any basemap; species (blue), presence (violet) and both
        // (crimson) separate by hue alone, so all three share one radius/weight — the markers are the
        // same size and stroke, differing only in colour.
        presence: {
            radius: 7,
            color: '#000000',
            weight: 2,
            opacity: 1,
            fillColor: '#ae3ec9',
            fillOpacity: 0.9
        },
        // A locality holding both a determination and a lead. Neither blue nor violet — a third
        // solid colour (crimson) so it can't be misread as either single-layer marker. Red reads
        // against a topographic basemap (muted greens/browns/pale-blues) where green blended into
        // vegetation and water tint; the dark edge holds it on the rare warm-toned ground.
        both: {
            radius: 7,
            color: '#000000',
            weight: 2,
            opacity: 1,
            fillColor: '#e03131',
            fillOpacity: 0.9
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

    // Holding filter option -> the holding_type values it admits. 'obtainable' is the union of the
    // three kinds that still physically exist; the three narrower options select one kind each.
    // 'exhausted' is a holding that existed and is used up: still evidence the material was
    // collected, but not obtainable now, so it is deliberately excluded from 'obtainable'.
    // Cap on the per-record drill-down for the 'unidentified' bucket only. These are mostly bare
    // genus-level leads whose provenance matters little; a dense site can stack dozens under one
    // "genus-level record" line. We surface the sample-backed ones first (a held specimen can still
    // be worth chasing even without a species name), cap the list, and defer the full set to the
    // /explore page (Phase 4). Verified and reported records are never capped — their source is the
    // point of the card.
    var UNIDENTIFIED_RECORD_CAP = 8;

    var HOLDING = {
        obtainable: ['live_cysts', 'preserved_specimen', 'tissue_or_dna'],
        live_cysts: ['live_cysts'],
        preserved_specimen: ['preserved_specimen'],
        tissue_or_dna: ['tissue_or_dna'],
        exhausted: ['exhausted']
    };

    var strings = rf.strings || {};
    var map = null;
    var clusters = {};
    var precisionLayer = null;
    var localities = [];
    var sources = [];
    var markersById = {};
    var elements = {};

    var state = {
        verified: true,
        reported: true,
        unidentified: true,
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
     *   occurrence tuple  [localityIndex, canonical_taxon, ploidy, layer, country_code, holding_type, taxon_rank]
     *   detail tuple      [event_date, recorded_by, sourceIdx, catalog_number, disposition,
     *                      references_url, holder_url]
     *   source tuple      [key, label, citation, doi, license]
     *
     * details[] is aligned index-identically to occurrences[] (same order, built in one server pass),
     * so detail[i] belongs to occurrence[i]; sourceIdx points into the deduped sources[] list. The
     * whole record-level card layer ships inline with the page (D-20): the popup drill-down reads it
     * from memory, so there is no detail endpoint and no per-record fetch.
     */
    function expandPayload() {
        var payload = rf.markers || {};
        var rawLocalities = payload.localities || [];
        var rawOccurrences = payload.occurrences || [];
        var rawDetails = payload.details || [];
        var rawSources = payload.sources || [];

        sources = rawSources.map(function (tuple) {
            return {
                key: tuple[0],
                label: tuple[1],
                citation: tuple[2],
                doi: tuple[3],
                license: tuple[4]
            };
        });

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

        rawOccurrences.forEach(function (tuple, i) {
            var locality = localities[tuple[0]];

            if (!locality) return;

            // detail[i] is aligned to occurrence[i] by construction; a missing row degrades to an
            // empty detail rather than throwing, so the marker still renders.
            var d = rawDetails[i] || [];
            var sourceIdx = d[2];

            locality.occurrences.push({
                name: tuple[1],
                ploidy: tuple[2],
                layer: tuple[3],
                country: tuple[4],
                holding: tuple[5],
                // Confidence bucket (D-18), derived once here so the filter, popup and marker code
                // all read the same value. verified = a determination; reported = a species name
                // from a non-authoritative source; unidentified = a lead reaching only the genus.
                category: tuple[3] === 'species' ? 'verified'
                    : (tuple[6] === 'genus' || !tuple[6]) ? 'unidentified' : 'reported',
                // Record-level card fields (D-20). Read lazily by buildPopup; never touched on the
                // hot filter path, so per-keystroke matching is unchanged.
                detail: {
                    date: d[0],
                    recordedBy: d[1],
                    source: (sourceIdx !== null && sourceIdx !== undefined) ? sources[sourceIdx] : null,
                    accession: d[3],
                    disposition: d[4],
                    referencesUrl: d[5],
                    holderUrl: d[6]
                }
            });

            // hasSpecies means "a verified determination exists here" and drives the gap map and the
            // mixed-marker styling; it keys on the verified bucket, i.e. layer 'species'.
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
     * Sort rank for a ploidy word: unknown (none) first, then by increasing chromosome-set count,
     * with the open-ended "polyploid" last. Only used to order a lineage's variants in the filter;
     * an unrecognised value sorts after the known set rather than throwing.
     *
     * @param   {?string} ploidy
     * @returns {number}
     */
    function ploidyRank(ploidy) {
        var order = { diploid: 1, triploid: 2, tetraploid: 3, pentaploid: 4, polyploid: 5 };
        if (!ploidy) return 0;
        return order[ploidy] || 6;
    }

    /**
     * Display form of a scientific name.
     *
     * Names arrive as the canonical_taxon: already citation-free, rank-marker-free and lower-cased at
     * import (the one place normalisation happens). Display just restores binomial case by
     * capitalising the initial (genus up, epithet down), so there is nothing to parse here.
     *
     * @param   {?string} name
     * @returns {string}
     */
    function displayTaxon(name) {
        if (!name) return '';

        return name.charAt(0).toUpperCase() + name.slice(1);
    }

    /**
     * Return a URL only if it is a plain http(s) link, else null.
     *
     * References and accession links come from external archives and are treated as hostile: a
     * javascript: or data: value in a source field must never become a live link. Only http:/https:
     * may pass; anything else is dropped so it renders (if at all) as inert text.
     *
     * @param   {?string} url
     * @returns {?string}
     */
    function safeHref(url) {
        if (!url) return null;

        return /^https?:\/\//i.test(url) ? url : null;
    }

    /**
     * Build an external anchor, or null if the URL fails the scheme check.
     *
     * @param   {?string} url
     * @param   {string} label
     * @param   {string} [className]
     * @returns {?Element}
     */
    function linkEl(url, label, className) {
        var href = safeHref(url);

        if (!href) return null;

        var a = el('a', className, label);
        a.setAttribute('href', href);
        a.setAttribute('target', '_blank');
        a.setAttribute('rel', 'noopener noreferrer');

        return a;
    }

    /**
     * Build the per-record detail line shown under a popup taxon (D-20).
     *
     * One compact line per occurrence: date, recorder, source, accession and any external record
     * link, plus a material badge when the record is backed by a physical holding. Every value is
     * source-supplied free text, so it goes in via el()/textContent; the two link fields pass the
     * http(s) scheme check before becoming anchors.
     *
     * Accession is shown as an identifier only — never as a claim that material is available. The
     * material badge (keyed on holding_type, with the finer disposition on hover) is the only
     * "obtainable material" signal, kept deliberately distinct from the accession.
     *
     * @param   {Object} occurrence
     * @returns {Element}
     */
    function buildRecordLine(occurrence) {
        var d = occurrence.detail || {};
        var line = el('li', 'rangefinder-record');

        line.appendChild(el('span', 'rangefinder-record-date', d.date || text('dateUnknown')));

        if (d.recordedBy) {
            line.appendChild(el('span', 'rangefinder-record-by',
                text('recordedBy', { name: d.recordedBy })));
        }

        // Source = the citable dataset (institution or publication), its full citation on hover.
        // This is what distinguishes a Field Museum record from a Naturalis one — never the coarse
        // holder institution.
        if (d.source && d.source.label) {
            var src = el('span', 'rangefinder-record-source', d.source.label);

            if (d.source.citation) src.setAttribute('title', d.source.citation);

            line.appendChild(src);
        }

        // Accession: a link only when a catalog URL template resolved one server-side (dormant until
        // a template exists); otherwise plain text. Either way an identifier, not a holding signal.
        if (d.accession) {
            var accLabel = text('accessionLabel', { id: d.accession });
            line.appendChild(linkEl(d.holderUrl, accLabel, 'rangefinder-record-accession')
                || el('span', 'rangefinder-record-accession', accLabel));
        }

        // The source's own record page (iNaturalist observation, museum record) — the meaningful
        // link for a bare observation that has no specimen behind it.
        var ref = linkEl(d.referencesUrl, text('viewRecord'), 'rangefinder-record-link');

        if (ref) line.appendChild(ref);

        // Material badge: shown only when the record is backed by a physical holding. disposition
        // (in collection / exhausted / …) rides as the tooltip so the compact badge stays readable.
        if (occurrence.holding) {
            var labels = strings.materialLabels || {};
            var mat = el('span', 'rangefinder-record-material rangefinder-material-' + occurrence.holding,
                labels[occurrence.holding] || occurrence.holding);

            if (d.disposition) mat.setAttribute('title', d.disposition);

            line.appendChild(mat);
        }

        return line;
    }

    /**
     * Sort key for the unidentified cap: obtainable material first, then depleted, then no holding.
     *
     * The always-visible cap should surface the records a visitor can act on, so a held (still
     * obtainable) sample outranks a used-up one, which outranks a bare observation. Records past the
     * cap are not dropped — they fold into the "+N more" accordion — so this only decides ordering,
     * never inclusion.
     *
     * @param   {Object} occurrence
     * @returns {number}
     */
    function holdingRank(occurrence) {
        if (!occurrence.holding) return 2;

        return occurrence.holding === 'exhausted' ? 1 : 0;
    }

    /**
     * Does one occurrence pass the current filters?
     *
     * @param   {Object} occurrence
     * @returns {boolean}
     */
    function matches(occurrence) {
        // Each occurrence sits in exactly one confidence bucket (verified / reported / unidentified);
        // if that bucket's checkbox is off, the record is hidden.
        if (!state[occurrence.category]) return false;

        // A species/lineage selection names one or more taxa. It constrains every record that makes a
        // species claim -- verified determinations AND reported leads -- to the selected taxa, because
        // both carry a canonical name that can match. Genus-only leads name no species, so they can
        // never satisfy a species selection and are hidden while one is active, regardless of the
        // "not identified" toggle (which is locked off to match; see syncUnidentifiedLock). An empty
        // selection means "no species restriction". Matching is by canonical name + ploidy, so a
        // verified and a reported record of the same taxon share a key despite differing verbatim
        // spellings. A reported match is still styled and labelled a lead, never a determination --
        // the confidence bucket (marker colour, popup) is untouched by this filter.
        if (state.species.length) {
            if (occurrence.category === 'unidentified') return false;

            if (state.species.indexOf(taxonKey(occurrence.name, occurrence.ploidy)) === -1) {
                return false;
            }
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
     * The three confidence buckets are listed and counted separately, in a fixed order. A combined
     * total would read as "n records of Artemia here", which for the two lead buckets is not known.
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

        // Fixed display order, strongest evidence first. 'verified' renders as a determination; the
        // two lead buckets render with an explicit unverified badge so neither can be read as one.
        var sections = [
            { category: 'verified', heading: 'verifiedHeading' },
            { category: 'reported', heading: 'reportedHeading' },
            { category: 'unidentified', heading: 'unidentifiedHeading' }
        ];

        sections.forEach(function (section) {
            var entries = {};
            var order = [];

            shown.forEach(function (occurrence) {
                if (occurrence.category !== section.category) return;

                // Unidentified leads make no species claim, so they collapse to a single
                // genus-level line whatever the underlying token (a bare genus, a BOLD BIN, or a
                // demoted null). Verified and reported keep their name + ploidy identity.
                var isLead = section.category === 'unidentified';
                var name = isLead ? null : occurrence.name;
                var ploidy = isLead ? null : occurrence.ploidy;
                var key = taxonKey(name, ploidy);

                if (!entries[key]) {
                    entries[key] = { name: name, ploidy: ploidy, count: 0, records: [] };
                    order.push(key);
                }

                entries[key].count += 1;
                entries[key].records.push(occurrence);
            });

            if (!order.length) return;

            wrapper.appendChild(el('p', 'rangefinder-filter-heading', text(section.heading)));

            var list = el('ul');

            order.forEach(function (key) {
                var entry = entries[key];
                var item = el('li');

                if (section.category === 'verified') {
                    // displayTaxon trims the author citation for display; the filter key is unaffected.
                    item.appendChild(el('span', 'rangefinder-taxon', displayTaxon(entry.name)));

                    if (entry.ploidy) {
                        item.appendChild(document.createTextNode(' '));
                        item.appendChild(el('span', 'rangefinder-ploidy', entry.ploidy));
                    }
                } else {
                    // A lead: show the reported name (citation trimmed) or a genus fallback, with an
                    // explicit unverified badge, so it can never be read off the page as a
                    // determination made at this site. A demoted (unrecognised) name arrives null
                    // from the server and falls to the fallback, so it is never shown.
                    item.appendChild(el('span', null, displayTaxon(entry.name) || text('genusOnly')));
                    item.appendChild(document.createTextNode(' '));
                    item.appendChild(el('span', 'rangefinder-badge', text('unverified')));
                }

                item.appendChild(document.createTextNode(' '));
                item.appendChild(el('span', 'rangefinder-tally',
                    text('recordTally', { count: entry.count })));

                // Drill-down: the individual records behind this tally (date, recorder, source,
                // accession, links). A demoted lead reaches here with its name already suppressed
                // in occurrences[], so the taxon line shows the genus fallback while these lines
                // still carry the record's real date/source — provenance without a species claim.
                //
                // Verified and reported list in full — their source is the point. Unidentified leads
                // are capped so a 96-record genus stack cannot bury the popup: rank sample-backed
                // records first (a held specimen can still matter), show at most
                // UNIDENTIFIED_RECORD_CAP inline, and fold the remainder into a "+N more" accordion
                // the visitor can open in place. Every folded record still carries its date — the one
                // field that matters for a bare genus-level lead — so nothing is lost, only deferred.
                var toShow = entry.records;
                var extra = [];

                if (section.category === 'unidentified') {
                    var ranked = entry.records.slice().sort(function (a, b) {
                        return holdingRank(a) - holdingRank(b);
                    });

                    toShow = ranked.slice(0, UNIDENTIFIED_RECORD_CAP);
                    extra = ranked.slice(UNIDENTIFIED_RECORD_CAP);
                }

                if (toShow.length) {
                    var records = el('ul', 'rangefinder-records');

                    toShow.forEach(function (occurrence) {
                        records.appendChild(buildRecordLine(occurrence));
                    });

                    item.appendChild(records);
                }

                // The folded remainder (only the capped 'unidentified' bucket ever has any). A toggle
                // button reveals a collapsed list of the rest in place — no request, no navigation.
                // Kept a real <button> for keyboard/AT reach; the /explore deep-link supersedes it in
                // Phase 4, but until then the records are here, not merely tallied.
                if (extra.length) {
                    var moreList = el('ul', 'rangefinder-records rangefinder-records-extra');
                    moreList.hidden = true;

                    extra.forEach(function (occurrence) {
                        moreList.appendChild(buildRecordLine(occurrence));
                    });

                    var moreLabel = text('moreRecords', { count: extra.length });
                    var toggle = el('button', 'rangefinder-record-more', moreLabel);
                    toggle.type = 'button';
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.addEventListener('click', function () {
                        var open = moreList.hidden;
                        moreList.hidden = !open;
                        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                        toggle.textContent = open ? text('fewerRecords') : moreLabel;
                    });

                    item.appendChild(toggle);
                    item.appendChild(moreList);
                }

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
     * no stable per-marker identity to toggle. A locality showing both a determination and a lead
     * becomes verified-only the moment its lead buckets are switched off.
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
            minZoom: 2,
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

        L.control.scale({ imperial: false }).addTo(map);

        addFullscreenControl(container);

        map.setView([20, 10], 2);

        return true;
    }

    /**
     * Add a fullscreen toggle as a Leaflet control.
     *
     * Built as an L.Control so it lives in the map's own control layer -- same corner stack, same
     * classes, same event handling as zoom and scale -- rather than a DOM button floating over the
     * canvas. Leaflet ships no fullscreen control of its own, and the plugin that adds one is not
     * worth a new dependency, so the control drives the browser Fullscreen API directly.
     *
     * The element made fullscreen is the map container only, not the filter panel. That is the whole
     * point of the requirement: filters keep their state (this touches nothing in the filter DOM or
     * the marker set) but are hidden while fullscreen, so they are changed after exiting. On
     * entering/exiting the map is resized with invalidateSize() so Leaflet redraws at the new
     * dimensions instead of leaving grey tile gaps.
     */
    function addFullscreenControl(container) {
        // Feature-detect: if the browser cannot go fullscreen (older iOS Safari), do not add a
        // control that would do nothing when pressed.
        if (!container.requestFullscreen && !container.webkitRequestFullscreen) return;

        var Fullscreen = L.Control.extend({
            options: { position: 'topleft' },

            onAdd: function () {
                var wrapper = L.DomUtil.create('div', 'leaflet-bar leaflet-control rangefinder-fullscreen');
                var link = L.DomUtil.create('a', '', wrapper);
                link.href = '#';
                link.setAttribute('role', 'button');
                this._link = link;
                this._setState(false);

                // stopPropagation so the click toggles fullscreen without also panning/zooming the
                // map underneath; preventDefault so the '#' href does not scroll the page.
                L.DomEvent.on(link, 'click', L.DomEvent.stop)
                    .on(link, 'click', this._toggle, this);

                return wrapper;
            },

            _setState: function (isFull) {
                var label = isFull ? text('exitFullscreen') : text('fullscreen');
                this._link.title = label;
                this._link.setAttribute('aria-label', label);
                this._link.innerHTML = isFull ? '✕' : '⛶'; // ✕ / ⛶
            },

            _toggle: function () {
                var doc = document;
                var isFull = doc.fullscreenElement || doc.webkitFullscreenElement;
                if (isFull) {
                    (doc.exitFullscreen || doc.webkitExitFullscreen).call(doc);
                } else {
                    (container.requestFullscreen || container.webkitRequestFullscreen).call(container);
                }
            }
        });

        var control = new Fullscreen();
        control.addTo(map);

        // A single handler for both the button and the Esc key / browser chrome, so the icon and the
        // map size stay correct however fullscreen was left.
        function onChange() {
            var isFull = !!(document.fullscreenElement || document.webkitFullscreenElement);
            control._setState(isFull);
            map.invalidateSize();
        }
        document.addEventListener('fullscreenchange', onChange);
        document.addEventListener('webkitfullscreenchange', onChange);
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
     * The facet lists every taxon that makes a species claim on the map -- verified determinations
     * and accepted reported names alike -- keyed on canonical_taxon so citation variants of a name
     * collapse to one choice. Selecting one filters both its verified and its reported records (a
     * reported match stays a lead in colour and popup; see matches). Genus-only leads are not offered.
     */
    function buildSpeciesFilter() {
        var list = elements.speciesList;
        var rows = (rf.speciesFacet || []).slice();

        if (!rows.length) return;

        // Order a lineage's ploidy variants by increasing ploidy level, unknown (no ploidy word)
        // first — e.g. A. parthenogenetica, then diploid, triploid, tetraploid, ... — rather than
        // the incidental facet order. Stable sort on a copy keeps the canonical-name grouping the
        // facet already supplies and only reorders within each name.
        rows.sort(function (a, b) {
            var byName = (a.canonical_taxon || '').localeCompare(b.canonical_taxon || '');
            if (byName !== 0) return byName;
            return ploidyRank(a.ploidy) - ploidyRank(b.ploidy);
        });

        // Lay the facet out as side-by-side columns rather than one tall scroll column, which
        // dominated the panel height. Columns are filled in order up to MAX_PER_COLUMN entries each,
        // so the leading columns are full and the last carries the remainder (13 rows -> 5 / 5 / 3).
        // Rows read top-to-bottom, left-to-right — a lineage's ploidy variants are not forced into
        // one column.
        var MAX_PER_COLUMN = 5;
        var column = null;

        rows.forEach(function (row, i) {
            if (i % MAX_PER_COLUMN === 0) {
                column = el('div', 'rangefinder-species-col');
                list.appendChild(column);
            }

            column.appendChild(speciesCheck(row));
        });
    }

    /**
     * One species/lineage checkbox row: the taxon name, an optional ploidy word, and the mapped count.
     * The value is the taxon key (canonical + ploidy), so the label text is free to be abbreviated
     * without affecting selection or filtering.
     *
     * @param   {object} row  Facet row {canonical_taxon, ploidy, display_name, n_mapped, ...}.
     * @return  {HTMLLabelElement}
     */
    function speciesCheck(row) {
        var label = el('label', 'rangefinder-check');
        var input = document.createElement('input');

        input.type = 'checkbox';
        input.value = taxonKey(row.canonical_taxon, row.ploidy);
        input.checked = state.species.indexOf(input.value) !== -1;
        input.addEventListener('change', onSpeciesChange);

        label.appendChild(input);
        label.appendChild(el('span', 'rangefinder-taxon', row.display_name));

        if (row.ploidy) {
            label.appendChild(document.createTextNode(' '));
            label.appendChild(el('span', 'rangefinder-tally', row.ploidy));
        }

        label.appendChild(document.createTextNode(' '));
        label.appendChild(el('span', 'rangefinder-tally',
            text('mappedTally', { mapped: row.n_mapped })));

        return label;
    }

    /**
     * Populate the country select.
     *
     * Falls back to the ISO 3166 table only where the database has no name for a code, so a name
     * recorded by the original source is never overwritten with a standardised one.
     */
    function buildCountryFilter() {
        var select = elements.country;
        var rows = (rf.countryFacet || []).filter(function (row) {
            // Only list countries with at least one record that plots. v_country_facet already
            // excludes zero-mapped countries; this guards against an older DB where it did not,
            // so a user never selects a country and is shown an empty map.
            return row.n_mapped > 0;
        }).map(function (row) {
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

        if (params.has('verified')) state.verified = params.get('verified') !== '0';
        if (params.has('reported')) state.reported = params.get('reported') !== '0';
        if (params.has('unidentified')) state.unidentified = params.get('unidentified') !== '0';
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

        if (!state.verified) params.set('verified', '0');
        if (!state.reported) params.set('reported', '0');
        if (!state.unidentified) params.set('unidentified', '0');
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

        syncUnidentifiedLock();
        apply(false);
    }

    /**
     * Lock the "not identified" control while a species/lineage selection is active.
     *
     * A species query cannot be answered by a genus-only lead, so those records are hidden while a
     * selection is active (see matches). This reflects that in the control: the checkbox is disabled
     * and shown unchecked so the reason is visible, while state.unidentified is preserved untouched
     * and resumes control the moment the selection is cleared.
     */
    function syncUnidentifiedLock() {
        var locked = state.species.length > 0;

        elements.unidentified.disabled = locked;
        elements.unidentified.checked = locked ? false : state.unidentified;
    }

    /**
     * Sync every control to the current state. Used after a preset changes several at once.
     */
    function syncControls() {
        elements.verified.checked = state.verified;
        elements.reported.checked = state.reported;
        elements.country.value = state.country;
        elements.holding.value = state.holding;

        Array.prototype.slice.call(elements.speciesList.querySelectorAll('input'))
            .forEach(function (input) {
                input.checked = state.species.indexOf(input.value) !== -1;
            });

        elements.gapMap.setAttribute('aria-pressed', state.gapsOnly ? 'true' : 'false');

        // Depends on state.species, so runs after the species checkboxes are synced above.
        syncUnidentifiedLock();
    }

    function bindControls() {
        elements.verified.addEventListener('change', function () {
            state.verified = this.checked;
            apply(false);
        });

        elements.reported.addEventListener('change', function () {
            state.reported = this.checked;
            apply(false);
        });

        elements.unidentified.addEventListener('change', function () {
            state.unidentified = this.checked;
            apply(false);
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

            // A gap is a locality with only leads and no verified determination: hide the verified
            // bucket, keep both lead buckets, and let gapsOnly drop any locality that has a
            // determination when judged on its full record set (see render()).
            state.gapsOnly = enabling;
            state.verified = !enabling;
            state.reported = true;
            state.unidentified = true;
            state.species = [];
            syncControls();
            apply(true);
        });

        elements.reset.addEventListener('click', function () {
            state.verified = true;
            state.reported = true;
            state.unidentified = true;
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
            verified: document.getElementById('rangefinderVerified'),
            reported: document.getElementById('rangefinderReported'),
            unidentified: document.getElementById('rangefinderUnidentified'),
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
