<?php declare(strict_types=1);

namespace Sharp\Tests\Unit\Runtime;

use PHPUnit\Framework\TestCase;
use Sharp\Runtime\LoopVariable;

final class LoopVariableTest extends TestCase
{
    public function test_initial_state(): void
    {
        $loop = new LoopVariable(5);
        self::assertSame(5,    $loop->count);
        self::assertSame(1,    $loop->depth);
        self::assertNull($loop->parent);
        self::assertSame(4,    $loop->remaining); // count - 1 before first update
    }

    public function test_first_iteration(): void
    {
        $loop = new LoopVariable(3);
        $loop->update(0);

        self::assertSame(0,    $loop->index);
        self::assertSame(1,    $loop->iteration);
        self::assertSame(2,    $loop->remaining);
        self::assertTrue($loop->first);
        self::assertFalse($loop->last);
        self::assertTrue($loop->even);
        self::assertFalse($loop->odd);
    }

    public function test_last_iteration(): void
    {
        $loop = new LoopVariable(3);
        $loop->update(2);

        self::assertSame(2,    $loop->index);
        self::assertSame(3,    $loop->iteration);
        self::assertSame(0,    $loop->remaining);
        self::assertFalse($loop->first);
        self::assertTrue($loop->last);
        self::assertTrue($loop->even);  // index 2: 2 % 2 = 0 → even
        self::assertFalse($loop->odd);
    }

    public function test_odd_iteration(): void
    {
        $loop = new LoopVariable(4);
        $loop->update(1);

        self::assertSame(1,    $loop->index);
        self::assertSame(2,    $loop->iteration);
        self::assertSame(2,    $loop->remaining);
        self::assertFalse($loop->even);
        self::assertTrue($loop->odd);
    }

    public function test_remaining_counts_down(): void
    {
        $loop = new LoopVariable(4);

        $loop->update(0); self::assertSame(3, $loop->remaining);
        $loop->update(1); self::assertSame(2, $loop->remaining);
        $loop->update(2); self::assertSame(1, $loop->remaining);
        $loop->update(3); self::assertSame(0, $loop->remaining);
    }

    public function test_single_item_loop(): void
    {
        $loop = new LoopVariable(1);
        $loop->update(0);

        self::assertTrue($loop->first);
        self::assertTrue($loop->last);
        self::assertSame(0, $loop->remaining);
    }

    public function test_nested_loop_depth(): void
    {
        $outer = new LoopVariable(3);
        $inner = new LoopVariable(2, $outer);

        self::assertSame(1, $outer->depth);
        self::assertSame(2, $inner->depth);
        self::assertSame($outer, $inner->parent);
    }

    public function test_triple_nested_depth(): void
    {
        $l1 = new LoopVariable(3);
        $l2 = new LoopVariable(3, $l1);
        $l3 = new LoopVariable(3, $l2);

        self::assertSame(1, $l1->depth);
        self::assertSame(2, $l2->depth);
        self::assertSame(3, $l3->depth);
        self::assertSame($l2, $l3->parent);
        self::assertSame($l1, $l3->parent->parent);
    }

    public function test_parent_is_null_for_outermost(): void
    {
        $loop = new LoopVariable(5);
        self::assertNull($loop->parent);
    }
}
