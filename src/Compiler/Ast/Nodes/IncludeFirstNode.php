<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class IncludeFirstNode extends Node
{
    public NodeType $type = NodeType::INCLUDE_FIRST;

    /**
     * @param string $arrayExpr  Raw PHP array expression, e.g. "['view1', 'view2']"
     */
    public function __construct(public string $arrayExpr, int $line = 0)
    {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        return "<?php echo \$__env->renderIncludeFirst({$this->arrayExpr}, get_defined_vars()); ?>";
    }
}
