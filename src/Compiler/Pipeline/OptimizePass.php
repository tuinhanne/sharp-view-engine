<?php declare(strict_types=1);

namespace Sharp\Compiler\Pipeline;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\Nodes\{TextNode, CommentNode, SwitchNode};
use Sharp\Compiler\CompilationContext;
use Sharp\Contract\PipelineInterface;

final class OptimizePass implements PipelineInterface
{
    /** @param Node[] $nodes */
    public function process(array $nodes, CompilationContext $ctx): array
    {
        $nodes = $this->removeComments($nodes);
        $nodes = $this->mergeTextNodes($nodes);

        // Recurse into children
        foreach ($nodes as $node) {
            if (!empty($node->children)) {
                $node->children = $this->process($node->children, $ctx);
            }
            // For IfNode branches
            if (property_exists($node, 'branches')) {
                foreach ($node->branches as &$branch) {
                    $branch['children'] = $this->process($branch['children'], $ctx);
                }
                unset($branch);
            }
            if (property_exists($node, 'elseChildren') && $node->elseChildren !== null) {
                $node->elseChildren = $this->process($node->elseChildren, $ctx);
            }
            // For SwitchNode cases
            if ($node instanceof SwitchNode) {
                foreach ($node->cases as &$case) {
                    $case['children'] = $this->process($case['children'], $ctx);
                }
                unset($case);
            }
        }

        return $nodes;
    }

    /** @param Node[] $nodes @return Node[] */
    private function removeComments(array $nodes): array
    {
        return array_values(array_filter($nodes, fn($n) => !($n instanceof CommentNode)));
    }

    /** @param Node[] $nodes @return Node[] */
    private function mergeTextNodes(array $nodes): array
    {
        $result = [];
        $buf    = null;

        foreach ($nodes as $node) {
            if ($node instanceof TextNode) {
                if ($buf === null) {
                    $buf = new TextNode('', $node->line);
                }
                $buf->content .= $node->content;
            } else {
                if ($buf !== null) {
                    if ($buf->content !== '') $result[] = $buf;
                    $buf = null;
                }
                $result[] = $node;
            }
        }

        if ($buf !== null && $buf->content !== '') {
            $result[] = $buf;
        }

        return $result;
    }
}
