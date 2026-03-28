<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class ExtendsNode extends Node
{
    public NodeType $type = NodeType::EXTENDS;

    public function __construct(public string $layout, int $line = 0)
    {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        $key = addslashes($this->layout);
        return "<?php \$__env->extends('{$key}'); ?>";
    }
}
