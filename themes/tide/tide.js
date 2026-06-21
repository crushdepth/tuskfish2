/* tide — progressive enhancement (loaded on every page, deferred).
 *
 * The theme is otherwise no-JS. Three deliberate enhancements live here:
 *
 *  1. Theme toggle. The header button flips the colour scheme and remembers it.
 *     The pre-paint default is set separately in tide-init.js.
 *
 *  2. Filter auto-submit. Auto-submitting a <select> on change has no no-JS
 *     equivalent short of a "Go" button we don't want, so we own that
 *     dependency here rather than smuggling it into an inline onchange attribute
 *     (which an inline-<script> CSP would reject). Any <select data-autosubmit>
 *     submits its form on change; without JS the control simply does nothing.
 *
 *  3. Nav dropdowns. The <details>-based menus work without JS (click to open,
 *     click again to close). This adds the expected niceties: opening one closes
 *     any other, and a click outside — or the Escape key — closes them all.
 */
(function () {
  'use strict';

  // 1. Theme toggle.
  var toggle = document.getElementById('theme-toggle');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var root = document.documentElement;
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('tide-theme', next); } catch (e) {}
    });
  }

  // 2. Filter auto-submit.
  document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
    select.addEventListener('change', function () {
      if (this.form) this.form.submit();
    });
  });

  // 3. Nav dropdowns: single-open, plus close on outside click / Escape.
  var dropdowns = document.querySelectorAll('.nav-dropdown details');
  if (dropdowns.length) {
    dropdowns.forEach(function (details) {
      details.addEventListener('toggle', function () {
        if (!details.open) return;
        dropdowns.forEach(function (other) {
          if (other !== details) other.open = false;
        });
      });
    });

    document.addEventListener('click', function (e) {
      dropdowns.forEach(function (details) {
        if (details.open && !details.contains(e.target)) details.open = false;
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      dropdowns.forEach(function (details) { details.open = false; });
    });
  }
})();
