# Layouts

Sharp supports multi-level layout inheritance. A child template declares `#extends` and fills named `#section` blocks. The parent layout inserts them with `#yield`.

---

## Table of Contents

- [How it works](#how-it-works)
- [#extends](#extends)
- [#section & #endsection](#section--endsection)
- [#yield](#yield)
- [#parent](#parent)
- [Stacks — #push, #prepend, #stack](#stacks----push-prepend-stack)
- [Full example](#full-example)
- [Multi-level inheritance](#multi-level-inheritance)

---

## How it works

1. Child template runs first. Each `#section` captures its content into a named slot.
2. The layout file runs next, calling `#yield('name')` to inject the captured content.
3. Sharp resolves this as an iterative loop — not recursion — so there is no stack overflow risk with deep layout chains.

---

## `#extends`

Declares a parent layout. Must appear at the top of the template. The argument uses dot notation.

```html
#extends('layouts.main')
```

| Dot path | Resolved file |
|---|---|
| `'layouts.main'` | `templates/layouts/main.sp` |
| `'layouts.admin'` | `templates/layouts/admin.sp` |

> `#extends` cannot appear inside a `#if`, `#foreach`, or `#while` block. It must be at the root of the template.

---

## `#section` & `#endsection`

Defines a named block of content for the parent layout.

```html
#extends('layouts.main')

#section('title')My Page Title#endsection

#section('content')
  <h1>Welcome</h1>
  <p>Page content here.</p>
#endsection

#section('sidebar')
  <nav>
    <a href="/dashboard">Dashboard</a>
  </nav>
#endsection
```

Any content outside `#section` blocks is ignored in a child template.

---

## `#yield`

Used in a layout file. Inserts the named section's content.

```html
#yield('slot-name')
```

An optional second argument provides a **default value** used when the child does not define that section:

```html
#yield('title', 'My App')
#yield('footer', '<p>© 2025</p>')
```

The default value is output as raw HTML (not escaped).

---

## `#parent`

Inside a `#section`, `#parent` emits the **parent layout's version of that same section**. Use this to append to — rather than replace — inherited content.

**Layout `templates/layouts/main.sp`:**
```html
#section('scripts')
  <script src="/vendor/app.js"></script>
#endsection
```

**Child template:**
```html
#extends('layouts.main')

#section('scripts')
  #parent
  <script src="/page-specific.js"></script>
#endsection
```

**Rendered output:**
```html
<script src="/vendor/app.js"></script>
<script src="/page-specific.js"></script>
```

---

## Stacks — `#push`, `#prepend`, `#stack`

Stacks let **multiple templates contribute content to the same named slot**, accumulating items in order. This is the recommended way to inject per-page `<script>` and `<link>` tags into a shared layout without needing a single monolithic `#section`.

### `#stack`

Placed in a layout, `#stack` renders all content that has been pushed or prepended into a given slot name.

```html
<!-- templates/layouts/main.sp -->
<head>
  <link rel="stylesheet" href="/app.css">
  #stack('styles')
</head>
<body>
  #yield('content')
  <script src="/app.js"></script>
  #stack('scripts')
</body>
```

### `#push` / `#endpush`

Appends content to the named stack. Multiple pushes accumulate in the order they are executed.

```html
<!-- templates/pages/dashboard.sp -->
#extends('layouts.main')

#section('content')
  <canvas id="chart"></canvas>
#endsection

#push('scripts')
  <script src="/chart.js"></script>
#endpush
```

A `#push` from a partial included by the page is also collected:

```html
<!-- templates/partials/widget.sp -->
#push('styles')
  <link rel="stylesheet" href="/widget.css">
#endpush
<div class="widget">…</div>
```

```html
<!-- templates/pages/dashboard.sp -->
#extends('layouts.main')

#section('content')
  #include('partials.widget')
#endsection
```

The layout's `#stack('styles')` will contain the `<link>` tag injected by the partial.

### `#prepend` / `#endprepend`

Inserts content **before** items already in the stack. Useful for ensuring vendor scripts or base styles appear before page-specific additions.

```html
#prepend('scripts')
  <script src="/vendor/polyfill.js"></script>
#endprepend

#push('scripts')
  <script src="/app.js"></script>
#endpush
```

**Rendered `#stack('scripts')`:**
```html
<script src="/vendor/polyfill.js"></script>
<script src="/app.js"></script>
```

### Ordering rules

| Operation | Effect |
|---|---|
| `#push('name')` | Appends to the end of the stack |
| `#prepend('name')` | Inserts at the front of the stack |
| Multiple `#push` calls | Items appear in execution order (top-to-bottom) |
| `#stack('name')` on an empty stack | Renders an empty string (no error) |

### Stacks vs `#section`

| | `#section` / `#yield` | `#push` / `#prepend` / `#stack` |
|---|---|---|
| Contributors | One child template "wins" | Many templates can all contribute |
| Override behaviour | Child overrides parent | Contributions accumulate |
| Typical use | Main content blocks | CSS / JS injection, meta tags |

---

## Full example

**`templates/layouts/main.sp`**
```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>#yield('title', 'Sharp App')</title>
  <link rel="stylesheet" href="/app.css">
  #yield('head')
</head>
<body>
  <header>
    #include('partials.nav')
  </header>

  <main>
    #yield('content')
  </main>

  <footer>
    #yield('footer', '<p>© 2025</p>')
  </footer>

  <script src="/app.js"></script>
  #yield('scripts')
</body>
</html>
```

**`templates/pages/dashboard.sp`**
```html
#extends('layouts.main')

#section('title')Dashboard — Sharp App#endsection

#section('head')
  <meta name="description" content="User dashboard">
#endsection

#section('content')
  <h1>Welcome, {{ $user->name }}!</h1>

  #foreach($notifications as $n)
    <div class="notification {{ $loop->first ? 'first' : '' }}">
      {{ $n->message }}
    </div>
  #endforeach
#endsection

#section('scripts')
  #parent
  <script src="/dashboard.js"></script>
#endsection
```

**PHP:**
```php
echo $sharp->render('pages.dashboard', [
    'user'          => $user,
    'notifications' => $notifications,
]);
```

---

## Multi-level inheritance

Layouts can themselves extend other layouts.

```
layouts/base.sp       ← outermost HTML shell
  └── layouts/main.sp ← adds sidebar, header
        └── pages/dashboard.sp ← page-specific content
```

**`templates/layouts/base.sp`**
```html
<!DOCTYPE html>
<html>
<body>
  #yield('body')
</body>
</html>
```

**`templates/layouts/main.sp`**
```html
#extends('layouts.base')

#section('body')
  <nav>…</nav>
  <main>#yield('content')</main>
#endsection
```

**`templates/pages/home.sp`**
```html
#extends('layouts.main')

#section('content')
  <h1>Home page</h1>
#endsection
```

Sharp resolves the full chain iteratively — no recursion.
