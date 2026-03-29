<?php declare(strict_types=1);

namespace Sharp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sharp\Sharp;

final class RenderBasicTest extends TestCase
{
    private string $tmpDir;
    private Sharp  $sharp;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/sharp_test_' . uniqid();
        mkdir($this->tmpDir . '/templates', 0755, true);

        // Write a minimal config
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

    public function test_plain_text(): void
    {
        $this->template('hello', 'Hello, World!');
        self::assertSame('Hello, World!', trim($this->sharp->render('hello')));
    }

    public function test_echo_variable(): void
    {
        $this->template('greeting', 'Hello, {{ $name }}!');
        $out = $this->sharp->render('greeting', ['name' => 'Nhan']);
        self::assertStringContainsString('Hello, Nhan!', $out);
    }

    public function test_echo_escapes_html(): void
    {
        $this->template('safe', '{{ $html }}');
        $out = $this->sharp->render('safe', ['html' => '<script>alert(1)</script>']);
        self::assertStringContainsString('&lt;script&gt;', $out);
    }

    public function test_raw_echo(): void
    {
        $this->template('raw', '{!! $html !!}');
        $out = $this->sharp->render('raw', ['html' => '<b>bold</b>']);
        self::assertStringContainsString('<b>bold</b>', $out);
    }

    public function test_comment_stripped(): void
    {
        $this->template('commented', '<!-- secret -->visible');
        $out = $this->sharp->render('commented');
        self::assertStringNotContainsString('secret', $out);
        self::assertStringContainsString('visible', $out);
    }

    public function test_if_true(): void
    {
        $this->template('cond', '#if($show)yes#endif');
        self::assertStringContainsString('yes', $this->sharp->render('cond', ['show' => true]));
    }

    public function test_if_false(): void
    {
        $this->template('cond2', '#if($show)yes#endif');
        self::assertStringNotContainsString('yes', $this->sharp->render('cond2', ['show' => false]));
    }

    public function test_if_else(): void
    {
        $this->template('cond3', '#if($x)A#else B#endif');
        self::assertStringContainsString('A', $this->sharp->render('cond3', ['x' => true]));
        self::assertStringContainsString('B', $this->sharp->render('cond3', ['x' => false]));
    }

    public function test_foreach(): void
    {
        $this->template('loop', '#foreach($items as $item){{ $item }},#endforeach');
        $out = $this->sharp->render('loop', ['items' => ['a', 'b', 'c']]);
        self::assertStringContainsString('a,b,c', str_replace(' ', '', $out));
    }

    public function test_dot_notation_path(): void
    {
        mkdir($this->tmpDir . '/templates/pages', 0755, true);
        $this->template('pages.home', '<h1>Home</h1>');
        $out = $this->sharp->render('pages.home');
        self::assertStringContainsString('<h1>Home</h1>', $out);
    }

    public function test_custom_directive(): void
    {
        $this->sharp->directive('money', fn($e) => "<?php echo number_format({$e}, 2); ?>");
        $this->template('money', '#money($price)');
        $out = $this->sharp->render('money', ['price' => 1234.5]);
        self::assertStringContainsString('1,234.50', $out);
    }

    public function test_compiled_cache_created(): void
    {
        $this->template('cached', 'cached content');
        $this->sharp->render('cached');
        $cacheDir = $this->tmpDir . '/.sharp/compiled';
        self::assertDirectoryExists($cacheDir);
        $files = glob($cacheDir . '/*.php');
        self::assertNotEmpty($files);
    }

    public function test_graph_cache_created(): void
    {
        $this->template('graphed', 'graphed content');
        $this->sharp->render('graphed');
        $graphDir = $this->tmpDir . '/.sharp/graph';
        self::assertDirectoryExists($graphDir);
    }

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
