<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class ForNode extends Node
{
    public NodeType $type = NodeType::FOR;

    public function __construct(public string $expression, int $line = 0)
    {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        return "<?php for ({$this->expression}): ?>"
            . $this->compileChildren($ctx)
            . '<?php endfor; ?>';
    }
}
