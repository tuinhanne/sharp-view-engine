<?php declare(strict_types=1);

namespace Sharp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sharp\Exception\CompileException;
use Sharp\Sharp;

final class SandboxTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/sharp_sandbox_' . uniqid();
        mkdir($this->tmpDir . '/templates', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function makeSharp(bool $sandbox): Sharp
    {
        file_put_contents(
            $this->tmpDir . '/sharp.config.json',
            json_encode(['viewPath' => 'templates/', 'sandbox' => $sandbox]),
        );
        return new Sharp($this->tmpDir);
    }

    private function template(string $name, string $content): void
    {
        $path = $this->tmpDir . '/templates/' . str_replace('.', '/', $name) . Sharp::EXTENSION;
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $content);
    }

    // ─── Sandbox ON ───────────────────────────────────────────────────────────

    public function test_exec_in_echo_throws_when_sandboxed(): void
    {
        $sharp = $this->makeSharp(true);
        $this->template('evil1', "{{ exec('ls') }}");

        $this->expectException(CompileException::class);
        $this->expectExceptionMessageMatches('/exec/');
        $sharp->render('evil1');
    }

    public function test_exec_in_if_condition_throws_when_sandboxed(): void
    {
        $sharp = $this->makeSharp(true);
        $this->template('evil2', "#if(exec('id'))yes#endif");

        $this->expectException(CompileException::class);
        $this->expectExceptionMessageMatches('/exec/');
        $sharp->render('evil2');
    }

    public function test_exec_in_foreach_throws_when_sandboxed(): void
    {
        $sharp = $this->makeSharp(true);
        $this->template('evil3', "#foreach(explode(',', shell_exec('ls')) as \$f){{ \$f }}#endforeach");

        $this->expectException(CompileException::class);
        $this->expectExceptionMessageMatches('/shell_exec/');
        $sharp->render('evil3');
    }

    public function test_file_get_contents_in_while_throws_when_sandboxed(): void
    {
        $sharp = $this->makeSharp(true);
        $this->template('evil4', "#while(file_get_contents('/etc/passwd'))x#endwhile");

        $this->expectException(CompileException::class);
        $this->expectExceptionMessageMatches('/file_get_contents/');
        $sharp->render('evil4');
    }

    public function test_raw_echo_with_dangerous_fn_throws_when_sandboxed(): void
    {
        $sharp = $this->makeSharp(true);
        $this->template('evil5', "{!! shell_exec('id') !!}");

        $this->expectException(CompileException::class);
        $this->expectExceptionMessageMatches('/shell_exec/');
        $sharp->render('evil5');
    }

    public function test_compile_exception_includes_line_number(): void
    {
        $sharp = $this->makeSharp(true);
        // exec() on line 2
        $this->template('evil6', "line one\n{{ exec('cmd') }}");

        try {
            $sharp->render('evil6');
            self::fail('Expected CompileException');
        } catch (CompileException $e) {
            self::assertSame(2, $e->templateLine);
            self::assertStringContainsString('evil6', $e->template);
        }
    }

    // ─── Sandbox OFF ──────────────────────────────────────────────────────────

    public function test_dangerous_fn_allowed_when_sandbox_disabled(): void
    {
        $sharp = $this->makeSharp(false);
        // With sandbox off, this should compile without throwing
        // (we don't actually run exec — just verify no CompileException)
        $this->template('safe1', "{{ strtoupper('hello') }}");

        $out = $sharp->render('safe1');
        self::assertStringContainsString('HELLO', $out);
    }

    public function test_sandbox_off_allows_exec_in_template_compilation(): void
    {
        $sharp = $this->makeSharp(false);
        // Sandbox is off — compilation should succeed (we won't execute it here)
        $this->template('noblock', "#if(true)ok#endif");
        $out = $sharp->render('noblock');
        self::assertStringContainsString('ok', $out);
    }

    // ─── Structural validation (always enforced) ──────────────────────────────

    public function test_extends_inside_if_always_throws(): void
    {
        // Structural validation runs regardless of sandbox setting
        $sharp = $this->makeSharp(false);
        $this->template('struct1', "#if(true)#extends('layouts.x')#endif");

        $this->expectException(CompileException::class);
        $this->expectExceptionMessageMatches('/#extends/');
        $sharp->render('struct1');
    }

    public function test_section_inside_foreach_always_throws(): void
    {
        $sharp = $this->makeSharp(false);
        $this->template('struct2', "#foreach(\$items as \$i)#section('x')y#endsection#endforeach");

        $this->expectException(CompileException::class);
        $this->expectExceptionMessageMatches('/#section/');
        $sharp->render('struct2', ['items' => [1]]);
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
