<?php declare(strict_types=1);

namespace Sharp\Runtime\Component;

use Sharp\Exception\RenderException;
use Sharp\Runtime\HtmlString;

final class ComponentRenderer
{
    public function __construct(
        private readonly ComponentRegistry $registry,
    ) {}

    /**
     * @param array<string, mixed>  $props
     * @param array<string, string> $slots   slotName → rendered HTML string
     */
    public function render(
        string $name,
        array  $props,
        array  $slots,
        callable $executeTemplate,
    ): string {
        $def = $this->registry->resolve($name);

        return match ($def->type) {
            ComponentType::FILE         => $this->renderFile($def, $props, $slots, $executeTemplate),
            ComponentType::CLASS_BACKED => $this->renderClass($def, $props, $slots),
        };
    }

    private function renderFile(
        ComponentDefinition $def,
        array $props,
        array $slots,
        callable $executeTemplate,
    ): string {
        if (!file_exists($def->target)) {
            throw new RenderException(
                "Component file not found: [{$def->name}] → {$def->target}"
            );
        }

        // Props become template variables; $slot is the default slot content
        $data            = $props;
        $data['__props'] = $props;
        $data['__slots'] = array_map(fn(string $html) => new HtmlString($html), $slots);
        $data['slot']    = new HtmlString($slots['default'] ?? '');

        return $executeTemplate($def->target, $data);
    }

    private function renderClass(
        ComponentDefinition $def,
        array $props,
        array $slots,
    ): string {
        $class = $def->target;
        if (!class_exists($class)) {
            throw new RenderException("Component class not found: {$class}");
        }

        $instance = new $class($props, $slots);

        if (!method_exists($instance, 'render')) {
            throw new RenderException("Component class [{$class}] must implement render(): string");
        }

        return $instance->render();
    }
}
