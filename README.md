<p align="center">
  <h1 align="center">Sharp</h1>
  <p align="center">A fast, AST-based PHP view engine with <code>#</code>-directive syntax</p>
</p>

<p align="center">
  <a href="https://packagist.org/packages/bynhan/sharp"><img src="https://img.shields.io/packagist/v/bynhan/sharp" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/bynhan/sharp"><img src="https://img.shields.io/badge/PHP-8.1%2B-blue" alt="PHP 8.1+"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License"></a>
  <a href="https://github.com/bynhan/sharp/actions"><img src="https://img.shields.io/badge/tests-109%20passing-brightgreen" alt="Tests"></a>
</p>

<p align="center">
  <a href="docs/getting-started.md">Documentation</a> ·
  <a href="docs/changelog.md">Changelog</a> ·
  <a href="CONTRIBUTING.md">Contributing</a> ·
  <a href="LICENSE">MIT License</a> ·
  <a href="#sponsors">Sponsors</a>
</p>

---

## Why Sharp?

- **`#directive` syntax** — clean, readable templates without a framework dependency
- **Full AST pipeline** — Lexer → Parser → Validator → Optimizer → Codegen → FileCache; no regex string replacement
- **Zero runtime overhead for directives** — compiled and inlined at build time
- **Plain PHP expressions** — no dot-notation translator, no magic; `{{ $user->name }}` is exactly what it says
- **Standalone** — PHP 8.1+, zero runtime dependencies, drop into any project

---

## Installation

```bash
composer require bynhan/sharp
```

---

## Quick start

```php
use Sharp\Sharp;

$sharp = new Sharp();                   // reads/creates sharp.config.json at getcwd()
echo $sharp->render('home', ['user' => $user]);
```

**`templates/home.sp`**

```html
#extends('layouts.main')

#section('content')
  <h1>Hello, {{ $user->name }}!</h1>

  #foreach($user->posts as $post)
    <p>{{ $loop->iteration }}. {{ $post->title }}</p>
  #endforeach
#endsection
```

Sharp auto-creates `sharp.config.json` on first run. No manual setup required.

---

## Features at a glance

| Feature | Description |
|---|---|
| `{{ $expr }}` | HTML-escaped output |
| `{!! $expr !!}` | Raw unescaped output |
| `<!-- comment -->` | Stripped at compile time |
| `#if` / `#elseif` / `#else` / `#endif` | Conditionals |
| `#foreach` / `#endforeach` | Loops with `$loop` variable (10 properties) |
| `#while` / `#endwhile` | While loops |
| `#for` / `#endfor` | C-style for loops |
| `#switch` / `#case` / `#default` / `#endswitch` | Switch statements |
| `#extends` / `#section` / `#yield` / `#parent` | Layout inheritance |
| `#push` / `#prepend` / `#stack` | Accumulated content stacks (CSS/JS injection) |
| `#include('view', $data)` | Partial includes with optional extra data |
| `#includeIf` | Include only if the view file exists |
| `#includeWhen` / `#includeUnless` | Conditionally include a partial |
| `#includeFirst` | Include the first resolvable view from a list |
| `#set($var = expr)` | Assign a variable inline |
| `#break` / `#continue` | Loop and switch control |
| `#php` / `#endphp` | Raw PHP blocks (sandbox-blocked) |
| `#dump` / `#dd` | Debug helpers (sandbox-blocked) |
| `<ComponentTag :prop="$val" />` | File-backed or class-backed components |
| `#props([name: type, name?: type])` | Typed prop declarations with runtime validation and PHPDoc generation |
| Named slots | `<slot name="header">…</slot>` |
| `$sharp->directive('name', fn)` | Compile-time custom directives |
| `$sharp->namespace('ns', $path)` | Namespace loader (`'ns::view'`) |
| Dependency graph | Auto-recompile when any dependency changes |
| Sandbox mode | Block dangerous PHP functions at compile time |

---

## Dev Mode & Debugging

Sharp includes a dev mode that injects source annotations into rendered HTML, enabling Chrome DevTools to trace any element back to its `.sp` template source.

**Enable via `sharp.config.json`** (recommended for local development):
```json
{
  "viewPath": "templates/",
  "sandbox": true,
  "devMode": true
}
```

**Enable via code** (takes priority over config):
```php
$sharp = new Sharp();
$sharp->setProduction(false); // dev mode — injects annotations (default)
$sharp->setProduction(true);  // production mode — no annotations
```

When dev mode is on, every HTML element in the rendered output will carry a `data-sharp-src` attribute:

```html
<!-- rendered output -->
<div data-sharp-src="/abs/path/templates/home.sp:8" class="user-card">
```

Sharp also writes `.sharp/ast/<hash>.ast` source-map files that the VSCode extension uses for Xdebug breakpoint translation.

**Jump to template source from Chrome:**

Add this bookmarklet to your browser to jump from a hovered element directly to its `.sp` source in VSCode:

```javascript
javascript:(function(){
  var el = document.querySelector(':hover');
  while(el && !el.dataset.sharpSrc) el = el.parentElement;
  if(el) location.href = 'vscode://file/' + el.dataset.sharpSrc;
})();
```

> Always set `"devMode": false` or call `$sharp->setProduction(true)` before deploying — dev mode exposes template file paths in public HTML.

---

## Documentation

| Guide | Description |
|---|---|
| [Getting Started](docs/getting-started.md) | Installation, config, first render |
| [Template Syntax](docs/syntax.md) | Echo, comments, `#if`, `#foreach`, `$loop`, `#while`, `#for`, `#switch`, includes, `#php`, `#dump` |
| [Layouts](docs/layouts.md) | `#extends`, `#section`, `#yield`, `#parent`, `#push`, `#prepend`, `#stack` |
| [Components](docs/components.md) | Props, slots, class-backed components |
| [Custom Directives](docs/directives.md) | Compile-time `#directives` |
| [Loaders](docs/loaders.md) | File, namespace, memory, custom loaders |
| [Cache & Dependency Graph](docs/cache.md) | How caching and invalidation work |
| [Security](docs/security.md) | Sandbox mode and trust boundaries |
| [API Reference](docs/api.md) | Full method and exception reference |
| [Architecture](docs/architecture.md) | Internals for contributors |
| [Changelog](docs/changelog.md) | Version history |

---

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) for setup instructions, coding standards, and the PR checklist.

```bash
git clone https://github.com/bynhan/sharp.git && cd sharp
composer install
./vendor/bin/phpunit --testdox
```

---

## Sponsors

Sharp is free, open-source software. If it saves you time, consider sponsoring its development.

<!-- sponsors -->

*No sponsors yet — [become the first!](https://github.com/sponsors/bynhan)*

<!-- /sponsors -->

---

## License

Sharp is open-sourced software licensed under the [MIT license](LICENSE).
