<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class WhileNode extends Node
{
    public NodeType $type = NodeType::WHILE;

    public function __construct(public string $condition, int $line = 0)
    {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        return "<?php while ({$this->condition}): ?>"
            . $this->compileChildren($ctx)
            . '<?php endwhile; ?>';
    }
}
