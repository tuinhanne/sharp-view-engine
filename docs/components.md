# Components

Components are reusable template fragments. They accept props and named slots, and can be backed by a `.sp` file or a PHP class.

---

## Table of Contents

- [Registering components](#registering-components)
- [Auto-discovery](#auto-discovery)
- [Using components](#using-components)
- [Static props](#static-props)
- [Dynamic props](#dynamic-props)
- [Props declaration](#props-declaration)
- [Default slot](#default-slot)
- [Named slots](#named-slots)
- [Class-backed components](#class-backed-components)

---

## Registering components

```php
use Sharp\Sharp;

$sharp = new Sharp();

// File-backed component — path to a .sp file
$sharp->component('Alert',    '/path/to/views/components/alert.sp');
$sharp->component('UserCard', '/path/to/views/components/user-card.sp');

// Class-backed component — fully qualified class name
$sharp->component('DataTable', App\Components\DataTable::class);
```

---

## Auto-discovery

Components placed in `{viewPath}/components/` are discovered automatically.

| Component tag | Auto-resolved file |
|---|---|
| `<UserCard />` | `{viewPath}/components/user-card.sp` |
| `<PrimaryButton />` | `{viewPath}/components/primary-button.sp` |
| `<user-card />` | `{viewPath}/components/user-card.sp` |

PascalCase tags are converted to kebab-case filenames. Both forms work:

```html
<UserCard :user="$user" />
<user-card :user="$user" />
```

---

## Using components

Self-closing syntax for components without slot content:

```html
<Alert type="success" message="Saved!" />
<Spinner />
<Divider />
```

---

## Static props

Attribute values without `:` are passed as string literals.

```html
<Badge color="green" label="Active" />
```

Inside `templates/components/badge.sp`:

```html
<span class="badge badge-{{ $color }}">{{ $label }}</span>
```

---

## Dynamic props

Prefix with `:` to pass a PHP expression as the value.

```html
<!-- Variable -->
<UserCard :user="$currentUser" />

<!-- Expression -->
<Badge :color="$user->isActive ? 'green' : 'gray'" :label="$user->statusLabel()" />

<!-- Mixed static + dynamic -->
<Alert type="warning" :message="$flash->message" :dismissible="true" />
```

Inside the component file, all props become PHP variables — same name as the attribute.

---

## Props declaration

Use `#props([...])` inside a component file to declare expected props with type hints. This generates PHPDoc annotations in compiled output (IDE type inference) and performs runtime validation.

```
#props([
  title: string,
  description?: string,
  count: int,
  post: App/Model/Post,
])
```

| Syntax | Meaning |
|---|---|
| `name: type` | Required prop — throws if missing |
| `name?: type` | Optional prop — `null` if not passed |
| `App/Model/Post` | Class path using `/` instead of `\` |

**Supported types:** `string`, `int`, `float`, `bool`, `array`, `mixed`, and any class path.

### Example

**`templates/components/post-card.sp`**
```
#props([
  post: App/Model/Post,
  variant: string,
  pinned?: bool,
])

<div class="card card--{{ $variant }} {{ $pinned ? 'card--pinned' : '' }}">
  <h2>{{ $post->title }}</h2>
  <p>{{ $post->excerpt }}</p>
</div>
```

**Caller:**
```html
<PostCard :post="$post" variant="featured" :pinned="true" />
<PostCard :post="$post" variant="default" />
```

The `@var` annotations are picked up by IDEs so `$post`, `$variant`, and `$pinned` are properly typed inside the component template.

### Validation behaviour

| Scenario | Result |
|---|---|
| Required prop not passed | `RenderException`: "Required prop 'name' missing in component." |
| Wrong type (`int` given for `string`) | `RenderException`: "Prop 'name' must be string, got int" |
| Wrong class (`stdClass` given for `App/Model/Post`) | `RenderException`: "Prop 'name' must be App\Model\Post, got stdClass" |
| Optional prop not passed | Variable is `null`, no error |
| Optional prop with wrong type | `RenderException` (same as required) |

### Notes

- `#props` must appear at the top of the component file, before any HTML output.
- It only works in **file-backed** components (`.sp` files). Class-backed components handle prop validation in their constructor.
- Type `mixed` skips the type check entirely (only required/optional is enforced).

---

## Default slot

Content placed directly inside a component tag (not in a named `<slot>`) becomes `$slot`.

```html
<Card>
  <p>This is the card body content.</p>
  <a href="/more">Read more</a>
</Card>
```

**`templates/components/card.sp`**
```html
<div class="card">
  <div class="card-body">
    {{ $slot }}
  </div>
</div>
```

---

## Named slots

Pass multiple distinct regions of HTML into a component.

```html
<Modal :title="$post->title">
  <slot name="body">
    <p>{{ $post->excerpt }}</p>
    <img src="{{ $post->image }}" alt="">
  </slot>

  <slot name="footer">
    <a href="{{ $post->url }}" class="btn">Read full article</a>
    <button data-dismiss>Close</button>
  </slot>
</Modal>
```

Inside the component, output named slots with `#yield('name')`:

**`templates/components/modal.sp`**
```html
<div class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <h2>{{ $title }}</h2>
    </div>

    <div class="modal-body">
      #yield('body')
    </div>

    <div class="modal-footer">
      #yield('footer')
    </div>
  </div>
</div>
```

### Phân biệt default slot và named slot

| | Khai báo ở caller | Output ở component |
|---|---|---|
| Default slot | Nội dung trực tiếp bên trong tag | `{{ $slot }}` |
| Named slot | `<slot name="footer">...</slot>` | `#yield('footer')` |

> `#yield` trong component template ưu tiên slot trước, sau đó mới fallback về layout section — nên cú pháp hoạt động đúng trong cả hai ngữ cảnh.

---

## Class-backed components

When a component needs PHP logic — data fetching, computed properties, transformations — use a class-backed component.

### Basic example

```php
namespace App\Components;

class AlertComponent
{
    private string $type;
    private string $message;

    public function __construct(array $props, array $slots)
    {
        $this->type    = $props['type']    ?? 'info';
        $this->message = $props['message'] ?? '';
    }

    public function render(): string
    {
        $class = match($this->type) {
            'success' => 'bg-green-100 border-green-500 text-green-900',
            'error'   => 'bg-red-100 border-red-500 text-red-900',
            'warning' => 'bg-yellow-100 border-yellow-500 text-yellow-900',
            default   => 'bg-blue-100 border-blue-500 text-blue-900',
        };

        return "<div class=\"alert border-l-4 p-4 {$class}\">"
             . htmlspecialchars($this->message)
             . "</div>";
    }
}
```

```php
$sharp->component('Alert', App\Components\AlertComponent::class);
```

```html
<Alert type="success" :message="$flash->message" />
```

### Constructor signature

The constructor always receives exactly two arguments:

| Parameter | Type | Description |
|---|---|---|
| `$props` | `array` | All attributes from the component tag |
| `$slots` | `array` | Named slot content (keyed by slot name); default slot is `$slots['__default']` |

### Returning a view from a class component

Class components can also delegate rendering to a `.sp` template:

```php
class ChartComponent
{
    public function __construct(
        private readonly array $props,
        private readonly array $slots,
    ) {}

    public function render(Sharp $sharp): string
    {
        $data = $this->props['data'] ?? [];

        return $sharp->render('components.chart-inner', [
            'labels'  => array_column($data, 'label'),
            'values'  => array_column($data, 'value'),
            'caption' => $this->props['caption'] ?? '',
        ]);
    }
}
```
