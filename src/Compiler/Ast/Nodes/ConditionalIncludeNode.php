<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class ConditionalIncludeNode extends Node
{
    public NodeType $type = NodeType::INCLUDE_WHEN;

    /**
     * @param string $condition  PHP expression to evaluate
     * @param string $view       View key to include
     * @param bool   $negate     true for #includeUnless (negates the condition)
     */
    public function __construct(
        public string $condition,
        public string $view,
        public bool   $negate,
        int $line = 0,
    ) {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        $view      = addslashes($this->view);
        $condition = $this->negate
            ? "!({$this->condition})"
            : $this->condition;

        return "<?php if ({$condition}): echo \$__env->renderInclude('{$view}', get_defined_vars()); endif; ?>";
    }
}
