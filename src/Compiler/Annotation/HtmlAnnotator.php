<?php declare(strict_types=1);

namespace Sharp\Compiler\Annotation;

final class HtmlAnnotator
{
    /**
     * Inject data-sharp-id and data-sharp-component into the first HTML opening
     * tag found in $html. Returns $html unchanged if no tag is found.
     *
     * The attributes are inserted immediately after the tag name, before any
     * existing attributes or the closing >.
     *
     * @param string|null $component  Component name (e.g. "user-card").
     * @param string|null $id         Component instance ID (e.g. "uc_1").
     */
    public static function inject(
        string $html,
        ?string $component = null,
        ?string $id        = null,
    ): string {
        if ($component === null && $id === null) {
            return $html;
        }

        if (!preg_match('/(<[a-zA-Z][a-zA-Z0-9\-]*)/', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $insertAt = $matches[1][1] + strlen($matches[1][0]);

        $attrs = '';
        if ($id !== null) {
            $attrs .= ' data-sharp-id="' . $id . '"';
        }
        if ($component !== null) {
            $attrs .= ' data-sharp-component="' . $component . '"';
        }

        return substr($html, 0, $insertAt) . $attrs . substr($html, $insertAt);
    }
}
