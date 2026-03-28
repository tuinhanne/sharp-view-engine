<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

/**
 * Represents a #set($var = expr) directive.
 * Compiles to: <?php $var = expr; ?>
 */
final class SetNode extends Node
{
    public NodeType $type = NodeType::SET;

    public function __construct(public string $expression, int $line = 0)
    {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        return "<?php {$this->expression}; ?>";
    }
}
