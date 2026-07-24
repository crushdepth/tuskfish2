# Creating a New Tuskfish Module — the Minimum Skeleton

How to scaffold a new module in Tuskfish 2: the minimum set of files, what each one must
contain, and the traps. Derived from reading the core request lifecycle plus the `Stats`
module on the `fishstat` branch, which is the reference implementation for a module that
reads its **own, separate SQLite database** alongside the CMS database.

Examples below use a hypothetical `Rangefinder` module serving one public page at `/map/`.
Substitute your own module name throughout — the namespace, the directory name and the
constant prefix must all agree.

---

## How a module hooks into Tuskfish

Four attachment points, and only the first is auto-discovered:

1. **`header.php`** — `index.php:31` globs `class/Tfish/*/header.php` and requires each one
   in the *global* scope of `index.php`. That scope already holds `$routingTable` (loaded at
   line 25) and the block-registry seed arrays, so a header can add both.
2. **Autoloader** — `config.php:90` maps `\Tfish\Rangefinder\Model\Map` →
   `class/Tfish/Rangefinder/Model/Map.php`. Directory names must match the namespace
   exactly (case-sensitive on Linux).
3. **Template fallback** — `Entity\Template::validPath()` tries
   `themes/{theme}/{tpl}.html` first, then `{modulePath}/{tpl}.html`. A module that sets
   `$modulePath` ships its own templates and needs **zero theme edits**.
4. **`View\Single`** is reused as-is — there is no view class to write.

> **Doc drift:** `DEVELOPMENT.md` §25 says "page routes still live centrally in
> `routingTable.php`". That is out of date — `Stats/header.php` registers all seven of its
> routes itself, and the load order in `index.php` supports it.

---

## Minimum file set

For one public page route, with a separate read-only SQLite connection:

```
trust_path/libraries/tuskfish/class/Tfish/Rangefinder/
├── header.php                        # REQUIRED — the only discovery hook
├── index.html                        # empty; directory-listing guard (convention)
├── language/english.php              # constants; included by header.php
├── Traits/RangefinderDatabase.php    # read-only PDO to the module's own .db
├── Model/Map.php
├── ViewModel/Map.php
├── Controller/Map.php
└── templates/map.html
```

**8 files.** It strips to 5 (`header.php`, Model, ViewModel, Controller, template) if you
drop the language file, the guard, and inline the database connection — but the trait is the
whole point of copying `Stats`, so keep it.

---

## What each placeholder must contain

| File | Contract |
|---|---|
| `header.php` | `namespace Tfish\Rangefinder;` → include the language file → `\define("TFISH_RANGEFINDER_TEMPLATE_PATH", TFISH_CLASS_PATH . 'Tfish/Rangefinder/templates/')` and `\define("TFISH_RANGEFINDER_DB", 'your-database.db')` → `$routingTable['/map/'] = new \Tfish\Route(model, viewModel, '\\Tfish\\View\\Single', controller, 0);` (mask `0` = public) |
| `Model/Map.php` | Plain class. `$dice->create($route->model())` passes **no positional args**, so every constructor param must be a Dice-resolvable type (`Database`, `Preference`, `Session`, `Logger`, `CriteriaFactory`). `use` the database trait and call `$this->connect()` in the constructor |
| `ViewModel/Map.php` | `implements \Tfish\Interface\Viewable`; `use \Tfish\Traits\ValidateString; use \Tfish\Traits\Viewable;`. Constructed as `[$model]` **+ autowired extras**. Constructor sets `$this->theme = $preference->defaultTheme()`, `$this->modulePath = TFISH_RANGEFINDER_TEMPLATE_PATH`, `$this->pageTitle`. The display action sets `$this->template = 'map'` |
| `Controller/Map.php` | Constructed as `[$model, $viewModel]` + autowired extras. Needs a **`display(): array`** returning cache params. `FrontController:105` reads `$_REQUEST['action']` (default `display`) and rejects non-alpha or non-existent methods |
| `templates/map.html` | Plain PHP + HTML; `$viewModel` is in scope; escape everything with the global `xss()` helper (`header.php:112`) |
| `Traits/RangefinderDatabase.php` | Copy of `Stats/Traits/StatsDatabase.php`'s `connect()`: opens `TFISH_DATABASE_PATH . TFISH_RANGEFINDER_DB` with `Pdo\Sqlite::OPEN_READONLY` (falling back to `PDO::SQLITE_OPEN_READONLY` on older PHP), `ERRMODE_EXCEPTION`, 5s timeout. The host class must provide `$this->logger` |

### The request lifecycle these contracts come from

`FrontController::__construct()` instantiates the quartet like this:

```php
$model     = $dice->create($route->model());                      // no positional args
$viewModel = $dice->create($route->viewModel(), [$model]);        // model is arg 1
$this->view      = $dice->create($route->view(), [$viewModel]);
$this->controller = $dice->create($route->controller(), [$model, $viewModel]);

$action = $this->trimString($_REQUEST['action'] ?? 'display');
$cacheParams = $this->controller->{$action}();
```

then calls `$viewModel->metadata()`, `->theme()`, `->layout()` and `->doNotCache()` — all of
which the `Viewable` trait supplies for free.

---

## What `Stats` has that a skeleton does not need

Multiple route quartets, a `StatsMetadata` trait (a good convention — worth copying once you
need canonical URLs and filter-aware page titles), a `sql/` directory of summary-table
builders, a custom `layoutStats` layout, and theme CSS/JS. The default theme's `layout.html`
works untouched, so **no theme files are required to boot a module**.

---

## Gotchas

- **`$modulePath` has no setter** in `Traits\Viewable` — assign the property directly in the
  ViewModel constructor, as `Stats` does.
- `Stats/header.php` uses `include 'language/english.php';`, which relies on PHP's
  calling-script-directory fallback. Prefer `__DIR__ . '/language/english.php'` — same cost,
  no dependence on `include_path` or cwd.
- Headers run on **every request** and `\define()` is global. Prefix every constant
  `TFISH_RANGEFINDER_*` so modules cannot collide.
- Module headers are globbed **alphabetically** and share the block-registry seed arrays.
  If you register blocks, **append** (`$blockTypes['\Tfish\Foo\Block\Bar'] = …`,
  `array_merge()` for `$blockRoutes`) — never reassign, or you clobber every other module.
- Route access mask `0` is public and returns immediately from `checkAccessRights()`. Any
  non-zero mask must be a valid bit from the `Group` trait or the request throws.
- A module's own database should be opened **read-only**. Core `\Tfish\Database` connects to
  `TFISH_DATABASE` in its constructor and takes no path argument, so a second database needs
  its own trait/class — it cannot be a second `Database` instance.
