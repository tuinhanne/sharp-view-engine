<?php declare(strict_types=1);

namespace Sharp\Compiler\Pipeline;

use Sharp\Compiler\Ast\Nodes\RootNode;
use Sharp\Compiler\CompilationContext;

final class CodeGeneration
{
    public function generate(RootNode $root, CompilationContext $ctx): string
    {
        $body = '';
        foreach ($root->children as $node) {
            $body .= $node->compile($ctx);
        }

        return $this->preamble() . $body;
    }

    private function preamble(): string
    {
        return "<?php\n/** Sharp compiled template — do not edit */\n?>";
    }
}
