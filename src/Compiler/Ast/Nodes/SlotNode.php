<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class SlotNode extends Node
{
    public NodeType $type = NodeType::SLOT;

    public function __construct(public string $name = 'default', int $line = 0)
    {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        return $this->compileChildren($ctx);
    }
}
