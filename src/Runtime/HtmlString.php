<?php declare(strict_types=1);

namespace Sharp\Runtime;

/**
 * Wraps a trusted HTML string so that {{ $var }} does not escape it.
 */
final class HtmlString implements \Stringable
{
    public function __construct(private readonly string $html) {}

    public function __toString(): string
    {
        return $this->html;
    }
}
