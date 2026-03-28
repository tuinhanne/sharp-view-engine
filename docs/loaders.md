# Loaders

Loaders resolve a view name string to template source. Sharp ships with three loaders. You can also implement `LoaderInterface` to add your own.

---

## Table of Contents

- [How loaders work](#how-loaders-work)
- [File Loader](#file-loader)
- [Namespace Loader](#namespace-loader)
- [Memory Loader](#memory-loader)
- [Custom Loader](#custom-loader)
- [Loader priority](#loader-priority)

---

## How loaders work

When `$sharp->render('view.name')` is called:

1. Sharp iterates through registered loaders in priority order.
2. The first loader whose `supports($name)` returns `true` is used.
3. That loader's `load($name)` returns the template source string.

---

## File Loader

The **default loader**. Converts dot notation to a file path under `viewPath` and loads the `.sp` file.

| View name | Resolved file |
|---|---|
| `'home'` | `{viewPath}/home.sp` |
| `'layouts.main'` | `{viewPath}/layouts/main.sp` |
| `'admin.users.index'` | `{viewPath}/admin/users/index.sp` |

No setup required — this loader is always active using `viewPath` from `sharp.config.json`.

---

## Namespace Loader

Maps a namespace prefix to a directory. Referenced with `ns::view` syntax.

### Registering namespaces

```php
$sharp->namespace('admin',  '/path/to/admin-views');
$sharp->namespace('mail',   '/path/to/email-templates');
$sharp->namespace('shared', resource_path('shared'));
```

### Usage

```php
// Renders /path/to/admin-views/dashboard.sp
$sharp->render('admin::dashboard');

// Renders /path/to/admin-views/users/index.sp
$sharp->render('admin::users.index');

// Renders /path/to/email-templates/welcome.sp
$sharp->render('mail::welcome', ['user' => $user]);
```

### In templates

Namespaced views also work with `#extends` and `#include`:

```html
#extends('admin::layouts.main')

#section('content')
  #include('shared::partials.breadcrumb')
  ...
#endsection
```

---

## Memory Loader

Stores templates as in-memory strings. Useful for testing, dynamic templates, or when templates come from a database.

```php
use Sharp\Loader\MemoryLoader;

$loader = new MemoryLoader();
$loader->set('greeting', '<p>Hello, {{ $name }}!</p>');
$loader->set('badge',    '<span class="badge">{{ $label }}</span>');

$sharp->addLoader($loader);

echo $sharp->render('greeting', ['name' => 'Nhan']);
// → <p>Hello, Nhan!</p>
```

### Updating a template at runtime

```php
$loader->set('notice', '<div class="notice">{{ $message }}</div>');
$sharp->render('notice', ['message' => 'First version']);

$loader->set('notice', '<div class="alert">{{ $message }}</div>');
$sharp->render('notice', ['message' => 'Updated version']); // recompiles automatically
```

> Memory loader templates are **always recompiled** — file mtime-based cache invalidation does not apply.

### Testing with MemoryLoader

```php
class HomeControllerTest extends TestCase
{
    private Sharp $sharp;
    private MemoryLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new MemoryLoader();
        $this->sharp  = (new Sharp())->addLoader($this->loader);
    }

    public function test_greeting(): void
    {
        $this->loader->set('hello', 'Hello, {{ $name }}!');
        self::assertSame(
            'Hello, World!',
            trim($this->sharp->render('hello', ['name' => 'World']))
        );
    }
}
```

---

## Custom Loader

Implement `Sharp\Contract\LoaderInterface`:

```php
<?php

namespace App\Loaders;

use Sharp\Contract\LoaderInterface;

class DatabaseLoader implements LoaderInterface
{
    public function __construct(private \PDO $db) {}

    public function supports(string $name): bool
    {
        // Handle views prefixed with "db::"
        return str_starts_with($name, 'db::');
    }

    public function load(string $name): string
    {
        $key = substr($name, 4); // strip "db::"

        $stmt = $this->db->prepare('SELECT source FROM templates WHERE key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new \Sharp\Exception\RenderException("Template not found: {$name}");
        }

        return $row['source'];
    }

    public function lastModified(string $name): int
    {
        // Return the template's last modified timestamp
        // Used by Sharp to decide whether to recompile
        $stmt = $this->db->prepare('SELECT updated_at FROM templates WHERE key = ?');
        $stmt->execute([substr($name, 4)]);
        $row = $stmt->fetch();
        return $row ? strtotime($row['updated_at']) : 0;
    }
}
```

Register it:

```php
$sharp->addLoader(new App\Loaders\DatabaseLoader($pdo));
```

```html
<!-- templates/pages/home.sp -->
#include('db::shared-header')
```

---

## Loader priority

Loaders are checked in **last-registered, first-checked** order. `addLoader()` inserts at the front of the stack, so the most recently added loader has the highest priority.

The built-in File Loader is always last (lowest priority) — it acts as the fallback.

```
addLoader(DatabaseLoader)  → checked 1st
addLoader(MemoryLoader)    → checked 2nd  (added later = higher priority)
namespace('admin', ...)    → checked 3rd
[FileLoader]               → checked last (built-in fallback)
```

Wait — `addLoader` prepends, so:

```
addLoader(A);  // A has priority
addLoader(B);  // B has higher priority than A now
```

Order of resolution: `B → A → NamespaceLoader → FileLoader`.
