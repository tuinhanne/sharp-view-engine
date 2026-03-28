# Template Syntax

Sharp templates use the `.sp` extension. Directives start with `#`. Expressions inside `{{ }}` and directive arguments are **plain PHP** — no dot-notation translator, no hidden magic.

---

## Table of Contents

- [Outputting Variables](#outputting-variables)
- [Raw (Unescaped) Output](#raw-unescaped-output)
- [Comments](#comments)
- [Conditionals](#conditionals)
- [Loops](#loops)
  - [foreach](#foreach)
  - [$loop variable](#loop-variable)
  - [Nested loops](#nested-loops)
  - [while](#while)
  - [for](#for)
- [Switch](#switch)
- [Includes](#includes)
  - [include](#include)
  - [includeIf](#includeif)
  - [includeWhen / includeUnless](#includewhen--includeunless)
  - [includeFirst](#includefirst)
- [Raw PHP](#raw-php)
- [Debugging](#debugging)

---

## Outputting Variables

`{{ expression }}` echoes a value with automatic HTML escaping via `htmlspecialchars`.

```html
{{ $user->name }}
{{ strtoupper($user->email) }}
{{ count($items) }} items
{{ $active ? 'Yes' : 'No' }}
{{ number_format($price, 2) }}
```

---

## Raw (Unescaped) Output

`{!! expression !!}` outputs HTML without escaping. Only use this for trusted content.

```html
<div class="body">
  {!! $post->html_body !!}
</div>
```

---

## Comments

Standard HTML comment syntax. Comments are **stripped at compile time** — they never appear in the rendered HTML, not even as HTML comments.

```html
<!-- This is a Sharp comment. It will not appear in the output. -->
<p>This text is visible.</p>
```

---

## Conditionals

### `#if` / `#elseif` / `#else` / `#endif`

```html
#if($user->isAdmin)
  <p>Admin panel</p>
#elseif($user->isModerator)
  <p>Moderator tools</p>
#else
  <p>Regular user</p>
#endif
```

Compiles directly to PHP `if/elseif/else/endif` — no wrapping or overhead.

You can use any PHP expression:

```html
#if(count($errors) > 0)
  <ul>
    #foreach($errors as $error)
      <li>{{ $error }}</li>
    #endforeach
  </ul>
#endif

#if($user !== null && $user->verified)
  <span class="badge">Verified</span>
#endif
```

---

## Loops

### `foreach`

```html
<ul>
  #foreach($posts as $post)
    <li>{{ $post->title }}</li>
  #endforeach
</ul>
```

Key-value syntax:

```html
#foreach($config as $key => $value)
  <dt>{{ $key }}</dt>
  <dd>{{ $value }}</dd>
#endforeach
```

Any iterable (array, `Generator`, `Traversable`) is accepted:

```html
#foreach($repository->active() as $item)
  ...
#endforeach
```

---

### `$loop` variable

Inside every `#foreach` block, Sharp automatically injects a `$loop` object with 10 properties:

| Property | Type | Description |
|---|---|---|
| `$loop->index` | `int` | 0-based index of the current iteration |
| `$loop->iteration` | `int` | 1-based iteration number |
| `$loop->count` | `int` | Total number of items |
| `$loop->remaining` | `int` | Iterations remaining after the current one |
| `$loop->first` | `bool` | `true` on the first iteration |
| `$loop->last` | `bool` | `true` on the last iteration |
| `$loop->even` | `bool` | `true` when index is even (0, 2, 4 …) |
| `$loop->odd` | `bool` | `true` when index is odd (1, 3, 5 …) |
| `$loop->depth` | `int` | Nesting depth — `1` for the outermost loop |
| `$loop->parent` | `LoopVariable\|null` | The outer loop's `$loop`, or `null` if not nested |

**Counter / progress:**

```html
#foreach($steps as $step)
  <p>Step {{ $loop->iteration }} of {{ $loop->count }}: {{ $step->label }}</p>
  <progress value="{{ $loop->iteration }}" max="{{ $loop->count }}"></progress>
#endforeach
```

**First / last:**

```html
#foreach($items as $item)
  #if($loop->first)<ul>#endif
    <li>{{ $item->name }}</li>
  #if($loop->last)</ul>#endif
#endforeach
```

**Alternating rows (striped tables):**

```html
#foreach($rows as $row)
  <tr class="{{ $loop->even ? 'bg-white' : 'bg-gray-50' }}">
    <td>{{ $row->name }}</td>
    <td>{{ $row->value }}</td>
  </tr>
#endforeach
```

**Remaining items:**

```html
#foreach($queue as $job)
  <p>Processing {{ $job->name }} ({{ $loop->remaining }} left)…</p>
#endforeach
```

---

### Nested loops

Each nested `#foreach` gets its own `$loop`. The inner loop can access the outer loop via `$loop->parent`.

```html
#foreach($categories as $category)
  <h2>{{ $category->name }} (section {{ $loop->iteration }})</h2>

  #foreach($category->items as $item)
    <p>
      {{ $loop->parent->iteration }}.{{ $loop->iteration }}
      — {{ $item->name }}
    </p>
    <!-- renders "1.1", "1.2", "2.1", ... -->
  #endforeach
#endforeach
```

After each `#foreach` ends, `$loop` is **automatically restored** to the outer loop (`null` at the top level). Variable names are scoped with unique IDs at compile time — there is no collision risk regardless of nesting depth.

`$loop->depth` reflects the nesting level:

```html
#foreach($a as $x)
  <!-- $loop->depth === 1 -->
  #foreach($b as $y)
    <!-- $loop->depth === 2 -->
  #endforeach
#endforeach
```

---

### `while`

```html
#while($queue->isNotEmpty())
  <p>{{ $queue->dequeue() }}</p>
#endwhile
```

> There is no `$loop` variable inside `#while`.

---

### `for`

Standard C-style for loop.

```html
#for($i = 0; $i < 10; $i++)
  <p>Item {{ $i }}</p>
#endfor
```

```html
#for($i = count($items) - 1; $i >= 0; $i--)
  <p>{{ $items[$i] }}</p>
#endfor
```

---

## Switch

### `#switch` / `#case` / `#default` / `#endswitch`

Use `#break` inside each case to prevent fall-through.

```html
#switch($user->role)
  #case('admin')
    <p>Administrator</p>
  #break
  #case('editor')
    <p>Editor</p>
  #break
  #default
    <p>Regular user</p>
#endswitch
```

**Fall-through** (multiple cases sharing the same body):

```html
#switch($status)
  #case('active')
  #case('enabled')
    <span class="badge-green">Active</span>
  #break
  #case('inactive')
  #case('disabled')
    <span class="badge-red">Inactive</span>
  #break
  #default
    <span class="badge-gray">Unknown</span>
#endswitch
```

Case values are plain PHP expressions — strings, integers, variables, or expressions:

```html
#switch($score)
  #case(100)
    <p>Perfect!</p>
  #break
  #case($passingScore)
    <p>Passing</p>
  #break
  #default
    <p>Below passing</p>
#endswitch
```

---

## Includes

### `#include`

Include a partial template. All variables from the current scope are available in the included view.

```html
#include('partials.header')
#include('partials.footer')
```

**Passing extra data** — a PHP array expression merged into the included view's scope:

```html
#include('partials.nav', ['active' => 'home'])
#include('partials.user-card', ['user' => $author, 'compact' => true])
```

The extra data is merged on top of the current scope — existing variables are not replaced unless the array explicitly overrides them.

---

### `#includeIf`

Include a partial only if the view file exists. Silently skips if it cannot be resolved (no exception).

```html
#includeIf('partials.sidebar')
#includeIf('partials.sidebar', ['collapsed' => true])
```

Useful for optional partials that may not be present in all themes or configurations.

---

### `#includeWhen` / `#includeUnless`

Conditionally include a partial based on a PHP expression.

```html
<!-- Include only when condition is true -->
#includeWhen($user->isAdmin, 'partials.admin-bar')

<!-- Include only when condition is false -->
#includeUnless($user->isGuest, 'partials.user-nav')
```

All variables from the current scope are passed to the included view automatically.

### `#includeFirst`

Include the first view from a list that can be resolved. Useful for overridable partials.

```html
#includeFirst(['theme.nav', 'partials.nav'])
```

Sharp tries each view in order and renders the first one found. Throws a `RenderException` if none exist.

---

## Raw PHP

### `#php` / `#endphp`

Embed raw PHP code directly into a template. Useful for variable preparation or imperative logic that cannot be expressed with directives.

```html
#php
  $formatted = number_format($price, 2, '.', ',');
  $cssClass  = $stock > 0 ? 'in-stock' : 'out-of-stock';
#endphp

<p class="{{ $cssClass }}">{{ $formatted }}</p>
```

> **Sandbox mode:** `#php` blocks are **blocked** when `sandbox: true` is set in `sharp.config.json`. Use custom directives instead for sandboxed environments.

---

## Debugging

### `#dump` / `#dd`

Inspect variable values during development.

```html
#dump($user)
#dump($items)
```

```html
<!-- Dump and die — stops execution -->
#dd($request)
```

`#dump` calls `var_dump()`. `#dd` calls `var_dump()` then `die()`.

> **Sandbox mode:** Both `#dump` and `#dd` are **blocked** in sandbox mode and throw a `CompileException`.
