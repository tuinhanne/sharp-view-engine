# Cache & Dependency Graph

Sharp compiles `.sp` templates to plain PHP files and caches them. Recompilation only happens when source files change.

---

## Table of Contents

- [Cache location](#cache-location)
- [How caching works](#how-caching-works)
- [Dependency graph](#dependency-graph)
- [Atomic writes](#atomic-writes)
- [Clearing the cache](#clearing-the-cache)
- [Disabling the cache](#disabling-the-cache)

---

## Cache location

```
{project-root}/
└── .sharp/
    ├── compiled/
    │   ├── a3f1c2d4.php    ← compiled PHP file
    │   └── b8e9f012.php
    ├── graph/
    │   ├── a3f1c2d4.json   ← dependency graph entry
    │   └── b8e9f012.json
    └── ast/
        ├── a3f1c2d4.ast    ← source map (dev mode only)
        └── b8e9f012.ast
```

Filenames are the MD5 hash of the view name (not the content). This gives O(1) lookup.

> All directories are created automatically. Add `.sharp/` to your `.gitignore`.

### `.ast` source map files

When `devMode` is enabled (see [Dev Mode & Source Maps](#dev-mode--source-maps)), Sharp writes a `.ast` file alongside each compiled template. This file maps source line numbers in the `.sp` template to the corresponding lines in the compiled PHP output.

**Format:**
```json
{
  "version": 1,
  "source": "/absolute/path/templates/home.sp",
  "viewKey": "home",
  "mappings": [
    { "spLine": 3, "phpLine": 7 },
    { "spLine": 5, "phpLine": 12 }
  ]
}
```

The Sharp VSCode extension reads these files to translate Xdebug breakpoints between `.sp` lines and compiled `.php` lines.

> `.ast` files are only written when `devMode` is on. They are not generated in production.

---

## How caching works

On each `render()` call, Sharp performs a cache validity check before compiling:

```
render('pages.home')
│
├─ Does .sharp/compiled/{hash}.php exist?  No  → compile + cache
│
├─ Does .sharp/graph/{hash}.json exist?    No  → compile + cache
│
├─ Read graph: source mtime + dependency mtimes
│
└─ Any file newer than compiled?           Yes → recompile + update graph
                                           No  → include cached file ✓
```

The check is a set of `filemtime()` calls — negligible overhead. Template compilation only happens when needed.

---

## Dependency graph

The dependency graph tracks every file a template depends on and their modification times at compile time.

**What is tracked:**
- The template itself
- Every `#extends('...')` layout
- Every `#include('...')` partial
- Every component used via `<ComponentTag />`

**Graph record format (`.sharp/graph/{hash}.json`):**
```json
{
    "view":   "pages.dashboard",
    "mtimes": {
        "templates/pages/dashboard.sp": 1710000000,
        "templates/layouts/main.sp":    1709999000,
        "templates/partials/nav.sp":    1709998000
    }
}
```

If `templates/partials/nav.sp` is modified, every template that `#include`s it (directly or transitively) will be recompiled on the next render. No manual cache invalidation needed.

---

## Atomic writes

Sharp writes compiled files atomically to avoid serving partial output during concurrent requests:

1. Write content to a temp file: `{hash}.tmp.{uniqid}`
2. `@unlink($final)` — remove existing file if present (required on Windows)
3. `rename($tmp, $final)` — atomic swap

This means a request that reads the cache while another request is recompiling will never see a half-written file.

---

## Dev Mode & Source Maps

Sharp includes a dev mode that injects source annotations into rendered HTML and generates `.ast` source-map files.

**Enable via config:**
```json
{
  "viewPath": "templates/",
  "sandbox": true,
  "devMode": true
}
```

**Enable via code** (takes priority over config):
```php
$sharp->setProduction(false); // dev mode on
$sharp->setProduction(true);  // production mode — disable all annotations
```

**Priority chain** (highest → lowest):
1. `setProduction()` explicit call
2. `"devMode"` in `sharp.config.json`
3. Default: **dev mode on**

**What dev mode does:**
- Injects `data-sharp-src="path/to/file.sp:8"` on every HTML opening tag in rendered output
- Writes `.sharp/ast/<hash>.ast` source-map files
- These enable the Sharp VSCode extension's live preview and Xdebug breakpoint translation

> Always set `devMode: false` (or call `setProduction(true)`) when deploying to production.

---

## Clearing the cache

```bash
# Clear everything (compiled PHP + dependency graphs + source maps)
rm -rf .sharp/

# Clear only compiled files
rm -rf .sharp/compiled/

# Clear only source maps
rm -rf .sharp/ast/
```

Sharp recreates the directories automatically on the next render.

---

## Disabling the cache

Sharp ships with a `NullCache` that skips all caching — every render recompiles from source. Useful during development or in test environments.

```php
use Sharp\Support\Cache\NullCache;

// Internal: inject NullCache during tests
$compiler = new Compiler($config, new NullCache());
```

> In normal usage, the built-in `FileCache` handles all caching automatically. You do not need to configure this.
