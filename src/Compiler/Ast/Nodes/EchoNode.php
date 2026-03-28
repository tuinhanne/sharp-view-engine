<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class EchoNode extends Node
{
    public NodeType $type = NodeType::ECHO;

    public function __construct(public string $expression, int $line = 0)
    {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        $expr = $this->expression;
        return "<?php echo ((\$__v = ({$expr})) instanceof \Sharp\Runtime\HtmlString ? (string)\$__v : htmlspecialchars((string)\$__v, ENT_QUOTES, 'UTF-8')); ?>";
    }
}
