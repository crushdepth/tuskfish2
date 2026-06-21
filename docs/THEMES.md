# Themes

## CSS-grid theme family

`tide` is a hand-written, non-Bootstrap, CSS-grid theme. `sandsea`, `kelp`,
`canyon` and `pond` are colour-variant siblings generated from it: each is a
full copy of `tide/` in which **only the palette differs**. Concretely, between
`tide` and any sibling:

| File | What differs |
|---|---|
| `style.css` | the palette (`:root` light tokens, the `:root[data-theme="dark"]` block, a few hardcoded colour spots, the shadow tint, and the heading font) — this is the real work |
| `layout.html` | 6 asset-path repoints (`tide/` → `<theme>/`) + the 3 brand-hex meta values |
| `logo.svg` | 3 colour values (wave stroke = accent, border = rule, dot = coral) |
| every other content template | one informational comment line pointing at the theme's own `style.css` |

No PHP logic, output escaping, or markup structure differs anywhere — so an XSS
review of `tide` covers all of them. (A fresh review is only needed if a template
starts echoing a *new* value or changes how an existing one is escaped; pure
palette/asset swaps don't.)

The header and footer markup lives **entirely** in `layout.html`. Nothing else
carries it.

---

## HOWTO: share one customised layout across the CSS-grid themes

*Applies to the CSS-grid theme family only: `tide`, `sandsea`, `kelp`, `canyon`,
`pond`.*

If you have customised the header/footer in one theme's `layout.html` (e.g. on
the production server) and want the same header/footer in the sibling themes,
copy that `layout.html` into each sibling and fix two things:

1. **Asset paths** — the 6 `tide/` references (style.css, tide-init.js, tide.js,
   the `svg-icons.html` include, and two logo `<img>`s) must point at the
   theme's own directory, or the theme will load the source theme's assets.
2. **The 3 brand-hex meta values** (`layout.html` lines ~24–26) — browser-chrome
   / OS-tile colours (mobile address-bar tint, Safari pinned-tab, Windows tile).
   In stock `tide` all three are the teal `#0e7c86`:

   ```html
   <link rel="mask-icon" href="...safari-pinned-tab.svg" color="#0e7c86">
   <meta name="msapplication-TileColor" content="#0e7c86">
   <meta name="theme-color" content="#0e7c86">
   ```

### Per-theme accent hex (replaces `#0e7c86`)

| Theme | accent hex |
|---|---|
| tide | `#0e7c86` |
| sandsea | `#136aa3` |
| kelp | `#7c5a22` |
| canyon | `#a8472b` |
| pond | `#357a58` |

### One command per theme

Run from the `themes/` directory, using your customised `tide/layout.html` as
the source:

```bash
sed -e 's#tide/#kelp/#g'    -e 's/#0e7c86/#7c5a22/g' tide/layout.html > kelp/layout.html
sed -e 's#tide/#sandsea/#g' -e 's/#0e7c86/#136aa3/g' tide/layout.html > sandsea/layout.html
sed -e 's#tide/#canyon/#g'  -e 's/#0e7c86/#a8472b/g' tide/layout.html > canyon/layout.html
sed -e 's#tide/#pond/#g'    -e 's/#0e7c86/#357a58/g' tide/layout.html > pond/layout.html
```

### Two cautions on the `tide/` → `<theme>/` swap

- If your customisation references an asset you only placed in `tide/` (e.g. a
  custom logo or banner image), the swap repoints it to `<theme>/…` where it
  doesn't exist. Copy that asset into each theme dir too, or leave that one path
  as `tide/` if it's genuinely shared.
- If you hardcoded the teal `#0e7c86` anywhere in your custom markup, the second
  `sed` will recolour it to the theme accent — usually what you want, but worth
  knowing.

Nothing else needs touching: each theme's `style.css` palette, `logo.svg`
colours, and content-template comments are already correct.
