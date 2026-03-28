# Getting Started

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.1` |
| Composer | `^2.0` |

No other runtime dependencies.

---

## Installation

```bash
composer require bynhan/sharp
```

---

## Project Setup

Create a `Sharp` instance and call `render()`. That's it.

```php
<?php
require 'vendor/autoload.php';

use Sharp\Sharp;

$sharp = new Sharp();
echo $sharp->render('home', ['title' => 'Welcome']);
```

Sharp looks for `sharp.config.json` in the project root (`getcwd()`).
**If the file does not exist, Sharp creates it automatically:**

```json
{
    "viewPath": "templates/",
    "sandbox": true
}
```

Your first template lives at `templates/home.sp`:

```html
<h1>{{ $title }}</h1>
```

---

## Configuration

| Key | Type | Default | Description |
|---|---|---|---|
| `viewPath` | `string` | `"templates/"` | Base directory for `.sp` files. Relative to project root or absolute. |
| `sandbox` | `bool` | `true` | Block dangerous PHP functions inside templates. See [Security](security.md). |

### Custom root directory

```php
// Different project root (reads /srv/app/sharp.config.json)
$sharp = new Sharp('/srv/app');
```

### Config file example

```json
{
    "viewPath": "resources/views/",
    "sandbox": true
}
```

---

## Directory structure

After the first `render()` call:

```
project-root/
├── sharp.config.json          ← auto-created
├── templates/
│   └── home.sp
└── .sharp/
    ├── compiled/              ← compiled PHP files (gitignored)
    └── graph/                 ← dependency cache (gitignored)
```

Add `.sharp/` to your `.gitignore` — it is always regenerated.

---

## Rendering a view

```php
// Dot notation maps to subdirectories
$sharp->render('home');                       // templates/home.sp
$sharp->render('pages.about');               // templates/pages/about.sp
$sharp->render('admin.users.index');         // templates/admin/users/index.sp

// With data
$html = $sharp->render('profile', [
    'user'  => $user,
    'posts' => $user->posts(),
]);

// Namespace loader
$sharp->namespace('admin', '/path/to/admin-views');
$sharp->render('admin::dashboard');          // /path/to/admin-views/dashboard.sp
```

---

## Next steps

- [Template Syntax](syntax.md) — directives, echo, comments, loops
- [Layouts](layouts.md) — `#extends`, `#section`, `#yield`
- [Components](components.md) — reusable template fragments
- [Custom Directives](directives.md) — compile-time inlined directives
- [Loaders](loaders.md) — file, namespace, memory loaders
