<?php declare(strict_types=1);

namespace Sharp\Compiler\Pipeline;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\Nodes\{ExtendsNode, IncludeNode, ComponentNode, ConditionalIncludeNode, IncludeFirstNode, IncludeIfNode};
use Sharp\Compiler\CompilationContext;
use Sharp\Contract\PipelineInterface;

final class DependencyGraph implements PipelineInterface
{
    /** @var string[] */
    private array $dependencies = [];

    /** @var array<string, int> path → mtime */
    private array $mtimes = [];

    /** @param Node[] $nodes */
    public function process(array $nodes, CompilationContext $ctx): array
    {
        foreach ($nodes as $node) {
            $this->walk($node, $ctx);
        }
        return $nodes;
    }

    private function walk(Node $node, CompilationContext $ctx): void
    {
        if ($node instanceof ExtendsNode) {
            $this->addDependency($node->layout, $ctx);
        }

        if ($node instanceof IncludeNode) {
            $this->addDependency($node->view, $ctx);
        }

        if ($node instanceof ComponentNode) {
            $this->addComponentDependency($node->name, $ctx);
        }

        foreach ($node->walk() as $descendant) {
            if ($descendant === $node) continue;
            if ($descendant instanceof ExtendsNode)          $this->addDependency($descendant->layout, $ctx);
            if ($descendant instanceof IncludeNode)          $this->addDependency($descendant->view, $ctx);
            if ($descendant instanceof ComponentNode)        $this->addComponentDependency($descendant->name, $ctx);
            if ($descendant instanceof ConditionalIncludeNode) $this->addDependency($descendant->view, $ctx);
            if ($descendant instanceof IncludeIfNode)          $this->addDependency($descendant->view, $ctx);
        }
    }

    private function addDependency(string $viewKey, CompilationContext $ctx): void
    {
        if (in_array($viewKey, $this->dependencies, true)) return;
        $this->dependencies[] = $viewKey;

        // Resolve to a file path and record mtime so cache invalidates when the file changes
        if ($ctx->pathResolver !== null) {
            $path = ($ctx->pathResolver)($viewKey);
            if ($path !== null && file_exists($path)) {
                $this->mtimes[$path] = (int) filemtime($path);
            }
        }
    }

    private function addComponentDependency(string $name, CompilationContext $ctx): void
    {
        $path = $ctx->components->resolvePath($name);
        if ($path !== null && !in_array($path, $this->dependencies, true)) {
            $this->dependencies[] = $path;
            if (file_exists($path)) {
                $this->mtimes[$path] = (int) filemtime($path);
            }
        }
    }

    public function toArray(): array
    {
        return [
            'dependencies' => $this->dependencies,
            'mtimes'       => $this->mtimes,
        ];
    }

    public function getDependencies(): array { return $this->dependencies; }
    public function getMtimes(): array       { return $this->mtimes; }
}
