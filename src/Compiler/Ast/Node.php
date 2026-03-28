<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast;

use Sharp\Compiler\CompilationContext;

abstract class Node
{
    /** @var Node[] */
    public array $children = [];

    public int $line = 0;
    public int $col  = 0;

    abstract public function compile(CompilationContext $ctx): string;

    /** @return Node[] depth-first descendants including self */
    public function walk(): array
    {
        $result = [$this];
        foreach ($this->children as $child) {
            foreach ($child->walk() as $node) {
                $result[] = $node;
            }
        }
        return $result;
    }

    protected function compileChildren(CompilationContext $ctx): string
    {
        $out = '';
        foreach ($this->children as $child) {
            $out .= $child->compile($ctx);
        }
        return $out;
    }
}
