<?php declare(strict_types=1);

namespace Sharp\Compiler;

use Sharp\Runtime\Directive\DirectiveRegistry;
use Sharp\Runtime\Component\ComponentRegistry;
use Sharp\Support\Config;

final class CompilationContext
{
    public function __construct(
        public readonly DirectiveRegistry  $directives,
        public readonly ComponentRegistry  $components,
        public readonly Config             $config,
        public readonly string             $viewKey = '',
        /** Callable: (string $viewKey): ?string — resolves a view key to its absolute file path */
        public readonly ?\Closure          $pathResolver = null,
        public readonly bool               $devMode = false,
        /** Absolute path to the source .sp file — used for data-sharp-src annotations in dev mode. */
        public readonly string             $absoluteSpPath = '',
    ) {}
}
