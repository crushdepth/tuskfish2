# Tuskfish 2 Architecture Guide

This document is for developers and site maintainers. It explains how Tuskfish is structured, how the pieces connect, and where to find things. Read this before diving into individual files.

## What Tuskfish does

Tuskfish is a **single-user micro CMS**. One administrator publishes a mixed stream of content — articles, downloads, images, audio, video, static pages, GPS tracks and collections — through a single editing form. Visitors browse that stream, filter it by tag or type, read individual items, download enclosures, and subscribe via RSS. Optional editor and member roles extend this slightly, but the design centre of gravity is one person publishing content with the least possible ceremony.

The project's governing principle is **minimalism**: the smallest, most lightweight code base that does the job. A small code base is easier to understand, easier to keep secure, and easier to carry forward as PHP evolves. External libraries are avoided wherever a built-in will do, to reduce attack surface and maintenance overhead.

The core data flow is: the admin logs in, creates a content object (choosing its type), optionally attaches a media file and image, tags it, and sets it online. The front controller routes each visitor request to a set of MVVM components that pull the matching content from SQLite, render it through a theme template, wrap it in a layout, and (optionally) cache the resulting HTML to disk.

## Tech stack

| Layer | Technology | Role |
|-------|-----------|------|
| Language | PHP 8.4+ (8.5-ready) | All server-side code, strict types throughout |
| Database | SQLite 3 (via PDO + pdo_sqlite) | Single-file database, WAL journal, foreign keys on |
| Templates | Native PHP templates (`.html` files) | No template engine — plain PHP `include` with output buffering |
| DI container | [Dice](https://github.com/Level-2/Dice) | Lightweight dependency injection |
| Image processing | GD2 (or ImageMagick 6, optional) | Thumbnail generation |
| HTML sanitisation | HTMLPurifier | Filters admin-supplied HTML input |
| Mail | PHPMailer | SMTP notifications (login alerts, contact form) |
| 2FA | Self-contained WebAuthn implementation | Hardware security keys (e.g. Yubikey) as second factor |
| CSS/JS | Bootstrap 5, jQuery, Bootstrap-datepicker, Bootstrap-fileinput, TinyMCE, HTMX | Front-end; vendored under `vendor/` |
| Web server | Apache | mod_rewrite-style front-controller routing via `.htaccess` |

There is no SPA, no bundler, no Node.js build step, no Composer-managed runtime dependency graph. Pages are server-rendered HTML. The few JavaScript helpers (`vendor/tuskfish/*.js`) cover the content/block editor forms and the WebAuthn ceremonies; everything else is progressive enhancement supplied by the vendored libraries.

## Architectural pattern: MVVM

Tuskfish 2 is a from-scratch rewrite of Tuskfish 1 along **MVVM (Model–View–ViewModel)** lines. Each route is served by four cooperating components:

- **Model** — owns data access. Talks to the `Database` via the `CriteriaFactory`, validates parameters, returns entities or arrays. Knows nothing about presentation.
- **ViewModel** — the heart of a page. Holds all the state a template will display, exposes it through getters, and runs "actions" (e.g. `displayList()`, `displayObject()`) that ask the model for data and prepare it for output. Decides which template and theme to use.
- **View** — a thin object (`View\Listing` or `View\Single`) that assigns the ViewModel to a `Template` and renders it. There are only two views in the whole system; almost all per-page variation lives in the ViewModel and the template.
- **Controller** — thin glue. Reads request input (`$_GET`/`$_POST`/`$_REQUEST`), calls setters and one action method on the ViewModel, and returns **cache parameters** describing the page so it can be cached and later located.

The flow of control is: **Controller → ViewModel → Model**, and the flow of data back is **Model → ViewModel → Template**. Templates read data directly off the ViewModel (`$viewModel->someGetter()`); they never touch the model or the database.

Dependencies are wired by the **Dice** container, configured in `header.php` (core services) and instantiated per-request in `FrontController`. Components declare what they need in their constructors; Dice supplies it. This keeps the components testable and free of global lookups.

## Directory structure

```
tuskfish2/
├── index.php                 # Front-end controller script (entry point for all page requests)
├── mainfile.php              # Bootstraps paths + DB credentials; included by every entry point
├── .htaccess                 # Apache rewrite — routes all requests through index.php
├── admin/                    # (web root) admin entry scripts
├── cache/                    # Public HTML page cache (writable)
├── themes/                   # Template sets (one dir per theme) — the View layer's HTML
│   ├── default/              # Default front-end theme (layout.html + per-type templates)
│   ├── admin/                # Admin control-panel theme
│   ├── rss/ signin/          # Special-purpose themes
│   └── cerulean/ darkly/ ... # Bootswatch-based alternative themes
├── uploads/                  # User-supplied files
│   ├── image/                # Inline content images + generated thumbnails
│   └── media/                # Downloadable enclosures (audio, video, downloads, tracks)
├── vendor/                   # Vendored third-party front-end libs + Tuskfish's own JS
└── trust_path/               # Everything outside the web root — the bulk of the application
    ├── configuration/
    │   └── config.php        # Path constants, autoloader, DB pointer (CHMOD 0400 in production)
    ├── database/             # The SQLite database file(s)
    ├── cache/                # Private cache
    ├── log/tuskfish_log.txt  # Error log (check here first on a 500)
    ├── pads/                 # One-time pads / key material for crypto
    ├── cron/expiresOn.php    # Cron job: take expired content offline
    ├── utilities/            # One-off maintenance scripts (index migrations, etc.)
    └── libraries/
        ├── htmlpurifier/     # HTML sanitiser
        ├── phpmailer/        # SMTP mailer
        └── tuskfish/         # ★ The application itself
            ├── header.php          # Per-request bootstrap: security headers, Dice rules, blocks seed
            ├── routingTable.php    # Static route table (path → Route(MVVMC, accessMask))
            ├── version.php         # Version string ("Tuskfish V2.3.2")
            ├── language/english.php# UI text constants (TFISH_*)
            └── class/
                ├── Dice/           # DI container
                ├── webauthn/       # Self-contained WebAuthn (CBOR, attestation, binary)
                └── Tfish/          # ★★ All application classes (namespace \Tfish)
                    ├── *.php              # Core services (see below)
                    ├── Entity/            # Preference, Metadata, Template
                    ├── Interface/         # Block, Listable, Viewable contracts
                    ├── Traits/            # Cross-cutting reusable behaviour
                    ├── Model/             # Core (non-content) models
                    ├── ViewModel/         # Core viewModels
                    ├── View/              # Listing + Single views
                    ├── Controller/        # Core controllers
                    ├── Content/           # ★ Content module (MVVMC + Entity + Block + header.php)
                    └── User/              # ★ User module (MVVMC + Entity + module-local templates)
```

### Two paths, one principle

Tuskfish splits the filesystem into a **web root** (the repo root: `index.php`, `themes/`, `uploads/`, `vendor/`, `cache/`) and a **trust path** (`trust_path/`) that should sit outside the document root in production. Almost all code lives in the trust path; the public surface is deliberately tiny. `mainfile.php` defines `TFISH_ROOT_PATH`, `TFISH_TRUST_PATH` and `TFISH_URL`, then loads `config.php`, which derives every other path constant from those three.

## Core services (`class/Tfish/*.php`)

| Class | Responsibility |
|-------|----------------|
| `FrontController` | Orchestrates the whole request lifecycle (see below) |
| `Router` / `Route` | `Router` looks a path up in the routing table and returns a `Route`, which names the four MVVMC classes plus an access bitmask |
| `Database` | Thin PDO/SQLite wrapper: prepared statements only, backtick-escaped identifiers, WAL mode, foreign keys enforced |
| `Criteria` / `CriteriaItem` / `CriteriaFactory` | Query-building objects; models compose `Criteria` to express WHERE/ORDER/LIMIT without writing raw SQL |
| `Session` | Login, logout, privilege bitmask, session hardening (regeneration, fingerprinting, expiry), WebAuthn assertion |
| `Cache` | Disk-based full-page HTML cache, keyed by path + parameters |
| `Pagination` | Builds Bootstrap pagination controls from a result count |
| `BlockRegistry` | Whitelist of registered block types, templates, positions, routes and config — populated by module headers |
| `Tree` | Builds nested trees (used for collections / parent-child content) |
| `Logger` | Custom error + exception handler, writes to `trust_path/log/tuskfish_log.txt` |
| `FileHandler` | Safe file operations (uploads, deletes) |
| `Mail` | PHPMailer front-end |
| `Crypto` | Authenticated symmetric encryption (used e.g. for the stored SMTP password) |
| `WebAuthnService` | Wraps the bundled WebAuthn library for registration/assertion |

`Entity\Preference`, `Entity\Metadata`, `Entity\Template` are shared data holders: site preferences loaded from the `preference` table, page metadata, and the template renderer respectively.

## How a request flows

Every front-end request hits `index.php` (Apache rewrites all paths there). The sequence:

```
Client request → index.php
  1. mainfile.php          Define root/trust/URL paths; load config.php (autoloader, DB pointer)
  2. routingTable.php      Load the static path → Route map
  3. header.php            Send security headers; build the Dice container with core service rules;
                           seed the block-registry whitelist arrays
  4. glob module headers   Auto-discover class/Tfish/*/header.php and require each (Content, User, …);
                           modules append their block types/templates/routes to the seed arrays
  5. finalise BlockRegistry Inject the aggregated block whitelist into Dice as a shared singleton
  6. derive route path     Take REQUEST_URI's path only (scheme/host deliberately ignored — host is
                           client-supplied and untrusted); strip the install base dir; normalise slashes
  7. Router->route(path)   Return the matching Route, or the /error/ route on no match
  8. create FrontController Dice instantiates it with the Route and path
```

Inside `FrontController::__construct`:

```
session->start()
checkSiteClosed()      → if site is closed and you're not admin, redirect to /login/
checkAccessRights()    → bitmask test of the route's access mask vs the user's privileges;
                         redirect to /login/ (303) if unauthenticated, /restricted/ (403) if logged in
                         but unauthorised. Super-users bypass all route checks.
create MVVMC via Dice  → Model, ViewModel(model), View(viewModel), Controller(model, viewModel)
resolve action         → $_REQUEST['action'] (default 'display'); must be alphabetic AND a real method
                         on the controller, else redirect to /error/
ob_start()
$cacheParams = controller->{action}()   ← controller reads input, drives viewModel, returns cache key
cache->check(path, cacheParams)         ← serve cached HTML if a fresh copy exists (and exit)
renderLayout()                          ← render the page body + blocks, include the theme layout.html
if (!viewModel->doNotCache()) cache->save(...)   ← persist the rendered HTML
database->close(); ob_end_flush()
```

`renderLayout()` calls `$view->render()` to produce the page body, loads the blocks for this route (`renderBlocks()`), updates `Metadata` from the ViewModel, then `include`s `themes/{theme}/{layout}.html`. The theme and layout names come from the ViewModel and are traversal/null-byte checked before use.

## The routing table

`routingTable.php` returns an associative array mapping a normalised request path (with leading and trailing slashes, e.g. `/admin/content/`) to a `Route` object. Each `Route` names four fully-qualified class names — Model, ViewModel, View, Controller — and an **access bitmask**:

```php
'/admin/content/' => new Route(
    '\\Tfish\\Content\\Model\\ContentEdit',
    '\\Tfish\\Content\\ViewModel\\ContentEdit',
    '\\Tfish\\View\\Single',
    '\\Tfish\\Content\\Controller\\ContentEdit',
    2),   // ← access mask: editors and super-users
```

The bitmask mirrors CHMOD-style permissions and is defined in the `Group` trait:

| Bit | Constant | Meaning |
|-----|----------|---------|
| `0` | (public) | Anyone |
| `1` | `G_SUPER` | Site administrator only |
| `2` | `G_EDITOR` | Editors |
| `4` | `G_MEMBER` | Members |

The site administrator (super-user) has implicit access to **all** routes. Masks combine with bitwise OR, so a route open to editors and members would use `2 | 4 = 6`. Unmatched paths fall through to the `/error/` route.

Adding a page means adding one line to this table (or, for a module, merging routes in via its header — see *Modules* below) and supplying the four classes. There is no annotation scanning or convention-based discovery for page routes; the table is the single source of truth, kept in one editable file by design.

## Models, ViewModels, Controllers — the contract

A representative front-end flow (`/`, the home page) uses `Content\Controller\Listing`, `Content\ViewModel\Listing`, `Content\Model\Listing`, and `View\Listing`:

1. **Controller** (`display()` action) reads `start`, `tag`, `type`, `id` from `$_GET`, pushes them into the ViewModel via setters, sets sort order, then calls either `displayObject()` (single item) or `displayList()` (teaser stream). It returns a `$cacheParams` array (`['page' => 'home', …]`) that identifies this exact page view for caching.
2. **ViewModel** (`displayList()`) sets `$this->template = 'listView'`, calls the model to fetch the content list and count, and builds page metadata. It implements `Interface\Listable` (via the `Listable` trait) so the `Listing` view and pagination know how to drive it. Access control on a single object is enforced here by comparing the content's `accessGroups` mask against the user's mask (`canAccess()`).
3. **Model** (`getObject()`, `getList()`) builds a `Criteria` through the injected `CriteriaFactory`, runs it against the `Database`, and returns `Content` entities. Non-admins are silently restricted to `onlineStatus = 1`.
4. **View** (`View\Listing`) assigns the ViewModel to a `Template` and renders it.

Controllers are intentionally thin (`Content\Controller\Listing` is ~60 lines of logic). The pattern is consistent enough that most controllers are near-identical glue; the interesting per-page logic lives in the ViewModel.

## The template system

There is no template engine. `Entity\Template` is a tiny class that holds a bag of variables and renders a plain PHP file:

```php
public function render(): string {
    \extract($this->variables);   // assigned vars become local
    \ob_start();
    include $this->validPath();   // a .html file containing PHP
    return \ob_get_clean();
}
```

Templates are `.html` files containing inline PHP (`<?php echo xss($viewModel->title()); ?>`). The global `xss()` helper (defined in `header.php`) is the universal output-escape function — it wraps `htmlspecialchars` with HTML5-aware flags and must be used on **all** plain-text output. HTML markup is instead sanitised on *input* with HTMLPurifier, so it can be echoed raw.

**Template resolution** (`Template::validPath()`) checks two locations in order:

1. `themes/{theme}/{template}.html` — the active theme. If present, it wins, letting theme authors override anything.
2. `{modulePath}/{template}.html` — a module's bundled default, used only when the module supplied a `modulePath` *and* the theme doesn't provide the template.

This fallback is what makes drop-in modules possible (the `User` module ships its own templates under `User/templates/` and works in any theme without the theme author copying files in). Core and Content routes pass an empty `modulePath`, so they resolve theme-only, preserving legacy behaviour. Both candidate paths are traversal/null-byte checked.

The **layout** (`themes/{theme}/layout.html`) is the outer HTML shell — `<head>`, nav bar, block positions, footer, `</html>`. It is `include`d by `FrontController::renderLayout()` with `$page` (the rendered body), `$blocks`, `$metadata` and `$session` in scope. Layouts and CSS/JS are **not** module-local — they always come from the theme.

## Modules

Tuskfish 2 is organised so that new functional areas ship as **self-contained modules** under `class/Tfish/{Module}/`. The two present modules are **Content** (the main publishing engine) and **User** (account management).

A module directory may contain its own `Model/`, `ViewModel/`, `Controller/`, `Entity/`, `Block/`, `Traits/`, `language/`, `templates/`, and — crucially — a **`header.php`**. At bootstrap, `index.php` globs `class/Tfish/*/header.php` (alphabetical order) and requires each one. A module header can:

- Define module-specific path/URL/language constants.
- **Register block types, templates, config sub-templates and routes** by *appending* to the block-registry seed arrays (`$blockTypes`, `$blockTemplates`, `$blockConfig`, `$blockRoutes`). The convention is strict: append, never reassign — all module headers share these variables in `index.php`'s scope, so a reassignment would clobber every other module's registrations.

Page routes for Content still live in the central `routingTable.php` and its page templates still live in `themes/`; the header mechanism currently carries the block registrations. The `User` module is the fuller realisation of the "drop-in module" goal: it bundles its own templates (resolved via the `modulePath` fallback) so it works without touching any theme.

Classes autoload via the PSR-style autoloader in `config.php`: `\Tfish\Content\Model\Listing` maps to `class/Tfish/Content/Model/Listing.php`. The autoloader checks the file exists before including, so additional (e.g. module-specific) autoloaders can chain after it.

## The block system

Blocks are small, self-contained content widgets rendered into named layout **positions** (`banner`, `top-left`, `top-centre`, `left`, `right`, `bottom-*`, `footer`). The vocabulary of positions is fixed by the core (`header.php`) and is **not** module-extensible; everything else about blocks is.

- **`BlockRegistry`** holds whitelists of registered block *types* (FQCN → label), the *templates* each type offers, the *config* sub-template each type uses, the *positions*, and the *routes* on which blocks may appear. Modules populate these via their headers; the aggregated result is injected into Dice as a shared singleton after the module glob.
- Each block is a class implementing `Interface\Block` (e.g. `Content\Block\RecentContent`, `Spotlight`, `FeaturedVideo`, `Html`). It receives its DB row, the `Database`, and the `CriteriaFactory`, and exposes `html()` to render itself through a block template (`Content/Block/*.html`).
- Admins create and configure block instances at `/admin/blocks/`. Each instance is a row in the `block` table; the routes it appears on are rows in `blockRoute`.
- At render time, `FrontController::renderBlocks()` runs one query joining `block` to `blockRoute` for the current path, instantiates each whitelisted block class, and returns them grouped by position (sorted by weight) and indexed by ID. The layout echoes them: `$blocks['position']['top-centre']` is an array of blocks for that slot.

The whitelist check (`is_a($className, Interface\Block::class)`) means only registered block classes can ever be instantiated from a DB row — a stored type that isn't whitelisted is silently skipped.

## Data model

The schema (created by `install/index.php`) is small and lives in a single SQLite file under `trust_path/database/`. Main tables:

| Table | Holds |
|-------|-------|
| `content` | Every content object — all types share one table (single-table inheritance) |
| `taglink` | Many-to-many links between content and its tags (tags are themselves `content` rows of type `TfTag`) |
| `block` | Block instances (type, position, title, html, config, weight, template, onlineStatus) |
| `blockRoute` | Which routes each block appears on |
| `user` | Accounts (admin, editors, members) |
| `session` | Server-side session data |
| `preference` | Site preferences (one row of key settings) |
| `webauthn_credentials` | Registered WebAuthn/FIDO2 authenticators |

**Single-table content.** All content types (`TfArticle`, `TfAudio`, `TfCollection`, `TfDownload`, `TfImage`, `TfTrack`, `TfVideo`, `TfStatic`, `TfTag`) are rows in `content`, distinguished by the `type` column. A single `Content\Entity\Content` class models them all; its fields cover the union of what any type needs (`media`, `externalMedia`, `image`, `caption`, `parent`, `accessGroups`, `inFeed`, `onlineStatus`, expiry, view counter, SEO metadata, etc.). The `parent` column gives collections their hierarchy, walked by the `Tree` class.

**Tags** are content objects, not a separate table; `taglink` joins them to other content. This means tags get titles, descriptions and their own pages "for free".

**Files on disk, metadata in DB.** Uploaded enclosures live under `uploads/media/`, inline images and their generated thumbnails under `uploads/image/`. The `content` row stores the filename and metadata (`format`, `fileSize`); the bytes are never in the database.

**Access control** is the `accessGroups` integer on each content row — the same group bitmask used for routes. `0` is public; non-zero restricts the object to matching groups (super-users and editors always pass).

## Authentication and security

**Single-admin model.** The defining simplification: there is no general user-rights-management system. There is one administrator. Editor and member roles exist (as group bits) but the system is built around one publisher.

**Sessions** (`Session` class) are hardened: regeneration on privilege change, fingerprint/cleanliness checks (`isClean()`), idle expiry (`isExpired()`), and a privilege bitmask (`verifyPrivileges()`) read on every restricted route. Login deliberately delays on failure (`delayLogin()`) to throttle brute force.

**Passwords** are hashed with `hashPassword()` (PHP's `password_hash`).

**Two-factor authentication (WebAuthn).** Optional hardware-key 2FA, implemented with a *bundled, self-contained* WebAuthn library (`class/webauthn/`) rather than an external dependency — consistent with the minimal-dependency principle. Supports a main and a backup key. Registration and assertion ceremonies are driven by `WebAuthnService` + `Session::verifyWebAuthnAssertion()`, with the browser-side JS in `vendor/tuskfish/webauthn-*.js`. Credentials are stored in `webauthn_credentials`.

**SQL injection** is closed off structurally: the `Database` class exposes only prepared statements with bound parameters, and table/column identifiers are backtick-escaped through `addBackticks()`. Models build queries through `Criteria` objects, never string concatenation.

**XSS** is handled on two fronts — output is escaped with the global `xss()` helper in every template; HTML *input* is run through HTMLPurifier before storage so it can be rendered without re-escaping.

**HTTP headers** are set in `header.php`: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Cross-Origin-Opener-Policy`, `Cross-Origin-Resource-Policy`, `Permissions-Policy`, and a (commented, customise-per-site) `Content-Security-Policy`. HSTS is ready to enable once TLS is in place. Charset is locked to UTF-8.

**Routing trust boundary.** Routing uses the request *path* only. Scheme and host are deliberately ignored — the host is client-supplied (`Host`/`SERVER_NAME`) and must not be trusted, and ignoring the scheme means the app works transparently behind a TLS-terminating reverse proxy. The only admin-controlled input used is the base directory from `TFISH_LINK`, which keeps sub-directory installs working. `REQUEST_URI` is split on `?` directly (not via `parse_url`, which would misread a leading `//` as an authority and mis-route bogus URLs).

**Crypto.** The `Crypto` class provides authenticated symmetric encryption (used, for example, to encrypt the stored SMTP password). Key material lives under `trust_path/pads/`.

**Minimal public surface.** The web root contains almost no PHP logic — just entry scripts. The application code, database, logs, config and keys all live under `trust_path/`, intended to be outside the document root. `config.php` should be CHMOD `0400` in production.

## Caching

`Cache` is a disk-based full-page HTML cache. When caching is enabled in preferences, `FrontController` buffers the rendered page and, unless the ViewModel reports `doNotCache()`, writes it to `cache/` keyed by the path plus the controller's returned `$cacheParams`. On the next matching request, `cache->check()` serves the stored HTML and short-circuits the rest of the pipeline. Restricted/personalised pages opt out via `doNotCache()`. The cache can be flushed from the admin panel (`/flush/`).

## Configuration

There is no `.env`. Configuration is PHP constants:

- **`mainfile.php`** — the three values an installer must set: `TFISH_ROOT_PATH`, `TFISH_TRUST_PATH`, `TFISH_URL`. In a Docker container these must reflect the path *inside* the container, not the host.
- **`config.php`** — derives every other path/URL constant from those three, registers the autoloader, and (appended at the end by the installer) defines `TFISH_DATABASE`, the absolute path to the SQLite file.
- **`preference` table** — runtime, admin-editable settings: site name/description/author/email/copyright, timezones, pagination sizes (search/user/admin/gallery/collection), RSS post count, session name + lifetime, date format, cache on/off + lifetime, default and admin themes, SMTP host/port/encryption/user/password, maps API key, close-site flag. Edited at `/preference/edit/`.

The `defaultTheme` preference drives front-end rendering; `adminTheme` drives the control panel. A ViewModel can override the theme per-page.

## Themes

A theme is a directory under `themes/` containing a `layout.html` (the shell) plus one `.html` template per view a page can need (`article.html`, `collection.html`, `gallery.html`, `listView.html`, `download.html`, `video16x9.html`, `error.html`, `login.html`, …) and a `style.css`. The shipped set includes a `default` front-end theme, an `admin` theme for the control panel, special-purpose `rss` and `signin` themes, and a range of Bootswatch-derived alternatives (`cerulean`, `cosmo`, `darkly`, `flatly`, `lux`, `materia`, `minty`, `pulse`, `sandstone`, `simplex`, `united`, `yeti`, `zephyr`, `cyborg`). Because the template engine is just PHP `include`, building a new theme is copying an existing directory and editing HTML/CSS — no compilation, no registration step.

## Maintenance scripts

- **`trust_path/cron/expiresOn.php`** — run from cron to take content past its `expiresOn` date offline.
- **`trust_path/utilities/`** — one-off migration helpers (`add_indexes.php`, `add_stream_index.php`) for evolving the schema of an existing install.

## Installation

`install/index.php` is a self-contained installer: it checks the PHP version (8.4+ required) and required extensions (SQLite3, PDO, pdo_sqlite, GD2), collects database credentials via a form (`install/dbCredentialsForm.html`), creates the schema (the `CREATE TABLE` statements for `user`, `preference`, `session`, `content`, `taglink`, `block`, `blockRoute`, `webauthn_credentials`), and writes the database pointer into `config.php`. The `install/` directory should be deleted after setup.

## Adding a feature — what to touch

To add a new page to an existing module:

1. **Model** — `class/Tfish/{Module}/Model/Thing.php`: data access via `Criteria`/`Database`.
2. **ViewModel** — `class/Tfish/{Module}/ViewModel/Thing.php`: state, getters, action methods, template/theme choice. Implement `Interface\Listable` for list pages.
3. **Controller** — `class/Tfish/{Module}/Controller/Thing.php`: read input, drive the ViewModel, return cache params.
4. **Template** — `themes/{theme}/thing.html` (or `{Module}/templates/thing.html` for a drop-in module).
5. **Route** — add a line to `routingTable.php` naming the four classes and an access mask (reuse `View\Listing` or `View\Single`).

To add a **block type**: create a class under `{Module}/Block/` implementing `Interface\Block`, add its template(s) and config sub-template, and register all of them in the module's `header.php` by appending to the registry seed arrays.

To add a **whole module**: create `class/Tfish/{Module}/` with the MVVMC sub-directories and a `header.php`; it is auto-discovered at bootstrap. Bundle templates under `{Module}/templates/` and pass the path as the ViewModel's `modulePath` so they resolve with theme fallback.

There is no dependency-injection configuration to edit beyond `header.php`'s Dice rules for genuinely new shared services, no interface registry, no plugin manifest. You write concrete classes in concrete files and wire them up through the routing table and (for modules) the auto-discovered header.

## Bill of materials

Authoritative sources: `README.md` for system requirements, `version.php` for the release string, `vendor/` for vendored front-end libraries, `trust_path/libraries/` for back-end libraries.

### Runtime requirements

| Component | Version | Notes |
|-----------|---------|-------|
| Tuskfish | V2.3.2 | `trust_path/libraries/tuskfish/version.php` |
| PHP | 8.4+ (8.5-ready) | strict types throughout |
| SQLite | 3 | via PDO + pdo_sqlite |
| GD2 | — | thumbnailing (ImageMagick 6 optional alternative) |
| Apache | — | front-controller routing |

### Bundled back-end libraries (`trust_path/libraries/`)

| Library | Purpose |
|---------|---------|
| Dice | Dependency injection container |
| HTMLPurifier | HTML input sanitisation |
| PHPMailer | SMTP mail |
| (bundled) WebAuthn | FIDO2 second-factor authentication (`class/webauthn/`) |

### Vendored front-end libraries (`vendor/`)

| Library | Purpose |
|---------|---------|
| Bootstrap 5 | CSS framework and components |
| jQuery | DOM/AJAX support for vendored widgets |
| Bootstrap-datepicker | Date picker in the editor |
| Bootstrap-fileinput | File upload widget |
| TinyMCE | Rich-text editor for content bodies |
| HTMX | Progressive enhancement |
| (icon set) | Content-type icons |
| `vendor/tuskfish/*.js` | Tuskfish's own editor + WebAuthn helper scripts |
