# Architecture

This document describes the internal design of Sharp for contributors.

---

## Table of Contents

- [Overview](#overview)
- [Compilation pipeline](#compilation-pipeline)
- [Lexer](#lexer)
- [Parser](#parser)
- [AST nodes](#ast-nodes)
- [Pipeline stages](#pipeline-stages)
- [Runtime](#runtime)
- [Cache strategy](#cache-strategy)
- [Key design decisions](#key-design-decisions)

---

## Overview

Sharp compiles `.sp` templates to plain PHP files through a multi-stage AST pipeline. Compiled files are cached and reused until a source dependency changes.

```
.sp source
    │
    ▼
  Lexer                  char-by-char → Token[]
    │
    ▼
  Parser                 Token[] → AST (RootNode)
    │
    ▼
  DependencyGraph        AST walk → record deps + mtimes → .sharp/graph/
    │
    ▼
  AstValidator           sandbox + structural checks
    │
    ▼
  OptimizePass           merge adjacent TextNodes, strip CommentNodes
    │
    ▼
  CodeGeneration         Node::compile(ctx) → PHP string
    │
    ▼
  FileCache              atomic write → .sharp/compiled/{md5}.php
    │
    ▼
  include()              execute in isolated scope with $__env
```

---

## Lexer

**`src/Compiler/Lexer/Lexer.php`**

The lexer processes the source string character-by-character using a mode stack. No regex is used for structural parsing (only for detecting things like Windows drive letters in paths).

**Modes:**
- `TEXT` — default; accumulate raw content
- `ECHO` — inside `{{ }}`
- `RAW_ECHO` — inside `{!! !!}`
- `COMMENT` — inside `<!-- -->`
- `DIRECTIVE` — after `#`, reading directive name and args
- `COMPONENT` — inside `<Tag ... />`
- `SLOT` — inside `<slot name="...">`

**Output:** `Token[]` — each token has a `TokenType`, a value, and a source line number.

`TokenType` is a PHP 8.1 backed enum (`enum TokenType: string`).

---

## Parser

**`src/Compiler/Parser/Parser.php`**

The parser transforms `Token[]` into a tree of `Node` objects. It uses an **explicit stack** (an array) instead of recursive descent, eliminating any risk of PHP's call-stack limit being hit on deeply nested templates.

Stack entries track:
- The node type being built (`'if'`, `'foreach'`, `'section'`, etc.)
- Saved child lists (for `#elseif`/`#else` branching)
- Current branch condition
- Current parser phase

**Directive dispatch** uses a `match` expression on the directive name. Unknown names produce a `DirectiveNode` (resolved at code generation against the `DirectiveRegistry`).

### IfNode construction

`#if` / `#elseif` / `#else` / `#endif` go through four state transitions:

```
openIf()       → push stack entry, begin collecting children for first branch
branchElseif() → save current branch, start new branch with new condition
branchElse()   → save current branch, start collecting else children
closeIf()      → pop stack, assemble IfNode with all branches + elseChildren
```

### Yield with default

`#yield('name', 'default')` splits on the top-level comma using `splitTopLevelComma()` (respects nested parentheses and string literals), then strips quotes from each argument.

---

## AST nodes

Every node extends `Sharp\Compiler\Ast\Node` and implements:

```php
abstract public function compile(CompilationContext $ctx): string;
```

| Node | Generated PHP |
|---|---|
| `TextNode` | Literal string (no PHP tags) |
| `EchoNode` | `<?php echo htmlspecialchars($expr, ENT_QUOTES, 'UTF-8'); ?>` |
| `RawEchoNode` | `<?php echo $expr; ?>` |
| `IfNode` | `<?php if (...): ?> ... <?php elseif (...): ?> ... <?php endif; ?>` |
| `ForeachNode` | Buffer → `$loop` setup → `foreach` loop → `$loop` restore |
| `WhileNode` | `<?php while (...): ?> ... <?php endwhile; ?>` |
| `SectionNode` | `$__env->startSection('name'); ... $__env->stopSection();` |
| `YieldNode` | `<?php echo $__env->yieldSection('name', 'default'); ?>` |
| `ExtendsNode` | `<?php $__env->extends('view'); ?>` |
| `ParentNode` | `<?php echo $__env->yieldParentContent(); ?>` |
| `IncludeNode` | `<?php echo $__env->renderInclude('view', get_defined_vars()); ?>` |
| `ComponentNode` | `<?php echo $__env->renderComponent('Name', $props, $slots); ?>` |
| `DirectiveNode` | Inlined PHP returned by the directive callable |
| `CommentNode` | `""` (empty — stripped by OptimizePass before reaching here) |

### ForeachNode and `$loop`

Each `ForeachNode` is assigned a **unique integer UID** via a static counter (`private static int $counter`). This UID is used to suffix all loop-scoped PHP variables:

```php
$__lp{uid}   // saved outer $loop reference
$__tmp{uid}  // buffered iterable (array or iterator_to_array result)
$__fc{uid}   // final array
$__cc{uid}   // count
$__fi{uid}   // iteration counter
```

This eliminates naming collisions in arbitrarily deep nested loops.

---

## Pipeline stages

### DependencyGraph

Walks the AST and records every dependency:
- `ExtendsNode` → parent layout view name
- `IncludeNode` → included partial view name
- `ComponentNode` → component view name (file-backed only)

For each dependency, `LoaderInterface::lastModified()` is called and the mtime is stored in `.sharp/graph/{md5}.json`.

### AstValidator

Recursive AST walk with two modes controlled by `sandbox` config:

1. **Always-on structural checks** — `#extends` / `#section` not inside control flow
2. **Sandbox checks** (when `sandbox: true`) — blocked function names in expressions

Throws `CompileException` on violation. Runs before code generation so no PHP is ever written for invalid templates.

### OptimizePass

Single-pass AST transformation:

- Merges adjacent `TextNode` siblings into one (reduces PHP tag switching overhead)
- Removes `CommentNode` objects entirely

### CodeGeneration

Calls `$node->compile($ctx)` on the root node, which recursively compiles children. Returns the full PHP source string.

`CompilationContext` carries:
- `DirectiveRegistry` — for resolving `DirectiveNode`
- `ComponentRegistry` — for resolving `ComponentNode` (validates existence)
- `Config` — for sandbox flag and path resolution

---

## Runtime

### Environment

**`src/Runtime/Environment.php`**

`$__env` is injected into every compiled template via `include`. It provides the bridge between compiled PHP output and the runtime systems:

| Method | Purpose |
|---|---|
| `extends(string $view)` | Records layout to inherit from |
| `startSection(string $name)` | Begins output buffering for a section |
| `stopSection()` | Ends buffering, stores section content |
| `yieldSection(string $name, string $default)` | Returns captured section content or default |
| `yieldParentContent()` | Returns parent layout's version of current section |
| `renderInclude(string $view, array $vars)` | Compiles + renders an included partial |
| `renderComponent(string $name, array $props, array $slots)` | Renders a component |
| `callDirective(string $name, array $args)` | (dev-time only) resolves registered directives |

### Layout resolution

`Sharp::render()` resolves layout chains iteratively:

```php
$view = $requestedView;
while (true) {
    $compiled = $this->compile($view);
    $html     = $this->executeTemplate($compiled, $data);
    $parent   = $env->getExtendsTarget(); // null if no #extends
    if ($parent === null) break;
    $view = $parent;
}
return $html;
```

No recursion. No stack overflow with deep layout chains.

### Template execution scope

```php
private function executeTemplate(string $compiledPath, array $data): string
{
    $__env = $this->environment;   // injected
    extract($data, EXTR_SKIP);     // variables available in template
    ob_start();
    include $compiledPath;
    return (string) ob_get_clean();
}
```

`EXTR_SKIP` prevents user data from overwriting `$__env`.

---

## Cache strategy

- Cache key = `md5($viewName)` — O(1) lookup regardless of view path depth
- Compiled files: `.sharp/compiled/{md5}.php`
- Dependency graphs: `.sharp/graph/{md5}.json`
- Validity check: compare source mtime + all dependency mtimes against compiled file mtime
- Atomic writes: `tempnam()` → write → `@unlink(final)` → `rename(tmp, final)` (Windows-safe)

---

## Key design decisions

### No expression compiler

Expressions in `{{ }}`, `#if()`, `#foreach()` etc. are passed through to PHP verbatim. There is no expression language, no dot-notation translator, no DSL. Templates use standard PHP. This keeps the compiler simple, fast, and predictable.

### Compile-time directives

`$sharp->directive(name, callable)` — the callable is invoked once per call site at compile time. The returned PHP string is inlined into the compiled output. At render time there is no function call, no registry lookup, no overhead.

### Explicit-stack parser

The parser maintains its own stack (`array`) rather than using PHP's call stack via recursion. This means a template with 10,000 nested `#if` blocks will not crash with "maximum function nesting level reached". The stack depth limit is memory, not PHP's recursion limit.

### Iterative layout resolution

Layout inheritance (`#extends`) is resolved as a `while` loop after template execution, not during compilation or via recursion. Each level runs as a separate `include` with its own scope. Three levels of layout nesting = three `include` calls.

### Unique UIDs for `#foreach`

`ForeachNode` uses a static counter (`private static int $counter`) to give each node instance a unique integer ID. All loop-scoped temporary PHP variables use this ID as a suffix. This means arbitrarily nested `#foreach` blocks never share variable names, regardless of nesting depth or template reuse.
