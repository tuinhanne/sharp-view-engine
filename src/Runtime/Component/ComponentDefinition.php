<?php declare(strict_types=1);

namespace Sharp\Runtime\Component;

enum ComponentType
{
    case FILE;
    case CLASS_BACKED;
}

final readonly class ComponentDefinition
{
    public function __construct(
        public string        $name,
        public ComponentType $type,
        /** Absolute file path or FQCN */
        public string        $target,
    ) {}
}
