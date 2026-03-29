<?php declare(strict_types=1);

namespace Sharp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sharp\Sharp;

final class RenderIncludeTest extends TestCase
{
    private string $tmpDir;
    private Sharp  $sharp;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/sharp_include_' . uniqid();
        mkdir($this->tmpDir . '/templates', 0755, true);

        file_put_contents(
            $this->tmpDir . '/sharp.config.json',
            json_encode(['viewPath' => 'templates/', 'sandbox' => false]),
        );

        $this->sharp = (new Sharp($this->tmpDir))->setProduction(true);
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

    // ─────────────────────────────────────────────────────────────────────────

    public function test_include_renders_partial(): void
    {
        $this->template('partials.nav', '<nav>Navigation</nav>');
        $this->template('page', "#include('partials.nav')<main>Body</main>");

        $out = $this->sharp->render('page');
        self::assertStringContainsString('<nav>Navigation</nav>', $out);
        self::assertStringContainsString('<main>Body</main>', $out);
    }

    public function test_include_inherits_parent_variables(): void
    {
        $this->template('partials.greeting', '<p>Hello, {{ $name }}!</p>');
        $this->template('wrapper', "#include('partials.greeting')");

        $out = $this->sharp->render('wrapper', ['name' => 'Nhan']);
        self::assertStringContainsString('Hello, Nhan!', $out);
    }

    public function test_include_in_loop(): void
    {
        $this->template('partials.item', '<li>{{ $item }}</li>');
        $this->template('list', "<ul>#foreach(\$items as \$item)#include('partials.item')#endforeach</ul>");

        $out = $this->sharp->render('list', ['items' => ['a', 'b', 'c']]);
        self::assertStringContainsString('<li>a</li>', $out);
        self::assertStringContainsString('<li>b</li>', $out);
        self::assertStringContainsString('<li>c</li>', $out);
    }

    public function test_include_cache_invalidates_when_partial_changes(): void
    {
        $this->template('partials.footer', '<footer>v1</footer>');
        $this->template('page2', "#include('partials.footer')");

        $out1 = $this->sharp->render('page2');
        self::assertStringContainsString('v1', $out1);

        // Wait 1 second to guarantee a different filemtime
        sleep(1);

        // Update the partial
        $this->template('partials.footer', '<footer>v2</footer>');

        // Re-instantiate Sharp so the cache-check runs fresh
        $this->sharp = (new Sharp($this->tmpDir))->setProduction(true);
        $out2 = $this->sharp->render('page2');
        self::assertStringContainsString('v2', $out2);
    }

    public function test_nested_includes(): void
    {
        $this->template('partials.inner', '[inner]');
        $this->template('partials.outer', "[outer]#include('partials.inner')");
        $this->template('root', "#include('partials.outer')");

        $out = $this->sharp->render('root');
        self::assertStringContainsString('[outer]', $out);
        self::assertStringContainsString('[inner]', $out);
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
