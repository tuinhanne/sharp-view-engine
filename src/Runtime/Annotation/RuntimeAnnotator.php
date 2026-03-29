<?php declare(strict_types=1);

namespace Sharp\Runtime\Annotation;

use Sharp\Compiler\Annotation\HtmlAnnotator;
use Sharp\Runtime\Debug\DebugRegistry;

final class RuntimeAnnotator
{
    /**
     * Annotate the outer HTML element of a rendered component output with
     * data-sharp-* attributes. Called at runtime (not compile time) because
     * component output is a string.
     *
     * Also finalises the component's DebugRegistry record with render time
     * and cache hit status (dev mode only).
     *
     * @param string               $output          Rendered HTML from the component.
     * @param string               $absoluteSpPath  Absolute path of the .sp file that uses this component.
     * @param int                  $line            Line in $absoluteSpPath where the component tag appears.
     * @param string               $component       Component name (e.g. "user-card").
     * @param array<string,mixed>  $props           Runtime props passed to the component.
     * @param string[]             $slotNames       Names of slots provided to the component.
     * @param float                $renderMs        Wall-clock render time in milliseconds.
     * @param string               $debugId         Component instance ID from DebugRegistry::pushComponent().
     * @param bool                 $cacheHit        Whether the component's compiled PHP was cached.
     */
    public static function wrap(
        string $output,
        string $absoluteSpPath,
        int $line,
        string $component,
        array $props,
        array $slotNames,
        float $renderMs,
        string $debugId,
        bool $cacheHit,
    ): string {
        $registry = DebugRegistry::getInstance();
        $registry->popComponent($debugId, $renderMs, $cacheHit);

        $simpleProps = self::buildSimpleProps($props);

        return HtmlAnnotator::inject(
            $output,
            $absoluteSpPath,
            $line,
            $component,
            $debugId,
            $simpleProps,
        );
    }

    /**
     * Build a JSON string of scalar-only props for the data-sharp-props attribute.
     * Objects and arrays are replaced with type descriptors.
     *
     * @param array<string, mixed> $props
     */
    private static function buildSimpleProps(array $props): ?string
    {
        if (empty($props)) {
            return null;
        }

        $simple = [];
        foreach ($props as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $simple[$key] = $value;
            } elseif (is_array($value)) {
                $simple[$key] = '[array(' . count($value) . ')]';
            } else {
                $simple[$key] = '[' . get_debug_type($value) . ']';
            }
        }

        $json = json_encode($simple, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json !== false ? $json : null;
    }
}
