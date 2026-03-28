<?php declare(strict_types=1);

namespace Sharp\Contract;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\CompilationContext;

interface PipelineInterface
{
    /**
     * @param  Node[] $nodes
     * @return Node[]
     */
    public function process(array $nodes, CompilationContext $ctx): array;
}
