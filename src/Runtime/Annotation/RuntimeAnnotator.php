<?php declare(strict_types=1);

namespace Sharp\Runtime\Annotation;

use Sharp\Compiler\Annotation\HtmlAnnotator;

final class RuntimeAnnotator
{
    /**
     * Annotate the outer HTML element of a rendered component output with
     * data-sharp-src and data-sharp-component attributes.
     * Called at runtime (not compile time) because component output is a string.
     *
     * @param string $output          Rendered HTML string from the component.
     * @param string $absoluteSpPath  Absolute path to the parent .sp file that uses this component.
     * @param int    $line            Line number in the parent .sp file where the component tag appears.
     * @param string $component       Component name (e.g. "user-card").
     */
    public static function wrap(
        string $output,
        string $absoluteSpPath,
        int $line,
        string $component,
    ): string {
        return HtmlAnnotator::inject($output, $absoluteSpPath, $line, $component);
    }
}
