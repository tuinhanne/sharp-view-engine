<?php declare(strict_types=1);

namespace Sharp\Runtime;

/**
 * The $loop object injected into every #loop block.
 * Nested #loop blocks automatically receive a new $loop
 * whose ->parent points to the outer loop's variable.
 */
final class LoopVariable
{
    /** 0-based position in the current iteration */
    public int $index = 0;

    /** 1-based position in the current iteration */
    public int $iteration = 1;

    /** Total number of iterations */
    public readonly int $count;

    /** Remaining iterations after the current one */
    public int $remaining;

    /** True on the first iteration */
    public bool $first = true;

    /** True on the last iteration */
    public bool $last = false;

    /** True when index is even (0, 2, 4 …) */
    public bool $even = true;

    /** True when index is odd (1, 3, 5 …) */
    public bool $odd = false;

    /** Nesting depth — 1 for the outermost loop */
    public readonly int $depth;

    /** The parent $loop object, or null if this is the outermost loop */
    public readonly ?self $parent;

    public function __construct(int $count, ?self $parent = null)
    {
        $this->count     = $count;
        $this->parent    = $parent;
        $this->depth     = $parent !== null ? $parent->depth + 1 : 1;
        $this->remaining = max(0, $count - 1);
    }

    /**
     * Called at the start of each iteration to sync all properties.
     * @internal  Called from compiled template code.
     */
    public function update(int $index): void
    {
        $this->index     = $index;
        $this->iteration = $index + 1;
        $this->remaining = max(0, $this->count - $index - 1);
        $this->first     = $index === 0;
        $this->last      = $index === $this->count - 1;
        $this->even      = $index % 2 === 0;
        $this->odd       = $index % 2 !== 0;
    }
}
