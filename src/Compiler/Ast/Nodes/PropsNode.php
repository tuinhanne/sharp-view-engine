<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;
use Sharp\Compiler\Props\PropDefinition;
use Sharp\Compiler\Props\PropsParser;

/**
 * Represents a #props([name: type, name?: type]) directive.
 *
 * Compiles to:
 *   - A PHPDoc block with @var annotations (IDE type inference)
 *   - Runtime required-prop checks (throws RenderException if missing)
 *   - Runtime type checks (throws RenderException on type mismatch)
 *   - Variable assignments from $__props
 */
final class PropsNode extends Node
{
    public NodeType $type = NodeType::PROPS;

    public function __construct(public string $args, int $line = 0)
    {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        $definitions = (new PropsParser())->parse($this->args);

        if (empty($definitions)) return '';

        $out  = "<?php\n";
        $out .= $this->buildPhpDoc($definitions);
        $out .= $this->buildValidation($definitions);
        $out .= '?>';

        return $out;
    }

    /** @param PropDefinition[] $defs */
    private function buildPhpDoc(array $defs): string
    {
        $lines = ["/**\n"];
        foreach ($defs as $def) {
            $varType  = $def->isClass ? ltrim($def->type, '\\') : $def->type;
            $varType .= $def->nullable ? '|null' : '';
            $lines[]  = " * @var {$varType} \${$def->name}\n";
        }
        $lines[] = " */\n";
        return implode('', $lines);
    }

    /** @param PropDefinition[] $defs */
    private function buildValidation(array $defs): string
    {
        $out = '';
        foreach ($defs as $def) {
            $keyExpr = var_export($def->name, true);
            $varExpr = "\$__props[{$keyExpr}]";

            // Required presence check
            if (!$def->nullable) {
                $out .= "if (!array_key_exists({$keyExpr}, \$__props)) {\n";
                $out .= "    throw new \\Sharp\\Exception\\RenderException(\"Required prop '{$def->name}' missing in component.\");\n";
                $out .= "}\n";
            }

            // Type check (skip for mixed)
            if ($def->type !== 'mixed') {
                $typeLabel  = $def->isClass ? ltrim($def->type, '\\') : $def->type;
                $typeLabel .= $def->nullable ? '|null' : '';
                $typeCheck  = $this->buildTypeCheck($def, $varExpr);

                if ($def->nullable) {
                    $out .= "if (array_key_exists({$keyExpr}, \$__props) && {$varExpr} !== null && !({$typeCheck})) {\n";
                } else {
                    $out .= "if (!({$typeCheck})) {\n";
                }
                $out .= "    throw new \\Sharp\\Exception\\RenderException(\"Prop '{$def->name}' must be {$typeLabel}, got \" . get_debug_type({$varExpr}));\n";
                $out .= "}\n";
            }

            // Variable assignment
            if ($def->nullable) {
                $out .= "\${$def->name} = {$varExpr} ?? null;\n";
            } else {
                $out .= "\${$def->name} = {$varExpr};\n";
            }
        }
        return $out;
    }

    private function buildTypeCheck(PropDefinition $def, string $varExpr): string
    {
        if ($def->isClass) {
            return "{$varExpr} instanceof {$def->type}";
        }

        return match ($def->type) {
            'string' => "is_string({$varExpr})",
            'int'    => "is_int({$varExpr})",
            'float'  => "is_float({$varExpr}) || is_int({$varExpr})",
            'bool'   => "is_bool({$varExpr})",
            'array'  => "is_array({$varExpr})",
            default  => 'true',
        };
    }
}
