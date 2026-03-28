<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class PrependNode extends Node
{
    public NodeType $type = NodeType::PREPEND;

    public function __construct(public string $name, int $line = 0)
    {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        $name = addslashes($this->name);
        return "<?php \$__env->startPrepend('{$name}'); ?>"
            . $this->compileChildren($ctx)
            . "<?php \$__env->stopPrepend(); ?>";
    }
}
