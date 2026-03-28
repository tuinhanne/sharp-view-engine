<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class SwitchNode extends Node
{
    public NodeType $type = NodeType::SWITCH;

    /**
     * @var array<int, array{value: string|null, children: Node[]}>
     *   value = null for the #default case
     */
    public array $cases = [];

    public function __construct(public string $expression, int $line = 0)
    {
        $this->line = $line;
    }

    /** Override walk() — SwitchNode stores children inside $cases, not $this->children */
    public function walk(): array
    {
        $result = [$this];
        foreach ($this->cases as $case) {
            foreach ($case['children'] as $child) {
                foreach ($child->walk() as $node) {
                    $result[] = $node;
                }
            }
        }
        return $result;
    }

    public function compile(CompilationContext $ctx): string
    {
        $php = "<?php switch ({$this->expression}): ?>";

        foreach ($this->cases as $case) {
            if ($case['value'] === null) {
                $php .= '<?php default: ?>';
            } else {
                $php .= "<?php case {$case['value']}: ?>";
            }

            foreach ($case['children'] as $child) {
                $php .= $child->compile($ctx);
            }
        }

        $php .= '<?php endswitch; ?>';
        return $php;
    }
}
