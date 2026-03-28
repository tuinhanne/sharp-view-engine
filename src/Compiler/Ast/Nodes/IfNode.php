<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class IfNode extends Node
{
    public NodeType $type = NodeType::IF;

    /**
     * Each entry: ['condition' => string, 'children' => Node[]]
     * First entry = #if branch, subsequent = #elseif branches.
     * @var array<int, array{condition: string, children: Node[]}>
     */
    public array $branches = [];

    /** @var Node[]|null  null = no #else block */
    public ?array $elseChildren = null;

    public function __construct(int $line = 0)
    {
        $this->line = $line;
    }

    /** Override walk() so validators/pipeline stages can traverse branch children. */
    public function walk(): array
    {
        $result = [$this];

        foreach ($this->branches as $branch) {
            foreach ($branch['children'] as $child) {
                foreach ($child->walk() as $node) {
                    $result[] = $node;
                }
            }
        }

        foreach ($this->elseChildren ?? [] as $child) {
            foreach ($child->walk() as $node) {
                $result[] = $node;
            }
        }

        return $result;
    }

    public function compile(CompilationContext $ctx): string
    {
        $out = '';

        foreach ($this->branches as $i => $branch) {
            $keyword = $i === 0 ? 'if' : 'elseif';
            $out .= "<?php {$keyword} ({$branch['condition']}): ?>";
            foreach ($branch['children'] as $child) {
                $out .= $child->compile($ctx);
            }
        }

        if ($this->elseChildren !== null) {
            $out .= '<?php else: ?>';
            foreach ($this->elseChildren as $child) {
                $out .= $child->compile($ctx);
            }
        }

        $out .= '<?php endif; ?>';

        return $out;
    }
}
