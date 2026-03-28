# API Reference

---

## `Sharp`

The main entry point. `use Sharp\Sharp;`

---

### `__construct(?string $rootDir = null)`

Creates a new Sharp instance.

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$rootDir` | `?string` | `getcwd()` | Project root — where `sharp.config.json` is read/created and relative paths are resolved from. |

```php
$sharp = new Sharp();                // uses getcwd()
$sharp = new Sharp(__DIR__);         // current file's directory
$sharp = new Sharp('/var/www/app');  // explicit absolute path
```

---

### `render(string $view, array $data = []): string`

Compiles (if needed) and renders a template, returning the full HTML string.

| Parameter | Type | Description |
|---|---|---|
| `$view` | `string` | View name in dot notation (`'pages.home'`) or with namespace prefix (`'admin::dashboard'`) |
| `$data` | `array` | Variables to make available in the template |

```php
$html = $sharp->render('home');
$html = $sharp->render('pages.profile', ['user' => $user]);
$html = $sharp->render('admin::users.index', compact('users', 'pagination'));
```

**Throws:**
- `Sharp\Exception\RenderException` — view not found or template execution error
- `Sharp\Exception\CompileException` — sandbox violation or invalid template structure
- `Sharp\Exception\ParseException` — unclosed blocks or malformed syntax

---

### `directive(string $name, callable $handler): self`

Registers a compile-time directive.

| Parameter | Type | Description |
|---|---|---|
| `$name` | `string` | Directive name, without the `#` prefix |
| `$handler` | `callable` | Receives argument strings, returns a PHP code string |

Returns `$this` for chaining.

```php
$sharp
    ->directive('money', fn($e) => "<?php echo number_format({$e}, 2); ?>")
    ->directive('csrf',  fn()  => "<input type=\"hidden\" name=\"_token\" value=\"<?php echo csrf_token(); ?>\">");
```

> Directives must be registered **before** the first `render()` call that uses them, since compilation is cached.

---

### `component(string $name, string $target): self`

Registers a component.

| Parameter | Type | Description |
|---|---|---|
| `$name` | `string` | Component name (PascalCase or kebab-case) |
| `$target` | `string` | Absolute path to a `.sp` file, or a fully-qualified PHP class name |

Returns `$this` for chaining.

```php
$sharp
    ->component('Alert',     '/path/to/views/components/alert.sp')
    ->component('DataTable', App\Components\DataTable::class);
```

---

### `namespace(string $ns, string $path): self`

Adds a namespace mapping for the namespace loader.

| Parameter | Type | Description |
|---|---|---|
| `$ns` | `string` | Namespace prefix (used as `'ns::view'`) |
| `$path` | `string` | Absolute path to the namespace's view directory |

Returns `$this` for chaining.

```php
$sharp
    ->namespace('admin', '/path/to/admin-views')
    ->namespace('mail',  '/path/to/email-templates');
```

---

### `setProduction(bool $isProduction): static`

Controls whether the engine runs in **production** or **dev** mode.

| Parameter | Type | Description |
|---|---|---|
| `$isProduction` | `bool` | `true` = production (no annotations, no `.ast` files); `false` = dev mode (annotations + source maps) |

Returns `$this` for chaining.

**Priority chain** (highest → lowest):
1. `setProduction()` — explicit call in code
2. `"devMode"` field in `sharp.config.json`
3. Default: **dev mode on** (`false`)

```php
// Local development — dev mode (default, can be omitted)
$sharp->setProduction(false);

// Production deploy — disable annotations and source maps
$sharp->setProduction(true);

// Or control via config file:
// sharp.config.json: { "devMode": false }
```

**Dev mode effects:**
- Injects `data-sharp-src="path/to/file.sp:8"` on every HTML element in rendered output
- Writes `.sharp/ast/<hash>.ast` source-map files mapping `.sp` lines → compiled PHP lines
- These annotations let Chrome DevTools trace elements back to their template source

> Do not leave `devMode: true` (or `setProduction(false)`) in production. The injected attributes expose your template file paths in public HTML.

---

### `addLoader(LoaderInterface $loader): self`

Adds a custom loader with the highest priority.

| Parameter | Type | Description |
|---|---|---|
| `$loader` | `LoaderInterface` | A loader implementing `Sharp\Contract\LoaderInterface` |

Returns `$this` for chaining.

```php
$sharp->addLoader(new App\Loaders\DatabaseLoader($pdo));
```

---

## `LoopVariable`

`Sharp\Runtime\LoopVariable` — automatically injected as `$loop` inside `#foreach` blocks.

| Property | Type | Readonly | Description |
|---|---|---|---|
| `index` | `int` | no | 0-based current index |
| `iteration` | `int` | no | 1-based iteration number |
| `count` | `int` | **yes** | Total item count |
| `remaining` | `int` | no | Items remaining after current |
| `first` | `bool` | no | `true` on first iteration |
| `last` | `bool` | no | `true` on last iteration |
| `even` | `bool` | no | `true` when index is even |
| `odd` | `bool` | no | `true` when index is odd |
| `depth` | `int` | **yes** | Nesting depth (1 = outermost) |
| `parent` | `?self` | **yes** | Outer loop's `$loop`, or `null` |

---

## Exceptions

| Exception | Thrown when |
|---|---|
| `Sharp\Exception\ParseException` | Lexer/parser error — unclosed blocks, malformed syntax |
| `Sharp\Exception\CompileException` | Sandbox violation, unknown directive, structural error |
| `Sharp\Exception\RenderException` | View not found, template execution error, include/component failure |
| `Sharp\Exception\ConfigException` | `sharp.config.json` is invalid JSON or contains invalid values |

All exceptions extend `\RuntimeException`.

---

## Contracts

### `Sharp\Contract\LoaderInterface`

```php
interface LoaderInterface
{
    public function supports(string $name): bool;
    public function load(string $name): string;
    public function lastModified(string $name): int;
}
```

### `Sharp\Contract\CacheInterface`

```php
interface CacheInterface
{
    public function has(string $key): bool;
    public function getPath(string $key): string;
    public function write(string $key, string $phpSource): string;
    public function writeGraph(string $key, array $record): void;
    public function readGraph(string $key): ?array;
}
```

### `Sharp\Contract\PipelineInterface`

```php
interface PipelineInterface
{
    public function process(Node $node, CompilationContext $ctx): Node;
}
```
