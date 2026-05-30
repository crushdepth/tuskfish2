/**
 * Shared front-end helpers for the FishStat dashboard pages (/species/, /environment/).
 *
 * Exposes a single global, window.FishStat, holding the static member-state data, the inline
 * flag SVGs, small formatting/CSV utilities, and a reusable member-state filter component
 * (search box + autocomplete dropdown + flag row + reset/badge). Each page supplies its own
 * chart-building code and an onSelect callback; everything below is page-independent.
 *
 * @copyright   Simon Wilkinson 2022+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 */
(function(FS) {
    'use strict';

    FS.memberCountries = [
        { name: 'Australia', iso2: 'AU' },
        { name: 'Bangladesh', iso2: 'BD' },
        { name: 'Cambodia', iso2: 'KH' },
        { name: 'China', iso2: 'CN' },
        { name: 'China, Hong Kong SAR', iso2: 'HK' },
        { name: 'Democratic People\'s Republic of Korea', iso2: 'KP' },
        { name: 'India', iso2: 'IN' },
        { name: 'Indonesia', iso2: 'ID' },
        { name: 'Iran (Islamic Republic of)', iso2: 'IR' },
        { name: 'Lao People\'s Democratic Republic', iso2: 'LA' },
        { name: 'Malaysia', iso2: 'MY' },
        { name: 'Maldives', iso2: 'MV' },
        { name: 'Myanmar', iso2: 'MM' },
        { name: 'Nepal', iso2: 'NP' },
        { name: 'Pakistan', iso2: 'PK' },
        { name: 'Philippines', iso2: 'PH' },
        { name: 'Kingdom of Saudi Arabia', iso2: 'SA', dbName: 'Saudi Arabia' },
        { name: 'Sri Lanka', iso2: 'LK' },
        { name: 'Thailand', iso2: 'TH' },
        { name: 'Viet Nam', iso2: 'VN' }
    ];

    // Inline flag SVGs (simplified)
    FS.flagSvgs = {
        AU: '<svg viewBox="0 0 36 24"><rect fill="#00008B" width="36" height="24"/><g fill="#fff"><polygon points="0,0 7,4 0,8" /><polygon points="0,0 9,4 18,0 18,8 9,4 0,8" opacity=".6"/><circle cx="26" cy="10" r="2.5"/><circle cx="30" cy="5" r="1.5"/><circle cx="33" cy="9" r="1.5"/><circle cx="30" cy="14" r="1.5"/><circle cx="31" cy="18" r="1"/></g><g fill="#CC0000"><polygon points="0,3 7,4 0,5"/><polygon points="8,0 9,4 10,0"/><polygon points="8,8 9,4 10,8"/></g></svg>',
        BD: '<svg viewBox="0 0 36 24"><rect fill="#006a4e" width="36" height="24"/><circle cx="16" cy="12" r="7" fill="#f42a41"/></svg>',
        KH: '<svg viewBox="0 0 36 24"><rect fill="#032ea1" width="36" height="24"/><rect fill="#e00025" y="6" width="36" height="12"/><rect fill="#fff" x="12" y="8" width="12" height="6" rx="1"/></svg>',
        CN: '<svg viewBox="0 0 36 24"><rect fill="#de2910" width="36" height="24"/><g fill="#ffde00"><polygon points="5,3 6.2,6.7 3,4.8 7,4.8 3.8,6.7"/><polygon points="10,1 10.5,2.5 9,1.7 11,1.7 9.5,2.5"/><polygon points="12,3 12.5,4.5 11,3.7 13,3.7 11.5,4.5"/><polygon points="12,6 12.5,7.5 11,6.7 13,6.7 11.5,7.5"/><polygon points="10,8 10.5,9.5 9,8.7 11,8.7 9.5,9.5"/></g></svg>',
        HK: '<svg viewBox="0 0 36 24"><rect fill="#de2110" width="36" height="24"/><g fill="#fff" opacity="0.9"><circle cx="18" cy="12" r="5"/></g><g fill="#de2110"><circle cx="18" cy="12" r="3.5"/></g><g fill="#fff"><circle cx="18" cy="9" r="1.2"/><circle cx="15.5" cy="11" r="1.2"/><circle cx="16.5" cy="14" r="1.2"/><circle cx="19.5" cy="14" r="1.2"/><circle cx="20.5" cy="11" r="1.2"/></g></svg>',
        KP: '<svg viewBox="0 0 36 24"><rect fill="#024fa2" width="36" height="24"/><rect fill="#fff" y="3" width="36" height="18"/><rect fill="#ed1c27" y="4.5" width="36" height="15"/><rect fill="#024fa2" y="19.5" width="36" height="4.5"/><circle cx="11" cy="12" r="5" fill="#fff"/><polygon points="11,7 12.5,10.5 16,10.5 13,13 14.2,16.5 11,14 7.8,16.5 9,13 6,10.5 9.5,10.5" fill="#ed1c27"/></svg>',
        IN: '<svg viewBox="0 0 36 24"><rect fill="#f93" width="36" height="8"/><rect fill="#fff" y="8" width="36" height="8"/><rect fill="#128807" y="16" width="36" height="8"/><circle cx="18" cy="12" r="3" fill="none" stroke="#000080" stroke-width="0.5"/></svg>',
        ID: '<svg viewBox="0 0 36 24"><rect fill="#ce1126" width="36" height="12"/><rect fill="#fff" y="12" width="36" height="12"/></svg>',
        IR: '<svg viewBox="0 0 36 24"><rect fill="#239f40" width="36" height="8"/><rect fill="#fff" y="8" width="36" height="8"/><rect fill="#da0000" y="16" width="36" height="8"/><circle cx="18" cy="12" r="3" fill="#da0000"/></svg>',
        LA: '<svg viewBox="0 0 36 24"><rect fill="#ce1126" width="36" height="24"/><rect fill="#002868" y="6" width="36" height="12"/><circle cx="18" cy="12" r="4" fill="#fff"/></svg>',
        MY: '<svg viewBox="0 0 36 24"><g><rect fill="#cc0001" width="36" height="24"/><rect fill="#fff" y="1.7" width="36" height="1.7"/><rect fill="#fff" y="5.1" width="36" height="1.7"/><rect fill="#fff" y="8.6" width="36" height="1.7"/><rect fill="#fff" y="12" width="36" height="1.7"/><rect fill="#fff" y="15.4" width="36" height="1.7"/><rect fill="#fff" y="18.9" width="36" height="1.7"/><rect fill="#fff" y="22.3" width="36" height="1.7"/></g><rect fill="#010066" width="18" height="13.7"/><g fill="#fc0"><circle cx="8" cy="7" r="4"/><circle cx="9.5" cy="7" r="3.2" fill="#010066"/><polygon points="13,4 13.7,6 15.5,6 14,7.2 14.6,9 13,7.8 11.4,9 12,7.2 10.5,6 12.3,6"/></g></svg>',
        MV: '<svg viewBox="0 0 36 24"><rect fill="#d21034" width="36" height="24"/><rect fill="#007e3a" x="5" y="4" width="26" height="16"/><circle cx="19" cy="12" r="5" fill="#fff"/><circle cx="21" cy="12" r="5" fill="#007e3a"/></svg>',
        MM: '<svg viewBox="0 0 36 24"><rect fill="#fecb00" width="36" height="8"/><rect fill="#34b233" y="8" width="36" height="8"/><rect fill="#ea2839" y="16" width="36" height="8"/><polygon points="18,4 20.5,10 27,10 21.5,14 23.5,20.5 18,16.5 12.5,20.5 14.5,14 9,10 15.5,10" fill="#fff"/></svg>',
        NP: '<svg viewBox="0 0 36 24"><rect fill="#fff" width="36" height="24"/><polygon points="3,22 3,2 20,10 3,10 20,22" fill="#ce0000" stroke="#003893" stroke-width="1.5"/></svg>',
        PK: '<svg viewBox="0 0 36 24"><rect fill="#01411c" width="36" height="24"/><rect fill="#fff" width="9" height="24"/><circle cx="21" cy="12" r="5" fill="#fff"/><circle cx="22.5" cy="12" r="4" fill="#01411c"/><polygon points="24,8 24.7,10 26.5,10 25,11.2 25.5,13 24,11.8 22.5,13 23,11.2 21.5,10 23.3,10" fill="#fff"/></svg>',
        PH: '<svg viewBox="0 0 36 24"><rect fill="#0038a8" width="36" height="12"/><rect fill="#ce1126" y="12" width="36" height="12"/><polygon points="0,0 16,12 0,24" fill="#fff"/><circle cx="5.5" cy="12" r="2.5" fill="none" stroke="#fcd116" stroke-width="0.8"/></svg>',
        SA: '<svg viewBox="0 0 36 24"><rect fill="#006c35" width="36" height="24"/><rect fill="#fff" x="8" y="9" width="20" height="3" rx="1"/><rect fill="#fff" x="16" y="15" width="4" height="3" rx="0.5"/></svg>',
        LK: '<svg viewBox="0 0 36 24"><rect fill="#ffb700" width="36" height="24"/><rect fill="#00534e" x="2" y="2" width="8" height="20"/><rect fill="#ff6600" x="11" y="2" width="8" height="20"/><rect fill="#8b0000" x="20" y="2" width="14" height="20" rx="2"/></svg>',
        TH: '<svg viewBox="0 0 36 24"><rect fill="#a51931" width="36" height="24"/><rect fill="#fff" y="4" width="36" height="16"/><rect fill="#2d2a4a" y="8" width="36" height="8"/></svg>',
        VN: '<svg viewBox="0 0 36 24"><rect fill="#da251d" width="36" height="24"/><polygon points="18,5 20,11 26,11 21,14.5 23,20.5 18,17 13,20.5 15,14.5 10,11 16,11" fill="#ff0"/></svg>'
    };

    FS.escapeHtml = function(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    };

    // Choose a display unit (divisor/suffix/label/decimals) for a tonnes figure.
    FS.tonnesUnit = function(max) {
        if (max >= 1e6) return { divisor: 1e6, suffix: 'M', label: 'Million tonnes (live weight)', decimals: 2 };
        if (max >= 1e3) return { divisor: 1e3, suffix: 'K', label: 'Thousand tonnes (live weight)', decimals: 1 };
        return { divisor: 1, suffix: '', label: 'Tonnes (live weight)', decimals: 0 };
    };

    // Choose a display unit for a US dollar figure.
    FS.usdUnit = function(max) {
        if (max >= 1e9) return { divisor: 1e9, suffix: 'B', label: 'Billion USD', decimals: 2 };
        if (max >= 1e6) return { divisor: 1e6, suffix: 'M', label: 'Million USD', decimals: 1 };
        if (max >= 1e3) return { divisor: 1e3, suffix: 'K', label: 'Thousand USD', decimals: 1 };
        return { divisor: 1, suffix: '', label: 'USD', decimals: 0 };
    };

    FS.slug = function(str) {
        return str.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    };

    FS.csvCell = function(str) {
        return '"' + String(str).replace(/"/g, '""') + '"';
    };

    FS.csvLicense = '\n\n"Source: FAO Fisheries and Aquaculture Statistics (FishStatJ), 2026.1.0"'
        + '\n"License: CC BY 4.0 (https://creativecommons.org/licenses/by/4.0/)"';

    FS.downloadCsv = function(filename, csvContent) {
        var blob = new Blob(['﻿', csvContent], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    /**
     * Wire up the member-state filter: search box + autocomplete dropdown + flag row + reset
     * button + active-country badge. The component owns the "current country" state.
     *
     * Expects these element IDs to be present: countrySearch, countryDropdown, resetBtn,
     * activeBadge, badgeText, badgeClear, memberFlags.
     *
     * @param   {Object} opts
     * @param   {string[]} opts.countryList Full list of selectable country names.
     * @param   {function(string)} opts.onSelect Called with the chosen country name ('' = global).
     * @returns {{ setActiveCountry: function(string), current: function(): string }}
     */
    FS.initCountryFilter = function(opts) {
        var countryList = opts.countryList || [];
        var onSelect = opts.onSelect || function() {};

        var searchInput = document.getElementById('countrySearch');
        var dropdown = document.getElementById('countryDropdown');
        var resetBtn = document.getElementById('resetBtn');
        var activeBadge = document.getElementById('activeBadge');
        var badgeText = document.getElementById('badgeText');
        var badgeClear = document.getElementById('badgeClear');
        var flagContainer = document.getElementById('memberFlags');

        var currentCountry = '';
        var highlightIndex = -1;

        function setActiveCountry(name) {
            currentCountry = name;
            searchInput.value = '';
            closeDropdown();

            if (name) {
                activeBadge.classList.add('visible');
                badgeText.textContent = name;
            } else {
                activeBadge.classList.remove('visible');
            }

            document.querySelectorAll('.flag-btn').forEach(function(btn) {
                var dbName = btn.dataset.dbname || btn.dataset.country;
                btn.classList.toggle('active', dbName === name);
            });
        }

        function getFilteredCountries() {
            var q = searchInput.value.trim().toLowerCase();
            if (!q) return countryList.slice(0, 20);
            return countryList.filter(function(c) {
                return c.toLowerCase().indexOf(q) !== -1;
            });
        }

        function renderDropdown(matches) {
            var query = searchInput.value.trim().toLowerCase();
            dropdown.innerHTML = '';

            if (!matches.length) {
                dropdown.innerHTML = '<div class="country-option" style="color:#999;cursor:default">No matches</div>';
                dropdown.classList.add('open');
                return;
            }

            matches.forEach(function(name, idx) {
                var div = document.createElement('div');
                div.className = 'country-option';
                div.dataset.index = idx;

                if (query) {
                    var lower = name.toLowerCase();
                    var pos = lower.indexOf(query);
                    if (pos >= 0) {
                        div.innerHTML = FS.escapeHtml(name.substring(0, pos))
                            + '<mark>' + FS.escapeHtml(name.substring(pos, pos + query.length)) + '</mark>'
                            + FS.escapeHtml(name.substring(pos + query.length));
                    } else {
                        div.textContent = name;
                    }
                } else {
                    div.textContent = name;
                }

                div.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    onSelect(name);
                });
                dropdown.appendChild(div);
            });

            dropdown.classList.add('open');
            highlightIndex = -1;
        }

        function closeDropdown() {
            dropdown.classList.remove('open');
            highlightIndex = -1;
        }

        function updateHighlight(items) {
            items.forEach(function(el, i) {
                el.classList.toggle('highlighted', i === highlightIndex);
            });
            if (items[highlightIndex]) {
                items[highlightIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        searchInput.addEventListener('input', function() {
            renderDropdown(getFilteredCountries());
        });

        searchInput.addEventListener('focus', function() {
            renderDropdown(getFilteredCountries());
        });

        searchInput.addEventListener('blur', function() {
            setTimeout(closeDropdown, 150);
        });

        searchInput.addEventListener('keydown', function(e) {
            var items = dropdown.querySelectorAll('.country-option[data-index]');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightIndex = Math.min(highlightIndex + 1, items.length - 1);
                updateHighlight(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightIndex = Math.max(highlightIndex - 1, 0);
                updateHighlight(items);
            } else if (e.key === 'Enter' && highlightIndex >= 0 && items[highlightIndex]) {
                e.preventDefault();
                var matches = getFilteredCountries();
                if (matches[highlightIndex]) {
                    onSelect(matches[highlightIndex]);
                }
            } else if (e.key === 'Escape') {
                closeDropdown();
                searchInput.blur();
            }
        });

        resetBtn.addEventListener('click', function() {
            onSelect('');
        });

        badgeClear.addEventListener('click', function() {
            onSelect('');
        });

        // Member flags
        FS.memberCountries.forEach(function(mc) {
            var btn = document.createElement('button');
            btn.className = 'flag-btn';
            btn.type = 'button';
            btn.dataset.country = mc.name;
            if (mc.dbName) btn.dataset.dbname = mc.dbName;
            btn.innerHTML = (FS.flagSvgs[mc.iso2] || '') + '<span class="flag-tooltip">' + FS.escapeHtml(mc.name) + '</span>';

            btn.addEventListener('click', function() {
                var name = mc.dbName || mc.name;
                onSelect(currentCountry === name ? '' : name);
            });

            flagContainer.appendChild(btn);
        });

        return {
            setActiveCountry: setActiveCountry,
            current: function() { return currentCountry; }
        };
    };

})(window.FishStat = window.FishStat || {});
