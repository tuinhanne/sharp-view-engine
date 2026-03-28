# Custom Directives

Custom directives allow you to extend Sharp's template syntax with your own `#name()` tags. They are resolved and **inlined at compile time** — the callable runs once during compilation, never at render time. Zero runtime overhead per call.

---

## Table of Contents

- [Registering a directive](#registering-a-directive)
- [Argument handling](#argument-handling)
- [Multi-argument directives](#multi-argument-directives)
- [No-argument directives](#no-argument-directives)
- [Examples](#examples)
- [How compilation works](#how-compilation-works)

---

## Registering a directive

```php
$sharp->directive(string $name, callable $handler): self;
```

The `$handler` receives the raw argument string(s) and must return a **valid PHP code string** (including `<?php ?>` tags if needed).

```php
$sharp->directive('money', fn($e) =>
    "<?php echo number_format({$e}, 2) . ' ₫'; ?>"
);
```

Registration returns `$this`, so calls can be chained:

```php
$sharp
    ->directive('money',      fn($e) => "<?php echo number_format({$e}, 2) . ' ₫'; ?>")
    ->directive('formatDate', fn($d, $fmt = "'d/m/Y'") => "<?php echo date({$fmt}, strtotime({$d})); ?>")
    ->directive('csrf',       fn() => "<?php echo csrf_field(); ?>");
```

---

## Argument handling

Sharp parses the argument list respecting nested parentheses and string literals, then passes each argument as a separate parameter to your callable.

```html
<!-- Single argument -->
#money($product->price)
```

```php
$sharp->directive('money', fn($e) =>
    "<?php echo number_format({$e}, 2); ?>"
);
// Compiles to: <?php echo number_format($product->price, 2); ?>
```

The argument is the **raw PHP expression** from the template — use it directly inside the returned PHP string.

---

## Multi-argument directives

```html
#formatDate($post->created_at, 'd/m/Y')
#formatDate($post->created_at)
```

```php
$sharp->directive('formatDate', function (string $date, string $format = "'d/m/Y'") {
    return "<?php echo date({$format}, strtotime({$date})); ?>";
});
```

Default parameter values apply when the argument is omitted.

---

## No-argument directives

```html
#csrf
#debugBar
```

```php
$sharp->directive('csrf', fn() =>
    "<input type=\"hidden\" name=\"_token\" value=\"<?php echo csrf_token(); ?>\">"
);

$sharp->directive('debugBar', fn() =>
    "<?php if (app()->isLocal()): ?><div id='debug-bar'>...</div><?php endif; ?>"
);
```

---

## Examples

### Currency formatting

```php
$sharp->directive('money', fn($amount, $currency = "'VND'") =>
    "<?php echo number_format({$amount}, 0, ',', '.') . ' ' . {$currency}; ?>"
);
```

```html
<td>#money($item->price)</td>
<td>#money($item->price, 'USD')</td>
```

### Date formatting

```php
$sharp->directive('date', fn($ts, $fmt = "'d/m/Y'") =>
    "<?php echo date({$fmt}, is_int({$ts}) ? {$ts} : strtotime({$ts})); ?>"
);
```

```html
<time>#date($post->published_at)</time>
<time>#date($post->published_at, 'Y-m-d')</time>
```

### Role-based visibility

```php
$sharp->directive('can', fn($permission) =>
    "<?php if (auth()->user()?->can({$permission})): ?>"
);

$sharp->directive('endcan', fn() =>
    "<?php endif; ?>"
);
```

```html
#can('edit-posts')
  <a href="/edit">Edit</a>
#endcan
```

### Environment checks

```php
$sharp->directive('env', fn($env) =>
    "<?php if (app()->environment({$env})): ?>"
);
$sharp->directive('endenv', fn() =>
    "<?php endif; ?>"
);
```

```html
#env('local')
  <div class="debug-banner">Development mode</div>
#endenv
```

### CSRF / Forms

```php
$sharp->directive('method', fn($m) =>
    "<input type=\"hidden\" name=\"_method\" value=\"<?php echo strtoupper({$m}); ?>\">"
);

$sharp->directive('csrf', fn() =>
    "<input type=\"hidden\" name=\"_token\" value=\"<?php echo csrf_token(); ?>\">"
);
```

```html
<form method="POST" action="/posts/{{ $post->id }}">
  #csrf
  #method('PUT')
  ...
</form>
```

---

## How compilation works

Given this registration:

```php
$sharp->directive('money', fn($e) => "<?php echo number_format({$e}, 2); ?>");
```

And this template:

```html
Price: #money($product->price)
```

The compiler inlines the PHP code directly:

```php
// Compiled output:
Price: <?php echo number_format($product->price, 2); ?>
```

The directive callable is **never called at render time**. It runs exactly once, at compile time, and the result is cached. Subsequent renders just `include` the pre-compiled file.
