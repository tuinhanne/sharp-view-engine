<?php declare(strict_types=1);

namespace Sharp\Security;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\Nodes\{ExtendsNode, SectionNode, IfNode, ForeachNode, WhileNode, EchoNode, RawEchoNode, DirectiveNode, SetNode, SwitchNode, ForNode, DumpNode, DdNode, PhpNode, ConditionalIncludeNode};
use Sharp\Compiler\CompilationContext;
use Sharp\Contract\PipelineInterface;
use Sharp\Exception\CompileException;

final class AstValidator implements PipelineInterface
{
    private const DANGEROUS_FUNCTIONS = [
        'exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open',
        'eval', 'assert', 'preg_replace',
        'file_get_contents', 'file_put_contents', 'unlink', 'rmdir',
        'base64_decode', 'hex2bin',
    ];

    /** @param Node[] $nodes */
    public function process(array $nodes, CompilationContext $ctx): array
    {
        $this->validateExtendsPosition($nodes, $ctx);

        if ($ctx->config->isSandboxed()) {
            foreach ($nodes as $node) {
                foreach ($node->walk() as $descendant) {
                    $this->validateSandbox($descendant, $ctx);
                }
            }
        }

        return $nodes;
    }

    private function validateExtendsPosition(array $nodes, CompilationContext $ctx): void
    {
        $foundExtends = false;
        foreach ($nodes as $i => $node) {
            if ($node instanceof ExtendsNode) {
                if ($i !== 0 && $foundExtends === false) {
                    // Allow #extends as very first node (whitespace text before is OK)
                }
                $foundExtends = true;
            }
        }

        // Ensure #extends doesn't appear inside control blocks
        foreach ($nodes as $node) {
            if ($node instanceof IfNode || $node instanceof ForeachNode
                || $node instanceof WhileNode || $node instanceof SwitchNode
                || $node instanceof ForNode
            ) {
                foreach ($node->walk() as $descendant) {
                    if ($descendant instanceof ExtendsNode) {
                        throw new CompileException(
                            '#extends may not be nested inside a control block',
                            $ctx->viewKey,
                            $descendant->line,
                        );
                    }
                    if ($descendant instanceof SectionNode) {
                        throw new CompileException(
                            '#section may not be nested inside a control block',
                            $ctx->viewKey,
                            $descendant->line,
                        );
                    }
                }
            }
        }
    }

    private function validateSandbox(Node $node, CompilationContext $ctx): void
    {
        // Nodes that are unconditionally blocked in sandbox mode
        if ($node instanceof DumpNode || $node instanceof DdNode) {
            $directive = $node instanceof DumpNode ? 'dump' : 'dd';
            throw new CompileException(
                "Sandbox violation: [#{$directive}] is not allowed in sandbox mode",
                $ctx->viewKey,
                $node->line,
            );
        }

        if ($node instanceof PhpNode) {
            throw new CompileException(
                'Sandbox violation: [#php] blocks are not allowed in sandbox mode',
                $ctx->viewKey,
                $node->line,
            );
        }

        // IfNode: each branch condition must be checked individually
        if ($node instanceof IfNode) {
            foreach ($node->branches as $branch) {
                $this->checkExpression($branch['condition'] ?? '', $node->line, $ctx);
            }
            return;
        }

        // SwitchNode: check the switch expression
        if ($node instanceof SwitchNode) {
            $this->checkExpression($node->expression, $node->line, $ctx);
            return;
        }

        $expr = match (true) {
            $node instanceof EchoNode               => $node->expression,
            $node instanceof RawEchoNode            => $node->expression,
            $node instanceof DirectiveNode          => $node->args,
            $node instanceof ForeachNode            => $node->expression,
            $node instanceof WhileNode              => $node->condition,
            $node instanceof SetNode                => $node->expression,
            $node instanceof ForNode                => $node->expression,
            $node instanceof ConditionalIncludeNode => $node->condition,
            default                                 => null,
        };

        if ($expr === null) return;

        $this->checkExpression($expr, $node->line, $ctx);
    }

    private function checkExpression(string $expr, int $line, CompilationContext $ctx): void
    {
        foreach (self::DANGEROUS_FUNCTIONS as $fn) {
            if (preg_match('/\b' . preg_quote($fn, '/') . '\s*\(/i', $expr)) {
                throw new CompileException(
                    "Sandbox violation: [{$fn}] is not allowed in template expressions",
                    $ctx->viewKey,
                    $line,
                );
            }
        }
    }
}
