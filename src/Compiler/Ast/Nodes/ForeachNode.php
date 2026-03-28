<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class ForeachNode extends Node
{
    public NodeType $type = NodeType::FOREACH;

    /** Unique per-node counter — prevents variable collisions in nested #foreach blocks. */
    private static int $counter = 0;
    private int $uid;

    public function __construct(public string $expression, int $line = 0)
    {
        $this->line = $line;
        $this->uid  = ++self::$counter;
    }

    public function compile(CompilationContext $ctx): string
    {
        $u = $this->uid;
        [$collection, $alias] = $this->splitExpression();

        return implode('', [
            // 1. Buffer the iterable → array so we can count() before iterating
            "<?php",
            " \$__lp{$u}  = \$loop ?? null;",
            " \$__tmp{$u} = {$collection};",
            " \$__fc{$u}  = is_array(\$__tmp{$u}) ? \$__tmp{$u} : iterator_to_array(\$__tmp{$u});",
            " \$__cc{$u}  = count(\$__fc{$u});",
            " \$loop = new \\Sharp\\Runtime\\LoopVariable(\$__cc{$u}, \$__lp{$u});",
            " \$__fi{$u}  = 0; ?>",

            // 2. The actual foreach
            "<?php foreach (\$__fc{$u} as {$alias}): \$loop->update(\$__fi{$u}++); ?>",

            $this->compileChildren($ctx),

            // 3. Restore outer $loop (null for the outermost)
            "<?php endforeach; \$loop = \$__lp{$u}; ?>",
        ]);
    }

    /**
     * Split "COLLECTION as ALIAS" into ['COLLECTION', 'ALIAS'].
     * Works for:
     *   $items as $item
     *   $map as $key => $value
     *   getItems() as $item
     *
     * @return array{string, string}
     */
    private function splitExpression(): array
    {
        // Split on the first " as " (case-insensitive word boundary)
        $parts = preg_split('/\s+as\s+/i', $this->expression, 2);

        $collection = trim($parts[0] ?? $this->expression);
        $alias      = trim($parts[1] ?? '');

        return [$collection, $alias];
    }
}
