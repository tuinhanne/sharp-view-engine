<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;
use Sharp\Exception\CompileException;

final class DirectiveNode extends Node
{
    public NodeType $type = NodeType::DIRECTIVE;

    public function __construct(
        public string $name,
        public string $args,
        int $line = 0,
    ) {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        if (!$ctx->directives->has($this->name)) {
            throw new CompileException(
                "Unknown directive #{$this->name}",
                $ctx->viewKey,
            );
        }

        return $ctx->directives->call($this->name, $this->args);
    }
}
