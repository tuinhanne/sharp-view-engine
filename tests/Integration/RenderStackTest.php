<?php declare(strict_types=1);

namespace Sharp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sharp\Sharp;

final class RenderStackTest extends TestCase
{
    private string $tmpDir;
    private Sharp  $sharp;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/sharp_stack_' . uniqid();
        mkdir($this->tmpDir . '/templates/layouts', 0755, true);
        mkdir($this->tmpDir . '/templates/partials', 0755, true);

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

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_basic_push_and_yield_stack(): void
    {
        $this->template('layouts.main',
            '<head>#stack(\'scripts\')</head><body>#yield(\'content\')</body>'
        );
        $this->template('page',
            "#extends('layouts.main')" .
            "#section('content')<p>Hello</p>#endsection" .
            "#push('scripts')<script src=\"app.js\"></script>#endpush"
        );

        $out = $this->sharp->render('page');
        self::assertStringContainsString('<script src="app.js"></script>', $out);
        self::assertStringContainsString('<p>Hello</p>', $out);
    }

    public function test_multiple_pushes_accumulate_in_order(): void
    {
        $this->template('layouts.main',
            '#stack(\'scripts\')'
        );
        $this->template('page',
            "#extends('layouts.main')" .
            "#push('scripts')A#endpush" .
            "#push('scripts')B#endpush"
        );

        $out = $this->sharp->render('page');
        self::assertStringContainsString('AB', $out);
        $posA = strpos($out, 'A');
        $posB = strpos($out, 'B');
        self::assertLessThan($posB, $posA, 'A should appear before B');
    }

    public function test_prepend_inserts_before_push(): void
    {
        $this->template('layouts.main',
            '#stack(\'scripts\')'
        );
        $this->template('page',
            "#extends('layouts.main')" .
            "#push('scripts')A#endpush" .
            "#prepend('scripts')B#endprepend"
        );

        $out = $this->sharp->render('page');
        $posA = strpos($out, 'A');
        $posB = strpos($out, 'B');
        self::assertLessThan($posA, $posB, 'B (prepended) should appear before A (pushed)');
    }

    public function test_push_from_included_partial(): void
    {
        $this->template('layouts.main',
            '<head>#stack(\'styles\')</head><body>#yield(\'content\')</body>'
        );
        $this->template('partials.widget',
            "#push('styles')<link href=\"widget.css\">#endpush<div>widget</div>"
        );
        $this->template('page',
            "#extends('layouts.main')" .
            "#section('content')#include('partials.widget')#endsection"
        );

        $out = $this->sharp->render('page');
        self::assertStringContainsString('<link href="widget.css">', $out);
        self::assertStringContainsString('<div>widget</div>', $out);
    }

    public function test_stack_empty_yields_empty_string(): void
    {
        $this->template('page', '#stack(\'nonexistent\')hello');

        $out = $this->sharp->render('page');
        self::assertSame('hello', trim($out));
    }

    public function test_push_inside_foreach_appends_each_iteration(): void
    {
        $this->template('layouts.main',
            '#stack(\'items\')'
        );
        $this->template('page',
            "#extends('layouts.main')" .
            "#foreach(\$items as \$item)#push('items'){{ \$item }}#endpush#endforeach"
        );

        $out = $this->sharp->render('page', ['items' => ['X', 'Y', 'Z']]);
        self::assertStringContainsString('X', $out);
        self::assertStringContainsString('Y', $out);
        self::assertStringContainsString('Z', $out);
        self::assertLessThan(strpos($out, 'Y'), strpos($out, 'X'));
        self::assertLessThan(strpos($out, 'Z'), strpos($out, 'Y'));
    }

    public function test_multiple_stacks_are_independent(): void
    {
        $this->template('layouts.main',
            '#stack(\'scripts\')---#stack(\'styles\')'
        );
        $this->template('page',
            "#extends('layouts.main')" .
            "#push('scripts')JS#endpush" .
            "#push('styles')CSS#endpush"
        );

        $out = $this->sharp->render('page');
        self::assertStringContainsString('JS---CSS', $out);
    }
}
