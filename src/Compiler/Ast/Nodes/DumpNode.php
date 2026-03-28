<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class DumpNode extends Node
{
    public NodeType $type = NodeType::DUMP;

    public function __construct(public string $expression, int $line = 0)
    {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        return "<?php var_dump({$this->expression}); ?>";
    }
}
