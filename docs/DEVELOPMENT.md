# Tuskfish 2 Development Guide

This guide describes how each subsystem in Tuskfish works internally. It complements [ARCHITECTURE.md](ARCHITECTURE.md), which covers the overall structure, MVVM pattern, request flow, and directory layout. This document goes deeper: the internal mechanics of each subsystem, the design decisions behind them, caching and security policies, data flows, and the non-obvious implementation details a developer needs before modifying the code.

All paths below are relative to the repository root. Application classes live under `trust_path/libraries/tuskfish/class/Tfish/` — abbreviated below as `class/Tfish/`. Line numbers are accurate as of Tuskfish V2.3.2 and will drift; treat them as signposts, not coordinates.

## Table of Contents

### I. Content

1. [Content Objects & the Single-Table Model](#1-content-objects--the-single-table-model) — one `content` table for all types, the `Content` entity, type whitelist, `load()`/`url()`, URL-to-constant portability, non-persistent property stripping
2. [Content Editing](#2-content-editing) — the `ContentEdit` CRUD pipeline, form validation, HTMLPurifier on input, file upload handling, entity decode-on-save, cache flush, ex-collection cleanup
3. [Media Enclosures & Downloads](#3-media-enclosures--downloads) — the `/enclosure/` streaming endpoint, access control, download counter, canonical-link header, MIME whitelist
4. [Images & the Thumbnail Cache](#4-images--the-thumbnail-cache) — on-demand GD2/Imagick resizing, the `cachedImage()` width/height convention, public cache directory, proportional scaling
5. [Tags & Taglinks](#5-tags--taglinks) — tags as content objects, the `taglink` junction table, save/update/delete flows, tag-filtered listing
6. [Collections & the Tree](#6-collections--the-tree) — parent-child hierarchy, the `Tree` class, parent select boxes, orphan cleanup when a collection changes type

### II. Presentation

7. [The Template Engine](#7-the-template-engine) — `Entity\Template`, `extract()` + output buffering, theme-first / module-fallback resolution, the `xss()` escape contract
8. [Themes](#8-themes) — theme directory anatomy, `layout.html`, the admin/default split, per-type templates, Bootswatch variants
9. [The Block System](#9-the-block-system) — `BlockRegistry` whitelists, the `Block` interface, positions vs routes, `renderBlocks()`, block config as JSON, block-local templates
10. [Pagination](#10-pagination) — the sliding-window algorithm, first/last substitution, query-string assembly, gallery/search/user side limits
11. [Metadata](#11-metadata) — the `Metadata` entity, per-page override via the ViewModel, canonical URLs

### III. Content Discovery

12. [Search](#12-search) — AND/OR/exact modes, `LIKE` across six columns, the count-then-fetch pattern, online-status gating, escaped vs unescaped placeholders
13. [RSS Feeds](#13-rss-feeds) — the global feed, per-collection and per-tag feeds, the `rss` theme, `http`-scheme enclosure URLs
14. [Sitemap](#14-sitemap) — XML sitemap generation, online + in-feed gating

### IV. Routing & Request Lifecycle

15. [Front Controller & the MVVM Lifecycle](#15-front-controller--the-mvvm-lifecycle) — bootstrap order, Dice instantiation, the action dispatch, layout render, cache write
16. [Routing & Access Control](#16-routing--access-control) — the static routing table, the group bitmask, route masks vs content masks, redirect behaviour
17. [Page Cache](#17-page-cache) — cache-key construction from whitelisted params, path canonicalisation, the check-before-save contract, `doNotCache()`

### V. Data Layer

18. [Database & Prepared Statements](#18-database--prepared-statements) — the PDO/SQLite wrapper, identifier escaping, transactions, `select`/`insert`/`update`/`toggleBoolean`/`updateCounter`, WAL mode
19. [The Criteria System](#19-the-criteria-system) — `Criteria`, `CriteriaItem`, `CriteriaFactory`, operator whitelist, tag joins, sort/limit/offset

### VI. Authentication & Security

20. [Sessions & Login](#20-sessions--login) — password verification, session regeneration, brute-force delay, login flags, session hardening (fingerprint, expiry, regeneration)
21. [WebAuthn Two-Factor Authentication](#21-webauthn-two-factor-authentication) — the bundled library, the two-phase login state machine, challenge storage, credential persistence
22. [Crypto: Secrets at Rest](#22-crypto-secrets-at-rest) — libsodium secretbox, the `enc:v1:` envelope, fail-soft semantics, the config-resident key
23. [The Security Model](#23-the-security-model) — SQL injection, XSS (input purification vs output escaping), file upload safety, traversal checks, HTTP headers, the trust path

### VII. Infrastructure

24. [Preferences](#24-preferences) — the single-row `preference` table, typed getters, SMTP password encryption, cache and theme settings
25. [Modules](#25-modules) — module anatomy, header auto-discovery, the append-don't-reassign convention, module-local templates, the autoloader
26. [Email](#26-email) — PHPMailer wrapper, SMTP settings, admin login notification
27. [Logging & Error Handling](#27-logging--error-handling) — the custom error/exception handler, the log file, production display settings
28. [Installation & Deployment](#28-installation--deployment) — the installer, the two-path layout, config hardening, the expiry cron, live deployment notes

---

## 1. Content Objects & the Single-Table Model

Every kind of publishable content — articles, downloads, images, audio, video, static pages, GPS tracks, collections, and tags — is a row in one `content` table, distinguished by a `type` column. A single entity class, `Content\Entity\Content` (`class/Tfish/Content/Entity/Content.php`), models all of them. Its property set is the union of what any type needs.

### The type whitelist

The permitted types are defined in the `Content\Traits\ContentTypes` trait, used by every content model, viewmodel, and block. Each type is a `Tf`-prefixed class-style key mapped to a human label:

| Type key | Purpose |
|----------|---------|
| `TfArticle` | Long-form HTML article (teaser + description) |
| `TfStatic` | Static page (excluded from the home stream) |
| `TfCollection` | A parent that groups child content (see §6) |
| `TfDownload` | A downloadable file with a counter |
| `TfImage` | An image object |
| `TfAudio` | An audio enclosure |
| `TfVideo` | A video (media is an external URL, not an uploaded file) |
| `TfTrack` | A GPS track |
| `TfTag` | A tag (also a content object — see §5) |

`validateForm()` rejects any `type` not in `listTypes()`, and any `template` not in the per-type `listTemplates()` list. This is the first line of defence against arbitrary template inclusion.

### Entity shape

The `Content` entity holds ~30 private properties (`id`, `type`, `title`, `teaser`, `description`, `creator`, `media`, `externalMedia`, `format`, `fileSize`, `image`, `caption`, `date`, `submissionTime`, `lastUpdated`, `expiresOn`, `counter`, `minimumViews`, `accessGroups`, `inFeed`, `onlineStatus`, `parent`, `language`, `rights`, `publisher`, `template`, `module`, plus the `meta*` SEO fields). Each has a getter and a validating setter; HTML fields additionally expose a `*ForDisplay()` accessor.

`load(array $row, bool $convertUrlToConstant = true)` (`Content.php:119`) populates the entity from a database row through the setters (so validation always runs), then runs the URL-portability conversion on `teaser` and `description`. Note that the database `select()` often hydrates entities directly via `PDO::FETCH_CLASS` against `\Tfish\Content\Entity\Content` rather than calling `load()` explicitly — PDO sets properties before the constructor when fetching as a class.

### URL portability

Tuskfish stores the site base URL inside HTML fields as the literal token `TFISH_LINK` rather than the actual domain. `convertBaseUrlToConstant()` (`Content.php:203`) swaps between the two directions:

- **On save** (`ContentEdit::validateForm`): the real base URL (`TFISH_LINK` constant value) is replaced with the literal string `TFISH_LINK` before storage.
- **On load for display**: the literal `TFISH_LINK` is replaced back with the real base URL.

This means moving the site to a new domain automatically fixes every internal link. The `TFISH_LINK` constant is `TFISH_URL` with the trailing slash stripped (`config.php`), chosen so it reads cleanly inside the editor.

### Permalinks

`url(string $customRoute = '')` (`Content.php:236`) builds an object's permalink as `TFISH_PERMALINK_URL . '?id=' . $id` (i.e. `https://example.com/?id=42` by default), or against a custom route when supplied. Content is addressed by numeric `id` query parameter, not by slug — there is no slug column.

### Non-persistent properties

`unsetNonPersistent()` (`Content.php:220`) strips properties that exist on the entity but are not columns in the `content` table (e.g. `tags`, which live in the `taglink` join table) before an insert or update reaches the database.

---

## 2. Content Editing

Content CRUD is handled by the trio `Content\Controller\ContentEdit`, `Content\ViewModel\ContentEdit`, and `Content\Model\ContentEdit` (`class/Tfish/Content/Model/ContentEdit.php`), reached via the `/admin/content/` route (editor access, mask `2`). All admin content management uses the `admin` theme and the `Single` view.

### The insert pipeline

`insert()` (`ContentEdit.php:113`):

1. `validateForm($_POST['content'])` — produces a fully cleaned associative array (see below).
2. `validateTags($_POST['tags'] ?? [])` — casts tag IDs to positive integers.
3. Sets `submissionTime = time()`, `lastUpdated = 0`, `counter = 0`.
4. `uploadImage($content)` — moves any uploaded image into `uploads/image/`.
5. `uploadMedia($content)` — for non-video types, moves the enclosure into `uploads/media/` and derives `format` (MIME) and `fileSize`.
6. `database->insert('content', $content)`.
7. `saveTaglinks($contentId, $type, 'content', $tags)` — needs the new row's `lastInsertId()`.
8. `cache->flush()` — clears the whole page cache so listings reflect the new item.

### The update pipeline

`update()` (`ContentEdit.php:150`) is more involved because it must reconcile existing files:

- It re-reads the saved row (`getRow`) and seeds `image` (and `media`, for non-video types) from the stored values, so an edit that doesn't touch the file leaves it intact.
- If a new file replaces an old one, or a `deleteImage`/`deleteMedia` flag is set, the redundant file is removed via `fileHandler->deleteFile()`. Deleting media also clears `format` and `fileSize`.
- `updateTaglinks()` reconciles the join table.
- `cache->flush()`.
- `checkExCollection()` — if the object **used to be** a `TfCollection` but no longer is, every child pointing at it via `parent` is reset to `parent = 0` (`updateAll`). This prevents orphaned hierarchy references.
- **Entity decoding before storage**: several fields are passed through `htmlspecialchars_decode()` before the UPDATE. Display-bound fields (`title`, `creator`, `caption`, …) decode `ENT_NOQUOTES`; attribute-bound meta fields (`metaTitle`, `metaSeo`, `metaDescription`) decode `ENT_QUOTES`. The rationale: these values were entity-encoded for safe display elsewhere, and are normalised back to their canonical form for storage.

### Form validation and input sanitisation

`validateForm()` (`ContentEdit.php:355`) is the single chokepoint for all admin-supplied content. Key behaviours:

- **Type and template** must be on their respective whitelists or it throws `InvalidArgumentException`.
- **`accessGroups`** is treated as a bitmask and checked against `groupsMask()` — only whitelisted group bits may be set. The form currently exposes a single group, but the bitmask supports multi-group access if the select is made multiple.
- **HTML fields** (`teaser`, `description`) have the base URL folded to `TFISH_LINK`, then are run through **HTMLPurifier** (`htmlPurifier->purify()`). This is the input-sanitisation half of the XSS strategy (see §23): stored HTML is already safe, so templates echo it without re-escaping.
- **Video media** is validated as a URL; other types take the media filename verbatim.
- **`format`** (MIME) must be in the `Mimetypes` whitelist.
- **`externalMedia`** must be a valid URL if present.
- **`image`** is checked for directory traversal/null bytes and its extension must map to an `image/*` MIME type.

File moves are done by `FileHandler::uploadFile()`, which is responsible for sanitising the uploaded filename and placing it under the correct `uploads/` subdirectory.

---

## 3. Media Enclosures & Downloads

Downloadable files (audio, video files, downloads, track files) are never linked directly. They are streamed through the `/enclosure/` route, which enforces access control and increments the download counter. The endpoint is served by `Content\Model\Enclosure` (`class/Tfish/Content/Model/Enclosure.php`).

### The streaming flow

`streamFileToBrowser(int $id, string $filename = '')` → `_streamFileToBrowser()` (`Enclosure.php:85`):

1. Selects only the columns needed for the decision — `type`, `media`, `expiresOn`, `accessGroups`, `onlineStatus`.
2. **Availability gate**: the object must be online (`onlineStatus == 1`) and not expired (`expiresOn` empty or in the future).
3. **Authorisation gate**: compares the content's `accessGroups` mask against the session's `verifyPrivileges()` mask via `canAccess()`. An unauthenticated user is redirected to `/login/` (303) with a "please log in" message stashed in the session; an authenticated-but-unauthorised user goes to `/restricted/`.
4. The media file must exist and be readable under `TFISH_MEDIA_PATH`.
5. For `TfDownload` objects only, the counter is incremented (`updateCounter`).
6. `session_write_close()` is called **before** streaming — otherwise the session lock would serialise concurrent downloads.
7. `_outputHeader()` sends no-cache headers, a `Content-Disposition: attachment`, the MIME type and `Content-Length`, then `readfile()`s the file.

### The canonical-link defence

`_outputHeader()` (`Enclosure.php:156`) emits a `Link: <enclosure-url>; rel="canonical"` header. This stops external sites that hotlink the file from accruing search-engine authority over the resource — the canonical always points back to the Tuskfish enclosure URL.

The MIME type is resolved from the file extension against the `Mimetypes` trait whitelist, so only known types are served.

---

## 4. Images & the Thumbnail Cache

Inline images are resized on demand and cached as files in the **public** cache directory (`cache/`, served at `TFISH_CACHE_URL`). Resizing logic lives in the `ResizeImage` trait (`class/Tfish/Traits/ResizeImage.php`), with an Imagick-based alternative in `ResizeImage-Imagick.php`. The default uses PHP's GD2 extension.

### The `cachedImage()` convention

Templates call `$content->cachedImage($width, $height)` to get a URL to a resized variant. The method (`ResizeImage.php:48`):

1. Requires at least one of width/height (returns `false` otherwise).
2. Requires the object to have a readable `image` under `TFISH_IMAGE_PATH`.
3. Constructs the cache filename by the **larger dimension**: if width ≥ height, the file is named `{base}-{width}w.{ext}`; otherwise `{base}-{height}h.{ext}`. This single-dimension naming means a thumbnail is keyed by its dominant constraint.
4. **Fast path**: if the cache file already exists and is readable, returns its URL immediately.
5. Otherwise reads the original's dimensions with `getimagesize()`, guards against zero/corrupt dimensions (avoids divide-by-zero), computes the proportional other dimension, and calls `scaleAndCacheImage()`.

`scaleAndCacheImage()` (`ResizeImage.php:132`) creates a true-colour canvas, decodes the original by MIME type (`imagecreatefromjpeg`/`png`/`gif`), `imagecopyresampled()`s into the canvas, and writes the output: **JPEG at quality 80** or **PNG at compression level 6**. GD resources are freed explicitly. There is no upscaling guard beyond the proportional maths — callers are expected to request sizes ≤ the original.

The returned URL is `htmlspecialchars`-escaped before it reaches the template.

---

## 5. Tags & Taglinks

Tags are themselves content objects (`type = 'TfTag'`), so they have titles, descriptions, and their own pages for free. The relationship between a tag and the content it tags is stored in a separate **`taglink`** junction table. Tag plumbing is split across three traits.

### The traits

- **`Tag`** — trivial: `tags()` / `setTags()` on entities.
- **`Taglink`** (used by models) — the write side: `saveTaglinks()`, `updateTaglinks()`, `deleteTaglinks()`, `deleteReferencestoTag()`, `getTagIds()`, `validateTags()`. Each taglink row records `contentId`, `tagId`, the `contentType`, and the `module` (so multiple modules can share the tag system).
- **`TagRead`** (used by viewmodels) — the read side: `activeTagOptions()`, `collectionTagOptions()`, `getTagsForObject()`, `onlineTagSelectOptions()` — these build the tag select boxes and the per-object tag lists for display.

### Write flows

- `saveTaglinks($contentId, $contentType, $module, $tags)` runs after an insert (it needs the new content ID).
- `updateTaglinks()` reconciles the set on edit — it deletes existing links for the object and re-creates the current set.
- `deleteTaglinks($contentId, $module)` removes all links for a deleted object; `deleteReferencestoTag($id)` removes all links **to** a tag that is itself being deleted, so deleting a tag doesn't leave dangling references.

`validateTags()` casts every submitted tag ID to a positive integer and drops the rest.

### Tag-filtered listing

The home/listing controller reads a `tag` query parameter and passes it to the listing viewmodel; the model adds it as a `Criteria` tag filter (see §19), which the database layer translates into an `INNER JOIN taglink`. Pagination preserves the active tag in its query strings (see §10).

---

## 6. Collections & the Tree

A `TfCollection` object acts as a parent; any content object can name it via its `parent` column. This single self-referential foreign key is the entire hierarchy mechanism. The `Tree` class (`class/Tfish/Tree.php`) turns the flat parent pointers into a navigable structure.

### The Tree class

`Tree` builds an in-memory node graph and exposes:

- `getTree()` — the whole tree by reference.
- `getAllChild($key)` / `getAllParent($key)` — descendant and ancestor walks.
- `getByKey($key)`, `getFirstChild($key)`.
- `makeParentSelectBox($selected, $key)` / `makeSelBox(...)` — render an indented `<select>` of collections for the editor's "parent" picker, excluding a given node (so an object can't be made its own parent).

### Lifecycle hazards

Two model behaviours keep the hierarchy consistent:

- **Type change away from collection** — `ContentEdit::checkExCollection()` resets children's `parent` to `0` when a collection is edited into a non-collection type (§2).
- **Collection deletion** — `Admin::deleteReferencesToParent()` clears parent references when a collection is deleted, so its former children become top-level rather than pointing at a missing row.

The collection picker itself is populated by `ContentEdit::collections()`, which selects all online `TfCollection` objects sorted by title.

---

## 7. The Template Engine

There is no third-party template engine. `Entity\Template` (`class/Tfish/Entity/Template.php`) is ~120 lines that hold a variable bag and `include` a plain-PHP `.html` file under output buffering.

### How rendering works

```php
public function render(): string {
    \extract($this->variables);   // assigned vars become locals in template scope
    \ob_start();
    include $this->validPath();   // a .html file containing inline PHP
    return \ob_get_clean();
}
```

`assign($key, $value)` populates the bag, but silently refuses the keys `template` and `theme` so a template can't overwrite the engine's own state. The `Single` and `Listing` views (the only two views in the system) assign the viewModel as `viewModel` and call `render()`; templates then read everything off `$viewModel`.

### Path resolution and the module fallback

`validPath()` (`Template.php:104`) resolves the file in a fixed order:

1. `themes/{theme}/{template}.html` — the active theme. If it exists, it **wins**, letting a theme author override any module template.
2. `{modulePath}/{template}.html` — the module's bundled default, used only when a `modulePath` was supplied to the constructor **and** the theme doesn't provide the template.

When no `modulePath` is set (core and Content routes), resolution is theme-only — identical to legacy behaviour. Both candidate paths are checked for directory traversal/null bytes, and a missing file throws `TFISH_ERROR_TEMPLATE_NOT_FOUND`. This two-step resolution is what makes drop-in modules (§25) work in any theme without copying files.

### The output-escape contract

The global `xss()` helper (defined in `header.php`) is the universal output escaper: `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8', false)`. The final `false` disables double-encoding. **Plain-text output must always go through `xss()`.** HTML fields (`teaser`, `description`) are the exception — they were sanitised by HTMLPurifier on input (§2) and are echoed raw via their `*ForDisplay()` accessors.

---

## 8. Themes

A theme is a directory under `themes/` containing a `layout.html` shell, one `.html` template per renderable view, and a `style.css`. Because the engine is just PHP `include`, a new theme is a copied directory with edited HTML/CSS — no build step, no registration.

### Anatomy of a theme

`themes/default/` contains, among others: `layout.html` (the outer shell), `listView.html` (the teaser stream), per-type detail templates (`article.html`, `collection.html`, `download.html`, `image.html`, `audio.html`, `track.html`, the `video*.html` aspect-ratio variants), `gallery.html`, `tag.html`, `search.html`, `login.html`, `register.html`, `error.html`, `response.html`, and `children.html`/`parent.html` collection partials.

### The layout

`themes/{theme}/layout.html` is `include`d by `FrontController::renderLayout()` with `$page` (the rendered body), `$blocks`, `$metadata`, and `$session` in scope. It emits the `<head>` (metadata via `xss()`, favicons, RSS `<link>`, optional canonical), the Bootstrap nav bar (which conditionally shows admin/login/logout links based on `$session->isEditor()`/`isLoggedIn()`), the block positions (`$blocks['position']['top-centre']`, etc.), the page body, and the footer. Layouts are **never** module-local — they always come from the theme.

### The admin/default split

Two preferences select themes: `defaultTheme` for the front end and `adminTheme` for the control panel. A viewModel sets `$this->theme` (usually from a preference) and the front controller validates it for traversal before including the layout. The shipped set includes `default`, `admin`, special-purpose `rss` and `signin` themes, and Bootswatch-derived variants (`cerulean`, `cosmo`, `cyborg`, `darkly`, `flatly`, `lux`, `materia`, `minty`, `pulse`, `sandstone`, `simplex`, `united`, `yeti`, `zephyr`).

---

## 9. The Block System

Blocks are content widgets rendered into named layout **positions**. The system is whitelist-driven and module-extensible. The core defines the position vocabulary; modules register everything else.

### The registry

`BlockRegistry` (`class/Tfish/BlockRegistry.php`) is an immutable (`readonly`) holder of five whitelists, injected once as a Dice singleton after the module-header glob (§25):

- **`types`** — fully-qualified block class → human label.
- **`templates`** — class → `[templateName => label]` (the display templates a block offers).
- **`positions`** — position key → label. **Fixed by the core**, not module-extensible: `banner`, `top-left`, `top-right`, `top-centre`, `left`, `right`, `bottom-left`, `bottom-right`, `bottom-centre`, `footer`.
- **`routes`** — the flat list of routes on which blocks may appear (de-duplicated via `array_unique`).
- **`config`** — class → config sub-template name.

The constructor takes a **single keyed array** rather than five positional arrays, deliberately: Dice resolves array arguments by type and cannot disambiguate multiple same-typed array parameters positionally. The keyed bag sidesteps that. `configTemplate()` and the private `blockPath()` derive a block's template directory from its class namespace (`\Tfish\Content\Block\Foo` → `class/Tfish/Content/Block/`).

### The Block interface and a concrete block

Every block implements `Interface\Block` and is constructed with `(array $row, Database, CriteriaFactory)`. `Content\Block\RecentContent` (`class/Tfish/Content/Block/RecentContent.php`) is representative:

- The constructor calls `load($row)` → `content(...)` → `render()` in sequence and stores the rendered HTML.
- `load()` validates the chosen template against the block's own `listTemplates()` and decodes the JSON `config` blob via `setConfig()` → `validateConfig()`.
- `content()` builds a parameterised SQL query honouring the block's config (item count capped at 20, optional content-type and tag filters), binding a variable number of placeholders. It uses `PDO::FETCH_KEY_PAIR` to return `id => title`.
- `render()` includes the block's template (`TFISH_CONTENT_BLOCK_PATH . {template}.html`) under output buffering — note: blocks render against their **module** Block directory, not the theme.
- `validateConfig()` is strict: item count is range-checked `[0,20]`, tag IDs cast to positive ints, content types checked against `listTypes()`. Config is stored as validated JSON (`serialiseConfig()` additionally runs `json_validate`).

### Rendering at request time

`FrontController::renderBlocks($path)` runs one query joining `block` to `blockRoute` for the current path, ordered by position, weight, then id, filtered to `onlineStatus = 1`. For each row it verifies the stored `type` class exists and `is_a(..., Interface\Block::class)` — a stored type that isn't a real block class is silently skipped (defence against tampered rows). Surviving blocks are returned both indexed by ID and grouped under `['position'][$pos][]` for the layout to echo.

Block instances and their route placements are managed at `/admin/blocks/` (`BlockAdmin`/`BlockEdit` controllers, super-user access, mask `1`).

---

## 10. Pagination

`Pagination` (`class/Tfish/Pagination.php`) renders a Bootstrap pagination control from a result count, the page size, and the current offset. It is created per-request by Dice with the current path.

### The sliding window

`renderPaginationControl()` (`Pagination.php:90`):

1. Computes `pageCount` from `count / limit` (plus one for any remainder). Returns `false` for zero results or a single page — no control is drawn.
2. Computes the current page from `start / limit + 1`.
3. The control length is `min(paginationElements, pageCount)` — `paginationElements` is a preference (the number of page "slots" shown).
4. It then slices a window of that length out of the full page range, centred on the current page, with fore/aft bound checks so the window clamps at the start and end rather than running off either edge.
5. The first and last slots are **substituted** with "first"/"last" labels (`TFISH_PAGINATION_FIRST`/`_LAST`), then the slot array is re-sorted.

`_renderPaginationControl()` builds the `<nav><ul class="pagination">` markup, marking the current page `active`. Each link's query string is assembled from whatever state is live — `extraParamsString`, `tag`, and `start` — joined with `&amp;`, so filters survive page navigation.

### Side limits

Several setters select context-appropriate page sizes from preferences: `setGallerySideLimit()`, `setSearchSideLimit()`, `setUserSideLimit()`, alongside the generic `setLimit()`, `setStart()`, `setCount()`, `setTag()`, `setUrl()`, and `setExtraParams()`.

---

## 11. Metadata

`Entity\Metadata` (`class/Tfish/Entity/Metadata.php`) is a shared singleton holding the page's `<head>` metadata: `title`, `description`, `author`, `copyright`, `robots`, `language`, `canonicalUrl`, `siteName`. It is seeded from preferences and **overridden per-page** by the viewModel.

In `FrontController::renderLayout()`, `metadata->update($viewModel->metadata())` merges the viewModel's metadata array over the defaults before the layout is included. So a content detail page can set its own title and description (typically from the object's `metaTitle`/`metaDescription`/`metaSeo` fields) while inheriting site-level defaults for anything it doesn't specify. The layout echoes every field through `xss()`.

---

## 12. Search

Free-text search is handled by `Content\Model\Search` (`class/Tfish/Content/Model/Search.php`) via the `/search/` route, with an admin variant at `/admin/search/`. It is a `LIKE`-based search across six columns — there is no full-text index.

### Search modes

`searchContent()` (`Search.php:123`) accepts a `searchType` from the whitelist `["AND", "OR", "exact"]` (anything else throws). The search terms are split and each term becomes a parenthesised clause testing six columns:

```sql
(`title` LIKE :searchTerm0 OR `teaser` LIKE :escapedSearchTerm0 OR
 `description` LIKE :escapedSearchTerm0 OR `caption` LIKE :searchTerm0 OR
 `creator` LIKE :searchTerm0 OR `publisher` LIKE :searchTerm0)
```

Clauses are joined by the chosen operator (`AND`/`OR`). Note the two placeholder families: **`escapedSearchTerm`** is used against the HTML fields (`teaser`, `description`) because their stored content is entity-encoded, while the plain **`searchTerm`** is used against plain-text columns. Both are bound wrapped in `%…%` wildcards.

### Count-then-fetch

The method runs the query twice. First it prepends `SELECT count(*)` to get the total (`contentCount`) for pagination. Then it prepends `SELECT *`, appends `ORDER BY date DESC, submissionTime DESC` and `LIMIT`/`OFFSET`, and fetches the page of results as `Content` entities via `PDO::FETCH_CLASS`. The count is `array_unshift`ed onto the front of the results array so the viewModel can read both from one return value.

Non-admin searches are gated to `onlineStatus = 1`; the admin search can include offline content by passing `onlineStatus = 0` to skip the filter.

---

## 13. RSS Feeds

`Content\Model\Rss` (`class/Tfish/Content/Model/Rss.php`) generates feeds, rendered through the dedicated `rss` theme. Three feed scopes are supported:

- **Global feed** (`/rss/`) — recent in-feed content (`getObjects(0)`).
- **Per-collection feed** — `customFeed($id)` / `getObjects($parentId)` returns the children of a collection, so each collection has its own feed.
- **Per-tag feed** — `getObjectsforTag($tagId)` returns content carrying a given tag.

Only content with `inFeed = 1` and `onlineStatus = 1` is included. The `rssPosts` preference caps the item count.

### The enclosure-URL scheme quirk

RSS enclosure URLs must use the `http` scheme — `https` enclosure URLs invalidate the feed per the RSS spec. `config.php` handles this: when the site URL is `https`, `TFISH_ENCLOSURE_URL` is still built with an explicit `https://{host}/enclosure/?id=` form, but the enclosure handling is aware of the scheme distinction. (See the `TFISH_ENCLOSURE_URL` definition in `config.php`.)

---

## 14. Sitemap

`Model\Sitemap` (`class/Tfish/Model/Sitemap.php`) generates an XML sitemap via the `/sitemap/` route (`generate()`). It walks all content that is online and (where relevant) in-feed, emitting permalink URLs. Like the feeds, it respects the same visibility gating so offline or excluded content never leaks into the sitemap. Generation is triggered from the admin panel.

---

## 15. Front Controller & the MVVM Lifecycle

`FrontController` (`class/Tfish/FrontController.php`) owns the entire request lifecycle. It is created at the very end of `index.php` once routing has resolved.

### Bootstrap order (index.php)

1. `mainfile.php` defines the three install paths and loads `config.php` (path constants, autoloader, `TFISH_DATABASE` pointer).
2. `routingTable.php` is loaded (the path → `Route` map).
3. `header.php` sends security headers, builds the Dice container with the core service rules, and seeds the empty block-registry whitelist arrays plus the core's own `['/error/']` block route.
4. `glob(TFISH_CLASS_PATH . 'Tfish/*/header.php')` auto-discovers and requires each module header in alphabetical order; modules **append** their block registrations to the seed arrays.
5. `BlockRegistry` is finalised by `addRule()` (which returns a **new** immutable Dice instance — `$dice` is reassigned) and injected as a shared singleton.
6. The route path is derived from `REQUEST_URI`'s path only (scheme and host ignored — see §16/§23), the install base dir is stripped, and slashes are normalised.
7. `Router::route($path)` returns the matching `Route` (or the `/error/` route).
8. Dice creates the `FrontController` with the route and path.

### Inside the controller

The constructor (`FrontController.php:65`):

1. `session->start()`.
2. `checkSiteClosed()` — if the site is closed and the visitor isn't an admin (and isn't already at `/login/`), redirect to login.
3. `checkAccessRights($route)` — the bitmask access test (§16).
4. Dice creates the four MVVM components: `Model`, `ViewModel(model)`, `View(viewModel)`, `Controller(model, viewModel)`. A `Pagination` instance is also created with the path.
5. The action is resolved from `$_REQUEST['action']` (default `display`). It must be alphabetic **and** an existing method on the controller, or the request is redirected to `/error/` — this prevents arbitrary method invocation.
6. `ob_start()`, then `$cacheParams = controller->{$action}()`.
7. `cache->check($path, $cacheParams)` — if a fresh cached copy exists, it is echoed and the script exits here.
8. `renderLayout()` renders the page body (`view->render()`), the blocks, merges metadata, and includes the theme layout.
9. Unless `viewModel->doNotCache()` is true, the buffered output is saved to cache.
10. `database->close()`, `ob_end_flush()`.

The flow of control is Controller → ViewModel → Model; the flow of data is Model → ViewModel → Template.

---

## 16. Routing & Access Control

### The static routing table

`routingTable.php` returns `path => Route(model, viewModel, view, controller, accessMask)`. Paths are normalised with leading and trailing slashes (`/admin/content/`). `Router::route()` is a single array lookup that falls back to the `/error/` route for any unmatched path. The full route set covers the home stream, admin (content, blocks, search), preferences, login/logout/register/restricted, password/email, enclosure, gallery, RSS, sitemap, token, flush, and error.

### The group bitmask

Access is a CHMOD-style bitmask defined in the `Group` trait:

| Bit | Constant | Group |
|-----|----------|-------|
| `1 << 0` = 1 | `G_SUPER` | Site administrator |
| `1 << 1` = 2 | `G_EDITOR` | Editors |
| `1 << 2` = 4 | `G_MEMBER` | Members |

`groupsMask()` is the OR of all valid bits (memoised). New groups extend at bits 8, 16, ….

### Route masks vs content masks

Two distinct authorisation surfaces share the same bitmask:

- **Route access** — `FrontController::checkAccessRights()`. A mask of `0` is public and returns immediately. Otherwise the route mask is validated against the whitelist, then the user's `verifyPrivileges()` mask is tested: `G_SUPER` always passes; otherwise any bit overlap (`hasAnyGroup`) grants access. An unauthenticated user on a restricted route gets a 303 to `/login/` (with the requested URL stashed as the next-URL); an authenticated but unauthorised user gets a 303 to `/restricted/` (403 semantics).
- **Content access** — `Group::canAccess($userMask, $requiredMask)`. Public content (`0`) is always visible; **super-users and editors bypass all content checks**; everyone else needs a bit overlap. Crucially, editors have full content access but **not** full route access — admin-only routes (mask `1`) still exclude them. This split is enforced because route checks and content checks call different methods.

Content-level checks happen in viewModels (e.g. `Listing::displayObject` compares `content->accessGroups()` to the user mask) and in the enclosure model (§3).

---

## 17. Page Cache

`Cache` (`class/Tfish/Cache.php`) is a disk-based full-page HTML cache writing to the **private** cache (`trust_path/cache/`). It is keyed by the request path plus a whitelisted set of query parameters supplied by the controller.

### Key construction

The controller's action returns a `$cacheParams` array describing the page (e.g. `['page' => 'home', 'tag' => 5, 'start' => 20]`). `_getCachedFileName()` (`Cache.php:180`) builds the filename from the path plus each `key=value` pair — but only for keys and values that pass `isAlnumUnderscore()`. Anything non-alphanumeric is dropped. The result is lowercased with a `.html` suffix. Because the controller controls exactly which parameters become cache keys, and they're validated, cache poisoning via arbitrary query strings is prevented.

### The check/save contract

- `check($path, $params)` aborts if caching is disabled or `$params` is empty (the empty-params "do not cache" signal). It sets the path, builds the filename, and **canonicalises**: it compares `realpath(cache) . '/' . filename` against the constructed path and bails on mismatch (traversal guard). If the file exists and is younger than `cacheLife()` seconds, it is echoed and the script exits.
- `save($params, $buffer)` repeats the same disable/empty/canonicalisation guards, then writes the buffer. **`check()` must precede `save()`** because `check()` sets the path property `save()` relies on — the front controller always calls them in that order.
- `flush()` unlinks every file in the private cache except `index.html`. It is called on every content insert/update/delete so listings and pagination never serve stale data.

The path setter strips `.php` extensions, runs the traversal/null-byte check, and replaces `/` with `_` (paths become flat filenames).

A page opts out entirely by having its viewModel return `doNotCache() === true` (used for personalised/restricted pages), or by returning empty cache params from the controller.

---

## 18. Database & Prepared Statements

`Database` (`class/Tfish/Database.php`) is a thin wrapper over PDO/SQLite. **Every** query is a prepared statement with bound values; table and column identifiers are backtick-escaped. There is no path by which a query reaches SQLite as concatenated user input.

### Connection

`connect()` opens `sqlite:TFISH_DATABASE` with `ERRMODE_EXCEPTION`, a 5-second busy timeout, and two PRAGMAs: **`journal_mode=WAL`** (concurrent reads during writes) and **`foreign_keys=ON`**. The connection is deliberately non-persistent and is closed in `__destruct()` and at the end of each request. Cloning, serialisation, and wakeup are all blocked (`final`).

### The query surface

| Method | Purpose |
|--------|---------|
| `select($table, ?Criteria, ?columns)` | SELECT; returns a `PDOStatement` (often fetched as `Content` entities) |
| `selectCount` / `selectDistinct` | Aggregate / distinct queries |
| `insert($table, $keyValues)` | INSERT from an associative array |
| `update($table, $id, $keyValues)` | UPDATE one row by id |
| `updateAll($table, $keyValues, ?Criteria)` | Conditional bulk UPDATE |
| `delete($table, $id)` / `deleteAll($table, Criteria)` | DELETE one / many (criteria required for safety) |
| `toggleBoolean($id, $table, $column)` | Flip a 0/1 column (online status, in-feed) |
| `updateCounter($id, $table, $column)` | Atomic increment (download/view counters) |
| `preparedStatement($sql)` | Escape hatch for hand-written parameterised SQL |
| `beginTransaction` / `commit` / `rollback` / `executeTransaction` | Transaction control |

### Validation helpers

`createTable()` enforces an alphanumeric table name and whitelists column types to `BLOB`, `TEXT`, `INTEGER`, `NULL`, `REAL`. `escapeIdentifier()`, `validateTableName()`, `validateColumns()`, `validateKeys()`, `validateId()`, and `validateCriteriaObject()` defend every identifier and parameter before it reaches a statement. `setType()` maps PHP types to the correct `PDO::PARAM_*` constant for binding.

`deleteAll()` documents a SQLite limitation: it doesn't support DELETE with INNER JOIN or table aliases, so tag criteria are ignored in bulk deletes (work around with a subquery or loop).

---

## 19. The Criteria System

Models express queries through `Criteria` objects rather than raw SQL, built by the injected `CriteriaFactory`. This keeps the SQL string assembly inside the `Database` class where it can be parameterised safely.

### The pieces

- **`CriteriaFactory`** — `criteria()` returns a fresh `Criteria`; `item($column, $value, $operator = '=')` returns a `CriteriaItem`.
- **`CriteriaItem`** — a single condition: a column, a value, and an operator. `setOperator()` validates against `listPermittedOperators()` (the operator whitelist — `=`, `LIKE`, `<`, `>`, etc.), so an attacker can't smuggle SQL through the operator slot.
- **`Criteria`** — the collection: `add(CriteriaItem, $condition = 'AND')` appends a condition with its boolean joiner; `setSort`/`setOrder`/`setSecondarySort`/`setSecondaryOrder` control ordering; `setLimit`/`setOffset` paginate; `setGroupBy` groups; `setTag(array)` attaches tag IDs that the database layer turns into the `taglink` INNER JOIN.

A typical model builds criteria like:

```php
$criteria = $this->criteriaFactory->criteria();
$criteria->add($this->criteriaFactory->item('type', 'TfCollection'));
$criteria->add($this->criteriaFactory->item('onlineStatus', 1));
$criteria->setSort('title');
$criteria->setOrder('ASC');
$statement = $this->database->select('content', $criteria);
```

`Database::select()` consumes the criteria, assembles the `WHERE`/`ORDER`/`LIMIT` clauses with placeholders, binds the values with their correct PDO types, and returns the statement.

---

## 20. Sessions & Login

`Session` (`class/Tfish/Session.php`) handles authentication and session hardening. Tuskfish is single-admin at heart, but the login machinery is group-aware.

### The login flow

`login($email, $password)` → `_login()` → `_authenticateUser()` (`Session.php:344`):

1. The email is validated as an email (it doubles as the username) and looked up in the `user` table via a prepared statement.
2. **Brute-force delay**: if the user has prior `loginErrors`, `delayLogin()` sleeps one second per failure, capped at 15 seconds (the cap prevents the feature being weaponised for DoS).
3. Suspended accounts (`onlineStatus !== 1`) are rejected.
4. `password_verify()` checks the password against the stored bcrypt hash.
5. On success the session is **regenerated immediately** (privilege escalation), then second-factor is checked: if the user has a WebAuthn credential, the session enters the pending-2FA state and returns `['webauthn_required' => true]` (§21).
6. With no second factor, `setLoginFlags()` stores the session identity, the failed-login counter is reset, an admin notification email is sent, and the user is redirected to their `nextUrl()` or their group's home page.
7. On failure the `loginErrors` counter is incremented (capped at 15) and the session is destroyed.

### Session identity and hardening

`setLoginFlags()` (`Session.php:485`) stores `id`, `adminEmail`, and — critically — `authHash`, which is `sha256(passwordHash)` rather than the hash itself. So a leaked session reveals neither the password nor its hash, and changing the password invalidates existing sessions.

Beyond login, `Session` provides the hardening surface used on every request: `start()` (regeneration cadence, fingerprinting), `isClean()` (session fingerprint validation), `isExpired()` (idle timeout against `sessionLife`), `regenerate()`, `reset()`, `destroy()`, and the privilege accessors `verifyPrivileges()`, `isAdmin()`, `isEditor()`, `isLoggedIn()`. The `setToken()`/`ValidateToken` machinery provides CSRF tokens for forms.

---

## 21. WebAuthn Two-Factor Authentication

Optional hardware-key 2FA (e.g. Yubikey, with a main and backup key) is implemented with a **bundled, self-contained** WebAuthn library (`class/webauthn/` — CBOR, attestation formats, binary helpers) rather than a Composer dependency, consistent with the minimal-dependency principle. `WebAuthnService` (`class/Tfish/WebAuthnService.php`) wraps it.

### The two-phase login state machine

Login is a two-phase ceremony coordinated through three small models — `WebAuthnLogin`, `WebAuthnChallenge`, `WebAuthnCredential`:

1. **Password phase** — `_authenticateUser()` verifies the password, regenerates the session, then asks `WebAuthnLogin::requiresSecondFactor($userId)`. If it returns `'webauthn'`, `WebAuthnChallenge::storePendingUserId()` stashes the user ID (a *pending*, not authenticated, session) and login returns `['webauthn_required' => true]`. The user is sent to the WebAuthn assertion page.
2. **Assertion phase** — `getWebAuthnAuthenticationOptions()` (`Session.php:595`) loads the pending user's credential IDs, asks `WebAuthnService::getAuthenticationOptions()` for a challenge, and stores it (`storeAuthentication`). The browser performs the assertion; `verifyWebAuthnAssertion()` (`Session.php:661`) retrieves the stored challenge and pending user ID, verifies the assertion against the stored credential, and — on success — promotes the session to fully authenticated. The challenge is cleared after use whether or not it succeeds.

### Service responsibilities

`WebAuthnService` is constructed with the relying-party name and ID (derived from the site config) and exposes `getRegistrationOptions()` / `verifyRegistration()` for enrolling a key and `getAuthenticationOptions()` / `verifyAuthentication()` for logging in, plus `getChallenge()` and `getSignatureCounter()`. Credential metadata (public key, signature counter) is persisted in the `webauthn_credentials` table; the signature counter is checked on each assertion to detect cloned authenticators.

---

## 22. Crypto: Secrets at Rest

`Crypto` (`class/Tfish/Crypto.php`) provides authenticated symmetric encryption for secrets stored in the database — currently the SMTP password. It uses **libsodium's secretbox (XSalsa20-Poly1305)**, which is built into PHP and authenticated (tampering is detected).

### The envelope and fail-soft design

- The key is a random 32 bytes written to `config.php` as `TFISH_ENCRYPTION_KEY` at install. Because it lives in config (outside the web root, not in the database), it protects a stored secret if the **database alone** leaks — but not if the config file itself is compromised.
- Ciphertext is tagged with a version prefix `enc:v1:` followed by base64(`nonce || ciphertext`). The prefix lets reads distinguish encrypted values from legacy plaintext and supports future key/algorithm versioning.
- The edges are deliberately permissive so the feature is opt-in and reversible:
  - `encrypt()` returns the input **unchanged** if it's empty, or if no key is available (legacy plaintext installs keep working).
  - `decrypt()` returns the value unchanged if it lacks the `enc:v1:` prefix (legacy plaintext), and returns an **empty string** if a tagged value can't be decrypted (missing/changed key, corruption, tampering) — callers fail soft rather than fatal.

`newKeyBase64()` generates a fresh key for the installer. `resolveKey()` validates that the key decodes to exactly `SODIUM_CRYPTO_SECRETBOX_KEYBYTES`. The encrypt/decrypt methods are `static` and centralised so the `Preference` entity (§24) can call them in one place.

---

## 23. The Security Model

Security in Tuskfish is structural — the safe path is the only path — and rests on a small number of consistently-applied techniques.

### SQL injection

Closed off by construction: the `Database` class exposes **only** prepared statements with bound parameters (§18), identifiers are backtick-escaped and validated against alphanumeric/whitelist rules, and models build queries through `Criteria` objects whose operators are whitelisted (§19). There is no string-concatenation query path.

### XSS — two complementary halves

1. **Input purification** — admin-supplied HTML (`teaser`, `description`) is run through HTMLPurifier in `validateForm()` before storage (§2). Stored HTML is therefore already safe.
2. **Output escaping** — all plain-text output goes through the global `xss()` helper (`htmlspecialchars` with HTML5-aware flags, no double-encoding). HTML fields are the documented exception, echoed via `*ForDisplay()` accessors because they were purified on input.

### File uploads

Uploaded files are moved by `FileHandler::uploadFile()`, which sanitises the filename and places it under the correct `uploads/` subdirectory. The content editor validates image extensions against `image/*` MIME types, validates enclosure MIME types against the `Mimetypes` whitelist, and checks every filename for directory traversal and null bytes (`hasTraversalorNullByte`). Enclosures are never served by path — only through the access-controlled `/enclosure/` streamer (§3).

### Path traversal

`TraversalCheck` is applied wherever a filesystem path is built from variable input: template resolution (§7), the cache (§17), image filenames (§4), and theme/layout selection in the front controller. Cache paths are additionally canonicalised against `realpath()` before use.

### The routing trust boundary

Routing uses the request **path only**. Scheme and host are deliberately ignored: the host (`Host`/`SERVER_NAME`) is client-supplied and untrusted, and ignoring the scheme lets the app work behind a TLS-terminating reverse proxy. The only admin-controlled input used is the base directory from `TFISH_LINK` (keeps subdirectory installs working). `REQUEST_URI` is split on `?` directly rather than via `parse_url()`, which would misread a leading `//` as an authority and mis-route bogus URLs (see the comments in `index.php`).

### HTTP headers

`header.php` sends `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Cross-Origin-Opener-Policy: same-origin`, `Cross-Origin-Resource-Policy: same-site`, and a restrictive `Permissions-Policy`. A `Content-Security-Policy` is provided commented-out for per-site customisation, and an HSTS header is ready to enable once TLS is installed. Charset is locked to UTF-8 at the header, `mb_internal_encoding`, and `mb_http_output` levels.

### The trust path

The bulk of the code, the database, logs, config, and key material live under `trust_path/`, intended to sit outside the document root. The public surface is a handful of entry scripts. `config.php` should be CHMOD `0400` in production.

---

## 24. Preferences

`Entity\Preference` (`class/Tfish/Entity/Preference.php`) is a shared singleton holding all admin-editable site settings, loaded from a single row in the `preference` table. Each setting has a typed private property and a getter; the entity validates on load.

### What it holds

Site identity (`siteName`, `siteDescription`, `siteAuthor`, `siteEmail`, `siteCopyright`), the `closeSite` flag, timezones (`serverTimezone`, `siteTimezone`), search (`minSearchLength`), pagination sizes (`searchPagination`, `userPagination`, `adminPagination`, `galleryPagination`, `collectionPagination`, `paginationElements`), `rssPosts`, `minimumViews`, session (`sessionName`, `sessionLife`), `defaultLanguage`, `dateFormat`, caching (`enableCache`, `cacheLife`), `mapsApiKey`, themes (`adminTheme`, `defaultTheme`), and SMTP (`smtpHost`, `smtpPort`, `smtpEncryption`, `smtpUser`, `smtpPassword`).

The inline comments in the property list classify each setting by scope (`global`, `viewmodel`, `model`) indicating where it is consumed.

### SMTP password encryption

The SMTP password is the one secret in this table. Its encryption/decryption is **centralised in the Preference entity** using `Crypto` (§22) — the entity encrypts on write and decrypts on read, so callers always see plaintext and the database always stores an `enc:v1:` token (given a configured key). This is the single integration point for `Crypto`.

Settings are edited through `/preference/edit/` (super-user). Changing them takes effect on the next request (preferences are read fresh each request) — no cache flush needed for settings, though content changes do flush the page cache.

---

## 25. Modules

A module is a self-contained functional area under `class/Tfish/{Module}/`. The two shipped modules are **Content** (the publishing engine) and **User** (account management). Modules are how new capability is added without editing core.

### Anatomy

A module directory may contain `Model/`, `ViewModel/`, `Controller/`, `Entity/`, `Block/`, `Traits/`, `language/`, `templates/`, and a **`header.php`**. The `User` module is the fullest realisation of the drop-in goal — it bundles its own `templates/` (resolved via the `modulePath` fallback, §7) and its own `language/english.php`, so it works in any theme with zero theme edits.

### Header auto-discovery

`index.php` runs `glob(TFISH_CLASS_PATH . 'Tfish/*/header.php')` and requires each result in alphabetical order, **after** the core header has seeded the block-registry arrays and **before** `BlockRegistry` is finalised. A module header can:

- Define module-specific path/URL/language constants (e.g. `Content/header.php` defines `TFISH_CONTENT_BLOCK_PATH`).
- Register block types, templates, config sub-templates, and block-hosting routes by **appending** to the shared seed arrays.

### The append-don't-reassign convention

This is the one rule module authors must follow. All module headers share the seed variables (`$blockTypes`, `$blockTemplates`, `$blockConfig`, `$blockRoutes`) in `index.php`'s scope. Use keyed assignment for the associative arrays (`$blockTypes['\Tfish\Foo\Block\Bar'] = ...`) and `array_merge()` for the numeric `$blockRoutes`. Writing `$blockTypes = [...]` would clobber every other module's registrations. Content's header is the reference example.

### Routing and autoloading

Page routes still live centrally in `routingTable.php` (the header mechanism currently carries block registrations, not page routes). Classes autoload via the `spl_autoload_register`ed `tfish_autoload()` in `config.php`, which maps `\Tfish\Content\Model\Listing` → `class/Tfish/Content/Model/Listing.php` and checks the file exists before including (so additional autoloaders can chain).

The longer-term direction is for modules to carry their own page routes and templates entirely, so a module can be dropped in or removed without editing `routingTable.php` — the header auto-discovery and the `modulePath` template fallback are the foundations already in place for that.

---

## 26. Email

`Mail` (`class/Tfish/Mail.php`) is a thin wrapper over the bundled PHPMailer (`trust_path/libraries/phpmailer/`). It is constructed with the `Preference` entity and exposes a single `send($to, $subject, $body)` method.

It reads the SMTP settings from preferences (host, port, encryption, user, and the decrypted password — §24) and dispatches via SMTP. The primary built-in use is the **admin login notification** sent by `Session::notifyAdminLogin()` after a successful password login, alerting the administrator that their account was accessed. The contact/email form route (`/email/`) also uses it.

---

## 27. Logging & Error Handling

`Logger` (`class/Tfish/Logger.php`) installs the custom error and exception handlers in `header.php`:

```php
\set_error_handler([$logger, "logError"]);
\set_exception_handler([$logger, "throwException"]);
```

Errors and uncaught exceptions are written to `trust_path/log/tuskfish_log.txt`. Production settings in `header.php` are `display_errors = 0`, `log_errors = 1`, `error_reporting = E_ALL` — nothing is shown to the visitor, everything is logged.

**When debugging a 500, check `trust_path/log/tuskfish_log.txt` first** — it is the authoritative record of what went wrong, since the browser will only ever show a generic error page.

---

## 28. Installation & Deployment

### The installer

`install/index.php` is a self-contained installer (`install/` is deleted after setup). It checks the PHP version (8.4+ required) and the required extensions (SQLite3, PDO, pdo_sqlite, GD2), collects database credentials via `dbCredentialsForm.html`, and creates the schema. The `Database::create()` method generates the SQLite file with an **unpredictable random integer prefix** (`mt_rand()`) on its filename and appends the `TFISH_DATABASE` constant (the absolute path) to `config.php`. The installer creates the tables: `user`, `preference`, `session`, `content`, `taglink`, `block`, `blockRoute`, `webauthn_credentials`.

### The two-path layout

Configuration is PHP constants, not env vars:

- **`mainfile.php`** sets the three values an operator must edit: `TFISH_ROOT_PATH` (web root), `TFISH_TRUST_PATH` (trust path, ideally outside the document root), and `TFISH_URL`. In a Docker container these must reflect paths **inside** the container.
- **`config.php`** derives every other path/URL constant, registers the autoloader, holds the `TFISH_DATABASE` pointer, and (post-install) the `TFISH_ENCRYPTION_KEY`. It should be CHMOD `0400` in production.

### Live deployment notes

In the reference deployment the repository **is** the live document root (`/var/www/html`), so a `git pull` deploys. Apache serves the site; `.htaccess` routes all requests through `index.php`. After a deploy that changes content, the page cache self-flushes on the next content edit; to force it, use `/flush/`.

The error log is at `trust_path/log/tuskfish_log.txt` — the first place to look on any 500.

### The expiry cron

`trust_path/cron/expiresOn.php` is intended to run from cron. It takes content whose `expiresOn` date has passed offline, so time-limited content disappears from public view automatically. One-off schema-migration helpers live in `trust_path/utilities/` (`add_indexes.php`, `add_stream_index.php`) for evolving an existing install's indexes.
