<?php declare(strict_types=1);

namespace Sharp\Runtime\Annotation;

use Sharp\Compiler\Annotation\HtmlAnnotator;
use Sharp\Runtime\Debug\DebugRegistry;

final class RuntimeAnnotator
{
    /**
     * Annotate the outer HTML element of a rendered component with data-sharp-*
     * attributes, and finalise the component's DebugRegistry record.
     *
     * @param string   $output     Rendered HTML from the component.
     * @param string   $component  Component name (e.g. "user-card").
     * @param array    $props      Runtime props (recorded in DebugRegistry).
     * @param string[] $slotNames  Names of slots provided to the component.
     * @param float    $renderMs   Wall-clock render time in milliseconds.
     * @param string   $debugId    Component instance ID from DebugRegistry::pushComponent().
     * @param bool     $cacheHit   Whether the component's compiled PHP was cached.
     */
    public static function wrap(
        string $output,
        string $component,
        array $props,
        array $slotNames,
        float $renderMs,
        string $debugId,
        bool $cacheHit,
    ): string {
        DebugRegistry::getInstance()->popComponent($debugId, $renderMs, $cacheHit);

        return HtmlAnnotator::inject($output, $component, $debugId);
    }
}
