<?php declare(strict_types=1);

namespace Sharp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sharp\Sharp;

final class RenderLayoutTest extends TestCase
{
    private string $tmpDir;
    private Sharp  $sharp;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/sharp_layout_' . uniqid();
        mkdir($this->tmpDir . '/templates/layouts', 0755, true);

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

    public function test_extends_and_yield(): void
    {
        $this->template('layouts.main',
            '<html><body>#yield(\'content\')</body></html>'
        );
        $this->template('page',
            "#extends('layouts.main')#section('content')<p>Hello</p>#endsection"
        );

        $out = $this->sharp->render('page');
        self::assertStringContainsString('<html>', $out);
        self::assertStringContainsString('<p>Hello</p>', $out);
    }

    public function test_multiple_sections(): void
    {
        $this->template('layouts.full',
            "<title>#yield('title')</title><body>#yield('content')</body>"
        );
        $this->template('multi',
            "#extends('layouts.full')" .
            "#section('title')My Page#endsection" .
            "#section('content')<p>Body</p>#endsection"
        );

        $out = $this->sharp->render('multi');
        self::assertStringContainsString('<title>My Page</title>', $out);
        self::assertStringContainsString('<p>Body</p>', $out);
    }

    public function test_yield_default_value(): void
    {
        $this->template('layouts.def',
            "#yield('sidebar', 'Default sidebar')"
        );
        $this->template('nosidebar',
            "#extends('layouts.def')#section('main')x#endsection"
        );

        $out = $this->sharp->render('nosidebar');
        self::assertStringContainsString('Default sidebar', $out);
    }

    public function test_parent_appends_to_layout_section(): void
    {
        $this->template('layouts.base',
            "#section('scripts')<script src=\"/base.js\"></script>#endsection" .
            "<body>#yield('content')#yield('scripts')</body>"
        );
        $this->template('childpage',
            "#extends('layouts.base')" .
            "#section('content')<p>Page</p>#endsection" .
            "#section('scripts')#parent<script src=\"/page.js\"></script>#endsection"
        );

        $out = $this->sharp->render('childpage');
        self::assertStringContainsString('/base.js', $out);
        self::assertStringContainsString('/page.js', $out);
        // Base script should appear before page script
        self::assertLessThan(strpos($out, '/page.js'), strpos($out, '/base.js'));
    }

    public function test_parent_without_parent_section_yields_empty(): void
    {
        // Layout has no 'extra' section defined — #parent should yield empty string
        $this->template('layouts.simple',
            "<main>#yield('content')</main>#yield('extra')"
        );
        $this->template('childwithparent',
            "#extends('layouts.simple')" .
            "#section('content')body#endsection" .
            "#section('extra')#parent<p>extra</p>#endsection"
        );

        $out = $this->sharp->render('childwithparent');
        self::assertStringContainsString('<p>extra</p>', $out);
        self::assertStringContainsString('body', $out);
    }

    public function test_three_level_layout_inheritance(): void
    {
        $this->template('layouts.base',
            '<!DOCTYPE html><html><body>#yield(\'content\')</body></html>'
        );
        $this->template('layouts.mid',
            "#extends('layouts.base')#section('content')<div class=\"wrap\">#yield('inner')</div>#endsection"
        );
        $this->template('deeppage',
            "#extends('layouts.mid')#section('inner')<h1>Deep</h1>#endsection"
        );

        $out = $this->sharp->render('deeppage');
        self::assertStringContainsString('<!DOCTYPE html>', $out);
        self::assertStringContainsString('<div class="wrap">', $out);
        self::assertStringContainsString('<h1>Deep</h1>', $out);
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
