<?php declare(strict_types=1);

namespace Sharp\Runtime\Component;

final class ComponentResolver
{
    /** UserCard → user-card,  user-card → user-card */
    public static function toKebab(string $name): string
    {
        if (str_contains($name, '-')) {
            return strtolower($name);
        }
        $kebab = preg_replace('/[A-Z]/', '-$0', lcfirst($name)) ?? $name;
        return strtolower(ltrim($kebab, '-'));
    }

    /** user-card → UserCard */
    public static function toPascal(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
    }
}
