<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Annotation\HtmlAnnotator;
use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class TextNode extends Node
{
    public NodeType $type = NodeType::TEXT;

    public function __construct(public string $content, int $line = 0)
    {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        if (!$ctx->devMode) {
            return $this->content;
        }
        return HtmlAnnotator::inject($this->content, $ctx->absoluteSpPath, $this->line);
    }
}
