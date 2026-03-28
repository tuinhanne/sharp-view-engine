<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class YieldNode extends Node
{
    public NodeType $type = NodeType::YIELD;

    public function __construct(public string $name, public string $default = '', int $line = 0)
    {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        $name    = addslashes($this->name);
        $default = addslashes($this->default);
        return "<?php echo isset(\$__slots['{$name}']) ? \$__slots['{$name}'] : \$__env->yieldSection('{$name}', '{$default}'); ?>";
    }
}
