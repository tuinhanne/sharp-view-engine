<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class ComponentNode extends Node
{
    public NodeType $type = NodeType::COMPONENT;

    /**
     * @param string                          $name       kebab-case component name
     * @param array<string, string>           $props      name => raw value (dynamic values are PHP exprs)
     * @param array<string, bool>             $dynamicMap which props are dynamic (:attr)
     * @param array<string, SlotNode|null>    $slots      named slots
     */
    public function __construct(
        public string $name,
        public array  $props      = [],
        public array  $dynamicMap = [],
        public array  $slots      = [],
        int $line = 0,
    ) {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        $name  = addslashes($this->name);
        $props = $this->compileProps();
        $slots = $this->compileSlots($ctx);

        $render = "\$__env->renderComponent('{$name}', {$props}, {$slots})";

        if (!$ctx->devMode) {
            return "<?php echo {$render}; ?>";
        }

        $spPath = addslashes($ctx->absoluteSpPath);
        $line   = $this->line;
        $cname  = addslashes($this->name);

        // Dev mode: track render time, cache hit, and component ID via DebugRegistry,
        // then annotate the output HTML with data-sharp-* attributes.
        return implode('', [
            '<?php ',
            "\$__dbgId = \\Sharp\\Runtime\\Debug\\DebugRegistry::getInstance()",
            "->pushComponent('{$cname}', '{$spPath}', {$line}, {$props}, array_keys({$slots}));",
            "\$__rStart = hrtime(true);",
            "\$__rOut = {$render};",
            "\$__rMs = (hrtime(true) - \$__rStart) / 1e6;",
            "\$__dbgHit = \\Sharp\\Runtime\\Debug\\DebugRegistry::getInstance()->popCacheHit();",
            "echo \\Sharp\\Runtime\\Annotation\\RuntimeAnnotator::wrap(",
            "\$__rOut, '{$spPath}', {$line}, '{$cname}', {$props}, array_keys({$slots}), \$__rMs, \$__dbgId, \$__dbgHit",
            "); ?>",
        ]);
    }

    private function compileProps(): string
    {
        if (empty($this->props)) return '[]';

        $parts = [];
        foreach ($this->props as $key => $value) {
            $k = var_export($key, true);
            $v = isset($this->dynamicMap[$key]) ? $value : var_export($value, true);
            $parts[] = "{$k} => {$v}";
        }

        return '[' . implode(', ', $parts) . ']';
    }

    private function compileSlots(CompilationContext $ctx): string
    {
        if (empty($this->slots)) return '[]';

        $parts = [];
        foreach ($this->slots as $slotName => $slotNode) {
            $k = var_export($slotName, true);
            if ($slotNode === null) {
                $parts[] = "{$k} => ''";
            } else {
                $inner = addslashes($slotNode->compile($ctx));
                $parts[] = "{$k} => '{$inner}'";
            }
        }

        return '[' . implode(', ', $parts) . ']';
    }
}
