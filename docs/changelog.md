# Changelog

All notable changes to Sharp are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Sharp uses [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

### Added
- `#push('name')` / `#endpush` — appends content to a named stack; multiple pushes accumulate in order
- `#prepend('name')` / `#endprepend` — inserts content at the front of a named stack
- `#stack('name')` — renders all content accumulated in a named stack (empty string if nothing was pushed)
- Stacks are independent from sections: many templates can contribute; `#section` / `#yield` remains single-winner
- `$loop` variable inside `#foreach` with 10 properties: `index`, `iteration`, `count`, `remaining`, `first`, `last`, `even`, `odd`, `depth`, `parent`
- `$loop->parent` reference for accessing outer loop in nested `#foreach` blocks
- Unique UID per `ForeachNode` to prevent nested-loop variable collisions
- `Sharp\Runtime\LoopVariable` class

### Changed
- `#foreach` now buffers the iterable to an array before iterating, enabling `$loop->count` and `$loop->remaining`

### Removed
- `#loop` / `#endloop` directive (replaced by `$loop` variable in `#foreach`)
- `LoopNode` AST node

---

## [0.1.0] — Initial release

### Added
- AST compilation pipeline: Lexer → Parser → DependencyGraph → AstValidator → OptimizePass → CodeGeneration → FileCache
- `#if` / `#elseif` / `#else` / `#endif` conditionals
- `#foreach` / `#endforeach` loops
- `#while` / `#endwhile` loops
- `{{ expression }}` — HTML-escaped output
- `{!! expression !!}` — raw unescaped output
- `<!-- comment -->` — stripped at compile time
- Layout system: `#extends`, `#section`, `#endsection`, `#yield`, `#parent`
- `#include` for partials
- Component system: file-backed (`.sp`) and class-backed (PHP class)
- PascalCase and kebab-case component tags with static + dynamic (`:prop`) props
- Named slots (`<slot name="...">`) and default slot (`$slot`)
- Custom compile-time directives via `$sharp->directive(name, callable)`
- Namespace loader (`'admin::dashboard'` syntax)
- Memory loader for in-memory templates
- File loader with dot notation (`'pages.home'` → `templates/pages/home.sp`)
- Dependency graph cache in `.sharp/graph/` for automatic cache invalidation
- Atomic compiled file writes (tempnam + rename, Windows-safe)
- `sharp.config.json` auto-creation with `viewPath` and `sandbox` keys
- Sandbox mode: blocks dangerous PHP functions at compile time
- Structural validation: `#extends` / `#section` cannot be inside control flow
- PHP 8.1+ — backed enums, `readonly` properties, named arguments, `match` expressions
- PSR-4 autoloading: `Sharp\\` → `src/`
- PHPUnit 10 test suite (unit + integration)
