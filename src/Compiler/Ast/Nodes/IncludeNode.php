<?php declare(strict_types=1);

namespace Sharp\Compiler\Ast\Nodes;

use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Ast\NodeType;
use Sharp\Compiler\CompilationContext;

final class IncludeNode extends Node
{
    public NodeType $type = NodeType::INCLUDE;

    /**
     * @param string $view      View key to include
     * @param string $extraData Optional PHP array expression merged into scope, e.g. "['active' => 'home']"
     */
    public function __construct(
        public string $view,
        public string $extraData = '',
        int $line = 0,
    ) {
        $this->line = $line;
    }

    public function compile(CompilationContext $ctx): string
    {
        $view = addslashes($this->view);
        $data = $this->extraData !== ''
            ? "array_merge(get_defined_vars(), {$this->extraData})"
            : 'get_defined_vars()';

        return "<?php echo \$__env->renderInclude('{$view}', {$data}); ?>";
    }
}
