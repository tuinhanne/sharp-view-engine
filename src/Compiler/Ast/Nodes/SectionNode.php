<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class SectionNode extends Node
{
    public NodeType $type = NodeType::SECTION;

    public function __construct(public string $name, int $line = 0)
    {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        $name = addslashes($this->name);
        return "<?php \$__env->startSection('{$name}'); ?>"
            . $this->compileChildren($ctx)
            . "<?php \$__env->stopSection(); ?>";
    }
}
