<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class ParentNode extends Node
{
    public NodeType $type = NodeType::PARENT;

    public function __construct(int $line = 0)
    {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        return '<?php echo $__env->yieldParentContent(); ?>';
    }
}
