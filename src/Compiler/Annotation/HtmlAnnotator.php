<?php declare(strict_types=1);

namespace Sharp\Compiler\Annotation;

final class HtmlAnnotator
{
    /**
     * Inject data-sharp-src (and optionally data-sharp-component, data-sharp-id,
     * data-sharp-props) into the first HTML opening tag found in $html.
     * Returns $html unchanged if no tag is found.
     *
     * The attribute is inserted immediately after the tag name, before any existing
     * attributes or the closing >.
     *
     * @param string      $absoluteSpPath  Absolute path to the source .sp file.
     * @param int         $line            Source line number in the .sp file.
     * @param string|null $component       Component name, if this element is a component root.
     * @param string|null $id              Component instance ID (e.g. "uc_1") for devtools targeting.
     * @param string|null $simpleProps     JSON-encoded scalar props (single-quoted attribute to avoid HTML escaping).
     */
    public static function inject(
        string $html,
        string $absoluteSpPath,
        int $line,
        ?string $component   = null,
        ?string $id          = null,
        ?string $simpleProps = null,
    ): string {
        // Match the first opening HTML tag name (e.g. <div, <span, <user-card)
        if (!preg_match('/(<[a-zA-Z][a-zA-Z0-9\-]*)/', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        // Insert position: right after the tag name, before any attributes or >
        $insertAt = $matches[1][1] + strlen($matches[1][0]);

        $attrs = ' data-sharp-src="' . $absoluteSpPath . ':' . $line . '"';
        if ($component !== null) {
            $attrs .= ' data-sharp-component="' . $component . '"';
        }
        if ($id !== null) {
            $attrs .= ' data-sharp-id="' . $id . '"';
        }
        if ($simpleProps !== null) {
            // Use single-quoted attribute so the JSON double-quotes don't need escaping
            $attrs .= " data-sharp-props='" . $simpleProps . "'";
        }

        return substr($html, 0, $insertAt) . $attrs . substr($html, $insertAt);
    }
}
