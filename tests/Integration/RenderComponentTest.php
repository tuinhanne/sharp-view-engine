<?php declare(strict_types=1);

namespace Sharp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sharp\Exception\RenderException;
use Sharp\Sharp;

final class RenderComponentTest extends TestCase
{
    private string $tmpDir;
    private Sharp  $sharp;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/sharp_comp_' . uniqid();
        mkdir($this->tmpDir . '/templates/components', 0755, true);

        file_put_contents(
            $this->tmpDir . '/sharp.config.json',
            json_encode(['viewPath' => 'templates/', 'sandbox' => false]),
        );

        $this->sharp = new Sharp($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function template(string $name, string $content): void
    {
        $path = $this->tmpDir . '/templates/' . str_replace('.', '/', $name) . Sharp::EXTENSION;
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $content);
    }

    // ─── File-backed components ───────────────────────────────────────────────

    public function test_self_closing_component_renders(): void
    {
        $this->template('components.badge', '<span class="badge">Badge</span>');
        $this->template('page', '<Badge />');

        $out = $this->sharp->render('page');
        self::assertStringContainsString('<span class="badge">Badge</span>', $out);
    }

    public function test_static_props_become_variables(): void
    {
        $this->template('components.alert', '<div class="alert-{{ $type }}">{{ $message }}</div>');
        $this->template('page2', '<Alert type="success" message="Saved!" />');

        $out = $this->sharp->render('page2');
        self::assertStringContainsString('alert-success', $out);
        self::assertStringContainsString('Saved!', $out);
    }

    public function test_dynamic_props_evaluate_php_expression(): void
    {
        $this->template('components.user-card', '<p>{{ $user }}</p>');
        $this->template('page3', '<UserCard :user="$name" />');

        $out = $this->sharp->render('page3', ['name' => 'Nhan']);
        self::assertStringContainsString('<p>Nhan</p>', $out);
    }

    public function test_kebab_case_tag_same_as_pascal_case(): void
    {
        $this->template('components.my-button', '<button>Click</button>');
        $this->template('pageA', '<my-button />');
        $this->template('pageB', '<MyButton />');

        self::assertStringContainsString('<button>Click</button>', $this->sharp->render('pageA'));
        self::assertStringContainsString('<button>Click</button>', $this->sharp->render('pageB'));
    }

    public function test_default_slot_content(): void
    {
        $this->template('components.card', '<div class="card">{!! $slot !!}</div>');
        $this->template('page4', '<Card><p>Hello inside</p></Card>');

        $out = $this->sharp->render('page4');
        self::assertStringContainsString('<div class="card">', $out);
        self::assertStringContainsString('<p>Hello inside</p>', $out);
    }

    public function test_named_slots(): void
    {
        $this->template('components.modal',
            '<div class="modal">' .
            '<header>{!! $__slots[\'header\'] ?? \'\' !!}</header>' .
            '<main>{!! $__slots[\'body\'] ?? \'\' !!}</main>' .
            '</div>'
        );
        $this->template('page5',
            '<Modal>' .
            '<slot name="header"><h2>Title</h2></slot>' .
            '<slot name="body"><p>Content</p></slot>' .
            '</Modal>'
        );

        $out = $this->sharp->render('page5');
        self::assertStringContainsString('<h2>Title</h2>', $out);
        self::assertStringContainsString('<p>Content</p>', $out);
    }

    public function test_missing_component_file_throws(): void
    {
        $this->template('broken', '<NonExistentComponent />');
        $this->expectException(RenderException::class);
        $this->sharp->render('broken');
    }

    // ─── Class-backed components ──────────────────────────────────────────────

    public function test_class_backed_component_renders(): void
    {
        $this->sharp->component('Greeting', GreetingComponent::class);
        $this->template('pageC', '<Greeting name="World" />');

        $out = $this->sharp->render('pageC');
        self::assertStringContainsString('Hello, World!', $out);
    }

    public function test_class_backed_component_missing_render_throws(): void
    {
        $this->sharp->component('Bad', BadComponent::class);
        $this->template('pageBad', '<Bad />');

        $this->expectException(RenderException::class);
        $this->sharp->render('pageBad');
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}

// ─── Fixtures ─────────────────────────────────────────────────────────────────

final class GreetingComponent
{
    public function __construct(
        private readonly array $props,
        private readonly array $slots,
    ) {}

    public function render(): string
    {
        $name = htmlspecialchars($this->props['name'] ?? 'World', ENT_QUOTES, 'UTF-8');
        return "<p>Hello, {$name}!</p>";
    }
}

final class BadComponent
{
    public function __construct(array $props, array $slots) {}
    // intentionally no render() method
}
