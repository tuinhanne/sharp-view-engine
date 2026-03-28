<?php declare(strict_types=1);

namespace Sharp\Compiler\Props;

final class PropDefinition
{
    public function __construct(
        public readonly string $name,
        /** Primitive type (string/int/float/bool/array/mixed) or fully-qualified class (\App\Model\Post) */
        public readonly string $type,
        public readonly bool   $nullable,
        public readonly bool   $isClass,
    ) {}
}
