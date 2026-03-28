# Security

Sharp's security model has two layers: the **sandbox** (configurable) and **structural validation** (always enforced).

---

## Table of Contents

- [Sandbox mode](#sandbox-mode)
- [Blocked functions](#blocked-functions)
- [Structural validation](#structural-validation)
- [Disabling the sandbox](#disabling-the-sandbox)
- [Notes on trust boundaries](#notes-on-trust-boundaries)

---

## Sandbox mode

When `"sandbox": true` (the default), Sharp's `AstValidator` inspects every `{{ expression }}` and directive argument in the compiled AST. If a blocked function appears, a `CompileException` is thrown before any PHP is generated.

Enable or disable in `sharp.config.json`:

```json
{
    "viewPath": "templates/",
    "sandbox": true
}
```

---

## Blocked functions

The following functions are blocked when sandbox mode is enabled:

| Category | Functions |
|---|---|
| Shell execution | `exec`, `shell_exec`, `system`, `passthru`, `popen`, `proc_open` |
| Code evaluation | `eval`, `assert`, `preg_replace` (with `/e` modifier) |
| File system | `file_get_contents`, `file_put_contents`, `file`, `unlink`, `rmdir`, `rename`, `mkdir` |
| Encoding | `base64_decode`, `hex2bin` |

**Example — sandbox blocks:**

```html
<!-- CompileException: exec() is not allowed in sandbox mode -->
{{ exec('rm -rf /') }}

<!-- CompileException -->
#if(file_get_contents('/etc/passwd'))
```

**Example — sandbox allows:**

```html
{{ strtoupper($name) }}
{{ number_format($price, 2) }}
{{ date('Y-m-d', $timestamp) }}
{{ implode(', ', $tags) }}
```

Standard string, math, array, and date functions are allowed.

---

## Structural validation

These checks run **regardless of sandbox setting** and cannot be disabled:

| Rule | Error thrown |
|---|---|
| `#extends` inside a control block (`#if`, `#foreach`, `#while`) | `CompileException` |
| `#section` inside a control block | `CompileException` |
| Unclosed block (e.g. `#if` without `#endif`) | `ParseException` |
| Unknown directive (not registered) | `CompileException` |

**Example — structural error:**

```html
#if($condition)
  #extends('layouts.main')   <!-- CompileException: #extends cannot appear inside a block -->
#endif
```

```html
#if($x)
  content
<!-- ParseException: unclosed #if block -->
```

---

## Disabling the sandbox

Set `"sandbox": false` in `sharp.config.json` for fully trusted template environments (e.g. templates authored by your own developers, not user input):

```json
{
    "viewPath": "templates/",
    "sandbox": false
}
```

> **Warning:** Never disable the sandbox for templates that contain user-provided content.

---

## Notes on trust boundaries

Sharp's sandbox is a **compile-time check**, not a runtime jail. It inspects the AST before PHP is generated.

**What this means:**
- If a template passes the sandbox check, the compiled PHP runs with full PHP privileges (the web server user's access level).
- The sandbox catches obvious dangerous calls. It does not make templates safe for arbitrary user input.
- **Templates should be treated as code**, not content. Do not allow end users to write or modify `.sp` files in production.

**What the sandbox does NOT protect against:**
- Variable values containing malicious data (always use `{{ }}` escaped output for user data, never `{!! !!}`)
- PHP code in dynamic expressions that the validator cannot statically analyse
- Template injection via unvalidated view names passed to `render()`

For user-generated content, always use `{{ $variable }}` (HTML-escaped output), never `{!! $variable !!}`.
