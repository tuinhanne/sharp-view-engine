<?php declare(strict_types=1);

namespace Sharp\Runtime\Layout;

use Sharp\Exception\RenderException;

final class SectionStack
{
    /** @var string[] */
    private array $stack = [];

    public function push(string $name): void
    {
        $this->stack[] = $name;
        ob_start();
    }

    public function pop(): string
    {
        if (empty($this->stack)) {
            throw new RenderException('SectionStack::pop() called with empty stack');
        }
        array_pop($this->stack);
        return (string) ob_get_clean();
    }

    public function current(): ?string
    {
        return empty($this->stack) ? null : end($this->stack);
    }

    public function isEmpty(): bool
    {
        return empty($this->stack);
    }

    /**
     * Flush all dangling output buffers opened by push() but never closed by pop().
     * Call this from exception handlers to prevent ob_start imbalance.
     */
    public function cleanup(): void
    {
        while (!empty($this->stack)) {
            ob_end_clean();
            array_pop($this->stack);
        }
    }
}
