<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class RootNode extends Node
{
    public NodeType $type = NodeType::ROOT;

    public function compile(CompilationContext $ctx): string
    {
        return $this->compileChildren($ctx);
    }
}
