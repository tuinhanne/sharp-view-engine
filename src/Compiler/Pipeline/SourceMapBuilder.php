<?php declare(strict_types=1);

namespace Sharp\Compiler\Pipeline;

use Sharp\Compiler\Ast\Nodes\RootNode;
use Sharp\Compiler\CompilationContext;

/**
 * Generates compiled PHP (same as CodeGeneration) while tracking spLine → phpLine
 * mappings for every AST node. Writes the result as a .ast file via FileCache.
 */
final class SourceMapBuilder
{
    private array $mappings = [];

    public function generate(RootNode $root, CompilationContext $ctx): string
    {
        $preamble = $this->preamble();
        // Count lines produced by the preamble (1-based line tracking)
        $currentPhpLine = substr_count($preamble, "\n") + 1;

        $body = '';
        foreach ($root->children as $node) {
            if ($node->line > 0) {
                $this->mappings[] = ['spLine' => $node->line, 'phpLine' => $currentPhpLine];
            }
            $chunk = $node->compile($ctx);
            $body .= $chunk;
            $currentPhpLine += substr_count($chunk, "\n");
        }

        return $preamble . $body;
    }

    /**
     * Returns the structured data for the .ast file.
     *
     * @param string $absoluteSpPath  Absolute path to the source .sp file.
     * @param string $viewKey         The view key used to compile this template.
     */
    public function getAst(string $absoluteSpPath, string $viewKey): array
    {
        return [
            'version'  => 1,
            'source'   => $absoluteSpPath,
            'viewKey'  => $viewKey,
            'mappings' => $this->mappings,
        ];
    }

    private function preamble(): string
    {
        return "<?php\n/** Sharp compiled template — do not edit */\n?>";
    }
}
