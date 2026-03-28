<?php declare(strict_types=1);

namespace Sharp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sharp\Sharp;

/**
 * Tests for $loop variable available inside #foreach blocks.
 */
final class RenderLoopTest extends TestCase
{
    private string $tmpDir;
    private Sharp  $sharp;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/sharp_loop_' . uniqid();
        mkdir($this->tmpDir . '/templates', 0755, true);
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

    private function render(string $name, array $data = []): string
    {
        return trim($this->sharp->render($name, $data));
    }

    // ─── $loop->index ────────────────────────────────────────────────────────

    public function test_loop_index(): void
    {
        $this->template('idx', '#foreach($items as $item){{ $loop->index }},#endforeach');
        self::assertSame('0,1,2,', $this->render('idx', ['items' => ['a', 'b', 'c']]));
    }

    // ─── $loop->iteration ────────────────────────────────────────────────────

    public function test_loop_iteration(): void
    {
        $this->template('iter', '#foreach($items as $item){{ $loop->iteration }},#endforeach');
        self::assertSame('1,2,3,', $this->render('iter', ['items' => ['a', 'b', 'c']]));
    }

    // ─── $loop->count ────────────────────────────────────────────────────────

    public function test_loop_count(): void
    {
        $this->template('cnt', '#foreach($items as $item){{ $loop->count }},#endforeach');
        self::assertSame('4,4,4,4,', $this->render('cnt', ['items' => [1, 2, 3, 4]]));
    }

    // ─── $loop->remaining ────────────────────────────────────────────────────

    public function test_loop_remaining(): void
    {
        $this->template('rem', '#foreach($items as $item){{ $loop->remaining }},#endforeach');
        self::assertSame('3,2,1,0,', $this->render('rem', ['items' => [1, 2, 3, 4]]));
    }

    // ─── $loop->first ────────────────────────────────────────────────────────

    public function test_loop_first(): void
    {
        $this->template('fst', '#foreach($items as $item){{ $loop->first ? "Y" : "N" }}#endforeach');
        self::assertSame('YNN', $this->render('fst', ['items' => [1, 2, 3]]));
    }

    // ─── $loop->last ─────────────────────────────────────────────────────────

    public function test_loop_last(): void
    {
        $this->template('lst', '#foreach($items as $item){{ $loop->last ? "Y" : "N" }}#endforeach');
        self::assertSame('NNY', $this->render('lst', ['items' => [1, 2, 3]]));
    }

    // ─── $loop->even / $loop->odd ────────────────────────────────────────────

    public function test_loop_even(): void
    {
        $this->template('evn', '#foreach($items as $item){{ $loop->even ? "E" : "O" }}#endforeach');
        self::assertSame('EOEO', $this->render('evn', ['items' => [1, 2, 3, 4]]));
    }

    public function test_loop_odd(): void
    {
        $this->template('odd', '#foreach($items as $item){{ $loop->odd ? "O" : "E" }}#endforeach');
        self::assertSame('EOEO', $this->render('odd', ['items' => [1, 2, 3, 4]]));
    }

    // ─── $loop->depth ────────────────────────────────────────────────────────

    public function test_loop_depth_outermost_is_1(): void
    {
        $this->template('dep', '#foreach($items as $item){{ $loop->depth }}#endforeach');
        self::assertSame('1', $this->render('dep', ['items' => ['a']]));
    }

    public function test_nested_loop_depth(): void
    {
        $this->template('nested',
            '#foreach($outer as $o){{ $loop->depth }}-' .
            '#foreach($inner as $i){{ $loop->depth }}#endforeach' .
            '#endforeach'
        );
        $out = $this->render('nested', ['outer' => [1], 'inner' => [1]]);
        self::assertSame('1-2', $out);
    }

    // ─── $loop->parent ───────────────────────────────────────────────────────

    public function test_nested_loop_parent(): void
    {
        $this->template('parent_test',
            '#foreach($outer as $o)' .
            '#foreach($inner as $i){{ $loop->parent->iteration }}#endforeach' .
            '#endforeach'
        );
        // outer iteration 1, inner has 2 items → "11"
        // outer iteration 2, inner has 2 items → "22"
        $out = $this->render('parent_test', ['outer' => [1, 2], 'inner' => ['a', 'b']]);
        self::assertSame('1122', $out);
    }

    public function test_parent_is_null_for_outermost(): void
    {
        $this->template('null_parent',
            '#foreach($items as $item){{ $loop->parent === null ? "null" : "has" }}#endforeach'
        );
        self::assertSame('null', $this->render('null_parent', ['items' => ['x']]));
    }

    // ─── Restore outer $loop after nested ────────────────────────────────────

    public function test_outer_loop_restored_after_nested(): void
    {
        $this->template('restore',
            '#foreach($outer as $o)' .
            '{{ $loop->iteration }}' .
            '#foreach($inner as $i)x#endforeach' .
            '{{ $loop->iteration }},' .
            '#endforeach'
        );
        // outer iter stays correct before and after inner foreach
        $out = $this->render('restore', ['outer' => [1, 2], 'inner' => ['a']]);
        self::assertSame('1x1,2x2,', $out);
    }

    // ─── Works with key => value syntax ──────────────────────────────────────

    public function test_key_value_foreach(): void
    {
        $this->template('kv',
            '#foreach($map as $key => $val){{ $loop->iteration }}:{{ $key }}={{ $val }},#endforeach'
        );
        $out = $this->render('kv', ['map' => ['a' => 1, 'b' => 2]]);
        self::assertSame('1:a=1,2:b=2,', $out);
    }

    // ─── Works with array of objects ─────────────────────────────────────────

    public function test_foreach_with_objects(): void
    {
        $items = [(object)['name' => 'Alice'], (object)['name' => 'Bob']];
        $this->template('obj',
            '#foreach($people as $p){{ $loop->iteration }}.{{ $p->name }},#endforeach'
        );
        self::assertSame('1.Alice,2.Bob,', $this->render('obj', ['people' => $items]));
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
