<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class CommentNode extends Node
{
    public NodeType $type = NodeType::COMMENT;

    public function __construct(public string $content, int $line = 0)
    {
        $this->line = $line;
    }

    /** Comments are stripped — compile to empty string. */
    public function compile(CompilationContext $ctx): string
    {
        return '';
    }
}
