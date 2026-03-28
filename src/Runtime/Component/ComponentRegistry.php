<?php declare(strict_types=1);

namespace Sharp\Runtime\Component;

use Sharp\Sharp;

final class ComponentRegistry
{
    /** @var array<string, ComponentDefinition> kebab-name → definition */
    private array $components = [];

    private string $componentsPath;

    public function __construct(string $componentsPath)
    {
        $this->componentsPath = rtrim($componentsPath, '/\\');
    }

    /**
     * Register a component by name (kebab or PascalCase).
     * $target may be an absolute file path or a FQCN.
     */
    public function register(string $name, string $target): void
    {
        $kebab = ComponentResolver::toKebab($name);
        $type  = class_exists($target) ? ComponentType::CLASS_BACKED : ComponentType::FILE;

        $this->components[$kebab] = new ComponentDefinition($kebab, $type, $target);
    }

    public function resolve(string $name): ComponentDefinition
    {
        $kebab = ComponentResolver::toKebab($name);

        if (isset($this->components[$kebab])) {
            return $this->components[$kebab];
        }

        // Auto-resolve from components directory
        $path = $this->componentsPath . DIRECTORY_SEPARATOR . $kebab . Sharp::EXTENSION;
        return new ComponentDefinition($kebab, ComponentType::FILE, $path);
    }

    /** Returns the file path for a component if it is file-backed, null otherwise. */
    public function resolvePath(string $name): ?string
    {
        $def = $this->resolve($name);
        return $def->type === ComponentType::FILE ? $def->target : null;
    }

    public function isRegistered(string $name): bool
    {
        return isset($this->components[ComponentResolver::toKebab($name)]);
    }
}
