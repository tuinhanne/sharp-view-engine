<?php declare(strict_types=1);

namespace Sharp\Compiler\Parser;

use Sharp\Compiler\Ast\Nodes\{
    RootNode, TextNode, EchoNode, RawEchoNode, CommentNode,
    IfNode, ForeachNode, WhileNode,
    ExtendsNode, SectionNode, YieldNode, ParentNode, IncludeNode,
    ComponentNode, SlotNode, DirectiveNode,
    BreakNode, ContinueNode, SetNode,
    PushNode, PrependNode, StackNode,
    SwitchNode, ForNode, DumpNode, DdNode, PhpNode,
    ConditionalIncludeNode, IncludeFirstNode, IncludeIfNode, PropsNode,
};
use Sharp\Compiler\Ast\Node;
use Sharp\Compiler\Lexer\Token;
use Sharp\Compiler\Lexer\TokenType;
use Sharp\Exception\ParseException;

final class Parser
{
    /** @var Token[] */
    private array $tokens;
    private int   $pos;

    /**
     * Stack entries:
     *   'node'          => Node being built
     *   'savedChildren' => Node[]  children array at the parent level before we pushed
     *   'condition'     => string  current branch condition (for if/elseif)
     *   'phase'         => string  'if'|'elseif'|'else'|'foreach'|'while'|'section'|'component'|'slot'
     *
     * @var array<int, array{node: Node, savedChildren: Node[], condition?: string, phase?: string}>
     */
    private array $stack;

    /** @var Node[] currently-accumulating children */
    private array $currentChildren;

    /** @return Node[] */
    public function parse(array $tokens): RootNode
    {
        $this->tokens          = $tokens;
        $this->pos             = 0;
        $this->stack           = [];
        $this->currentChildren = [];

        while (!$this->current()->is(TokenType::EOF)) {
            $token = $this->current();

            match ($token->type) {
                TokenType::TEXT             => $this->parseText($token),
                TokenType::ECHO_OPEN        => $this->parseEcho(),
                TokenType::RAW_ECHO_OPEN    => $this->parseRawEcho(),
                TokenType::COMMENT          => $this->parseComment($token),
                TokenType::DIRECTIVE        => $this->parseDirective($token),
                TokenType::COMPONENT_OPEN   => $this->parseComponentOpen($token),
                TokenType::SLOT_OPEN        => $this->parseSlotOpen($token),
                TokenType::COMPONENT_CLOSE  => $this->parseComponentClose($token),
                TokenType::SLOT_CLOSE       => $this->parseSlotClose($token),
                default                     => $this->advance(),
            };
        }

        if (!empty($this->stack)) {
            $top = end($this->stack);
            throw new ParseException(
                'Unclosed block: ' . get_class($top['node']),
                '',
                $top['node']->line,
            );
        }

        $root           = new RootNode();
        $root->children = $this->currentChildren;
        return $root;
    }

    // ─── Leaf parsers ────────────────────────────────────────────────────────

    private function parseText(Token $t): void
    {
        $this->currentChildren[] = new TextNode($t->value, $t->line);
        $this->advance();
    }

    private function parseEcho(): void
    {
        $this->advance(); // skip {{
        $expr = $this->expect(TokenType::EXPR);
        $this->expect(TokenType::ECHO_CLOSE);
        $this->currentChildren[] = new EchoNode($expr->value, $expr->line);
    }

    private function parseRawEcho(): void
    {
        $this->advance(); // skip {!!
        $expr = $this->expect(TokenType::EXPR);
        $this->expect(TokenType::RAW_ECHO_CLOSE);
        $this->currentChildren[] = new RawEchoNode($expr->value, $expr->line);
    }

    private function parseComment(Token $t): void
    {
        $this->currentChildren[] = new CommentNode($t->value, $t->line);
        $this->advance();
    }

    // ─── Directive dispatcher ────────────────────────────────────────────────

    private function parseDirective(Token $t): void
    {
        $name = strtolower($t->value);
        $line = $t->line;
        $this->advance(); // consume #word

        $args = null;
        if ($this->current()->is(TokenType::DIRECTIVE_ARGS)) {
            $args = trim($this->current()->value);
            $this->advance();
        }

        match ($name) {
            'if'          => $this->openIf($args ?? '', $line),
            'elseif'      => $this->branchElseif($args ?? '', $line),
            'else'        => $this->branchElse($line),
            'endif'       => $this->closeIf($line),
            'foreach'     => $this->openBlock(new ForeachNode($args ?? '', $line), 'foreach'),
            'endforeach'  => $this->closeBlock('foreach', $line),
            'while'       => $this->openBlock(new WhileNode($args ?? '', $line), 'while'),
            'endwhile'    => $this->closeBlock('while', $line),
            'section'     => $this->openBlock(new SectionNode($this->stripQuotes($args ?? ''), $line), 'section'),
            'endsection'  => $this->closeBlock('section', $line),
            'push'        => $this->openBlock(new PushNode($this->stripQuotes($args ?? ''), $line), 'push'),
            'endpush'     => $this->closeBlock('push', $line),
            'prepend'     => $this->openBlock(new PrependNode($this->stripQuotes($args ?? ''), $line), 'prepend'),
            'endprepend'  => $this->closeBlock('prepend', $line),
            'stack'       => $this->currentChildren[] = new StackNode($this->stripQuotes($args ?? ''), $line),
            'extends'     => $this->currentChildren[] = new ExtendsNode($this->stripQuotes($args ?? ''), $line),
            'yield'       => $this->currentChildren[] = $this->parseYield($args ?? '', $line),
            'parent'      => $this->currentChildren[] = new ParentNode($line),
            'include'     => $this->currentChildren[] = $this->parseInclude($args ?? '', $line),
            'includeif'   => $this->currentChildren[] = $this->parseIncludeIf($args ?? '', $line),
            'break'          => $this->currentChildren[] = new BreakNode($line),
            'continue'       => $this->currentChildren[] = new ContinueNode($line),
            'set'            => $this->currentChildren[] = new SetNode($args ?? '', $line),
            'switch'         => $this->openSwitch($args ?? '', $line),
            'case'           => $this->switchCase($args ?? '', $line),
            'default'        => $this->switchDefault($line),
            'endswitch'      => $this->closeSwitch($line),
            'for'            => $this->openBlock(new ForNode($args ?? '', $line), 'for'),
            'endfor'         => $this->closeBlock('for', $line),
            'dump'           => $this->currentChildren[] = new DumpNode($args ?? '', $line),
            'dd'             => $this->currentChildren[] = new DdNode($args ?? '', $line),
            'php'            => $this->currentChildren[] = new PhpNode($args ?? '', $line),
            'includewhen'    => $this->currentChildren[] = $this->parseIncludeWhen($args ?? '', $line, false),
            'includeunless'  => $this->currentChildren[] = $this->parseIncludeWhen($args ?? '', $line, true),
            'includefirst'   => $this->currentChildren[] = new IncludeFirstNode($args ?? '', $line),
            'props'          => $this->currentChildren[] = new PropsNode($args ?? '', $line),
            default          => $this->currentChildren[] = new DirectiveNode($name, $args ?? '', $line),
        };
    }

    // ─── If/elseif/else/endif ────────────────────────────────────────────────

    private function openIf(string $condition, int $line): void
    {
        $node = new IfNode($line);
        $this->stack[] = [
            'node'          => $node,
            'savedChildren' => $this->currentChildren,
            'condition'     => $condition,
            'phase'         => 'if',
        ];
        $this->currentChildren = [];
    }

    private function branchElseif(string $condition, int $line): void
    {
        if (empty($this->stack)) {
            throw new ParseException('#elseif without #if', '', $line);
        }
        $idx  = count($this->stack) - 1;
        $node = $this->stack[$idx]['node'];
        if (!$node instanceof IfNode) {
            throw new ParseException('#elseif without #if', '', $line);
        }
        // Save current children as the branch that just ended
        $node->branches[] = [
            'condition' => $this->stack[$idx]['condition'],
            'children'  => $this->currentChildren,
        ];
        $this->stack[$idx]['condition']  = $condition;
        $this->stack[$idx]['phase']      = 'elseif';
        $this->currentChildren           = [];
    }

    private function branchElse(int $line): void
    {
        if (empty($this->stack)) {
            throw new ParseException('#else without #if', '', $line);
        }
        $idx  = count($this->stack) - 1;
        $node = $this->stack[$idx]['node'];
        if (!$node instanceof IfNode) {
            throw new ParseException('#else without #if', '', $line);
        }
        $node->branches[] = [
            'condition' => $this->stack[$idx]['condition'],
            'children'  => $this->currentChildren,
        ];
        $this->stack[$idx]['phase'] = 'else';
        $this->currentChildren      = [];
    }

    private function closeIf(int $line): void
    {
        if (empty($this->stack)) {
            throw new ParseException('#endif without #if', '', $line);
        }
        $frame = array_pop($this->stack);
        /** @var IfNode $node */
        $node  = $frame['node'];
        if (!$node instanceof IfNode) {
            throw new ParseException('#endif without #if', '', $line);
        }
        if ($frame['phase'] === 'else') {
            $node->elseChildren = $this->currentChildren;
        } else {
            $node->branches[] = [
                'condition' => $frame['condition'],
                'children'  => $this->currentChildren,
            ];
        }
        $this->currentChildren   = $frame['savedChildren'];
        $this->currentChildren[] = $node;
    }

    // ─── Generic open/close block ────────────────────────────────────────────

    private function openBlock(Node $node, string $phase): void
    {
        $this->stack[] = [
            'node'          => $node,
            'savedChildren' => $this->currentChildren,
            'phase'         => $phase,
        ];
        $this->currentChildren = [];
    }

    private function closeBlock(string $expectedPhase, int $line): void
    {
        if (empty($this->stack)) {
            throw new ParseException("#end{$expectedPhase} without #{$expectedPhase}", '', $line);
        }
        $frame = array_pop($this->stack);
        if (($frame['phase'] ?? '') !== $expectedPhase) {
            throw new ParseException(
                "Unexpected #end{$expectedPhase} (expected to close #" . ($frame['phase'] ?? '?') . ')',
                '',
                $line,
            );
        }
        $frame['node']->children = $this->currentChildren;
        $this->currentChildren   = $frame['savedChildren'];
        $this->currentChildren[] = $frame['node'];
    }

    // ─── Component / Slot ────────────────────────────────────────────────────

    private function parseComponentOpen(Token $t): void
    {
        $rawName = $t->value;
        $line    = $t->line;
        $this->advance(); // consume COMPONENT_OPEN

        [$props, $dynamicMap] = $this->consumeAttrs();
        $isSelfClose = $this->current()->is(TokenType::COMPONENT_SELF_CLOSE);
        if ($isSelfClose) $this->advance();

        $name = $this->toKebab($rawName);
        $node = new ComponentNode($name, $props, $dynamicMap, [], $line);

        if ($isSelfClose) {
            $this->currentChildren[] = $node;
        } else {
            $this->stack[] = [
                'node'          => $node,
                'savedChildren' => $this->currentChildren,
                'phase'         => 'component',
            ];
            $this->currentChildren = [];
        }
    }

    private function parseSlotOpen(Token $t): void
    {
        $line = $t->line;
        $this->advance(); // SLOT_OPEN

        [$attrs] = $this->consumeAttrs();
        $slotName = $attrs['name'] ?? 'default';

        $isSelfClose = $this->current()->is(TokenType::COMPONENT_SELF_CLOSE);
        if ($isSelfClose) $this->advance();

        $node = new SlotNode($slotName, $line);

        if ($isSelfClose) {
            $this->currentChildren[] = $node;
        } else {
            $this->stack[] = [
                'node'          => $node,
                'savedChildren' => $this->currentChildren,
                'phase'         => 'slot',
            ];
            $this->currentChildren = [];
        }
    }

    private function parseComponentClose(Token $t): void
    {
        $this->advance();
        if (empty($this->stack)) return;

        $frame = array_pop($this->stack);
        $node  = $frame['node'];

        if ($node instanceof ComponentNode) {
            // Collect slot children from accumulated children
            $slots        = [];
            $defaultSlot  = [];
            foreach ($this->currentChildren as $child) {
                if ($child instanceof SlotNode) {
                    $slots[$child->name] = $child;
                } else {
                    $defaultSlot[] = $child;
                }
            }
            if (!empty($defaultSlot)) {
                $defaultSlotNode           = new SlotNode('default');
                $defaultSlotNode->children = $defaultSlot;
                $slots['default']          = $defaultSlotNode;
            }
            $node->slots = $slots;
        } else {
            $node->children = $this->currentChildren;
        }

        $this->currentChildren   = $frame['savedChildren'];
        $this->currentChildren[] = $node;
    }

    private function parseSlotClose(Token $t): void
    {
        $this->advance();
        if (empty($this->stack)) return;

        $frame = array_pop($this->stack);
        $node  = $frame['node'];
        $node->children = $this->currentChildren;

        $this->currentChildren   = $frame['savedChildren'];
        $this->currentChildren[] = $node;
    }

    // ─── Attr helpers ────────────────────────────────────────────────────────

    /** @return array{array<string,string>, array<string,bool>} [props, dynamicMap] */
    private function consumeAttrs(): array
    {
        $props      = [];
        $dynamicMap = [];

        while ($this->current()->is(TokenType::ATTR_NAME)) {
            $attrToken = $this->current();
            $this->advance();

            $rawKey   = $attrToken->value;
            $dynamic  = str_starts_with($rawKey, ':');
            $key      = $dynamic ? substr($rawKey, 1) : $rawKey;
            $value    = '';

            if ($this->current()->is(TokenType::ATTR_VALUE)) {
                $value = $this->current()->value;
                $this->advance();
            }

            $props[$key] = $value;
            if ($dynamic) $dynamicMap[$key] = true;
        }

        return [$props, $dynamicMap];
    }

    // ─── Token helpers ───────────────────────────────────────────────────────

    private function current(): Token
    {
        return $this->tokens[$this->pos] ?? new Token(TokenType::EOF, '', 0, 0);
    }

    private function advance(): void
    {
        $this->pos++;
    }

    private function expect(TokenType $type): Token
    {
        $t = $this->current();
        if (!$t->is($type)) {
            throw new ParseException(
                "Expected {$type->value}, got {$t->type->value}",
                '',
                $t->line,
            );
        }
        $this->advance();
        return $t;
    }

    // ─── #switch / #case / #default / #endswitch ─────────────────────────────

    private function openSwitch(string $expr, int $line): void
    {
        $node = new SwitchNode($expr, $line);
        $this->stack[] = [
            'node'          => $node,
            'savedChildren' => $this->currentChildren,
            'phase'         => 'switch',
            'sw_cases'      => [],
            'sw_current'    => null,
            'sw_in_case'    => false,
        ];
        $this->currentChildren = [];
    }

    private function switchCase(string $value, int $line): void
    {
        if (empty($this->stack) || ($this->stack[count($this->stack) - 1]['phase'] ?? '') !== 'switch') {
            throw new ParseException('#case outside #switch', '', $line);
        }
        $idx = count($this->stack) - 1;

        if ($this->stack[$idx]['sw_in_case']) {
            $this->stack[$idx]['sw_cases'][] = [
                'value'    => $this->stack[$idx]['sw_current'],
                'children' => $this->currentChildren,
            ];
        }

        $this->stack[$idx]['sw_current'] = $value;
        $this->stack[$idx]['sw_in_case'] = true;
        $this->currentChildren = [];
    }

    private function switchDefault(int $line): void
    {
        if (empty($this->stack) || ($this->stack[count($this->stack) - 1]['phase'] ?? '') !== 'switch') {
            throw new ParseException('#default outside #switch', '', $line);
        }
        $idx = count($this->stack) - 1;

        if ($this->stack[$idx]['sw_in_case']) {
            $this->stack[$idx]['sw_cases'][] = [
                'value'    => $this->stack[$idx]['sw_current'],
                'children' => $this->currentChildren,
            ];
        }

        $this->stack[$idx]['sw_current'] = null; // null = default
        $this->stack[$idx]['sw_in_case'] = true;
        $this->currentChildren = [];
    }

    private function closeSwitch(int $line): void
    {
        if (empty($this->stack)) {
            throw new ParseException('#endswitch without #switch', '', $line);
        }
        $frame = array_pop($this->stack);
        if (($frame['phase'] ?? '') !== 'switch') {
            throw new ParseException(
                'Unexpected #endswitch (expected to close #' . ($frame['phase'] ?? '?') . ')',
                '',
                $line,
            );
        }

        if ($frame['sw_in_case']) {
            $frame['sw_cases'][] = [
                'value'    => $frame['sw_current'],
                'children' => $this->currentChildren,
            ];
        }

        /** @var SwitchNode $node */
        $node        = $frame['node'];
        $node->cases = $frame['sw_cases'];

        $this->currentChildren   = $frame['savedChildren'];
        $this->currentChildren[] = $node;
    }

    // ─── Includes ────────────────────────────────────────────────────────────

    private function parseInclude(string $args, int $line): IncludeNode
    {
        $parts     = $this->splitTopLevelComma($args);
        $view      = $this->stripQuotes($parts[0] ?? '');
        $extraData = trim($parts[1] ?? '');
        return new IncludeNode($view, $extraData, $line);
    }

    private function parseIncludeIf(string $args, int $line): IncludeIfNode
    {
        $parts     = $this->splitTopLevelComma($args);
        $view      = $this->stripQuotes($parts[0] ?? '');
        $extraData = trim($parts[1] ?? '');
        return new IncludeIfNode($view, $extraData, $line);
    }

    // ─── Conditional includes ─────────────────────────────────────────────────

    private function parseIncludeWhen(string $args, int $line, bool $negate): ConditionalIncludeNode
    {
        $parts     = $this->splitTopLevelComma($args);
        $condition = trim($parts[0] ?? '');
        $view      = $this->stripQuotes($parts[1] ?? '');
        return new ConditionalIncludeNode($condition, $view, $negate, $line);
    }

    // ─── String helpers ──────────────────────────────────────────────────────

    private function parseYield(string $args, int $line): YieldNode
    {
        // Split 'name', 'default value' on first top-level comma
        $parts   = $this->splitTopLevelComma($args);
        $name    = $this->stripQuotes($parts[0] ?? '');
        $default = $this->stripQuotes($parts[1] ?? '');
        return new YieldNode($name, $default, $line);
    }

    /**
     * Split a string on the first top-level comma (not inside quotes or parens).
     * @return string[]
     */
    private function splitTopLevelComma(string $s): array
    {
        $parts = [];
        $buf   = '';
        $depth = 0;
        $inStr = false;
        $strCh = '';
        $len   = strlen($s);

        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];

            if ($inStr) {
                $buf .= $ch;
                if ($ch === $strCh && ($i === 0 || $s[$i - 1] !== '\\')) $inStr = false;
                continue;
            }
            if ($ch === '"' || $ch === "'") { $inStr = true; $strCh = $ch; $buf .= $ch; continue; }
            if ($ch === '(') { $depth++; $buf .= $ch; continue; }
            if ($ch === ')') { $depth--; $buf .= $ch; continue; }
            if ($ch === ',' && $depth === 0) { $parts[] = trim($buf); $buf = ''; continue; }
            $buf .= $ch;
        }
        if (trim($buf) !== '' || !empty($parts)) $parts[] = trim($buf);

        return $parts;
    }

    private function stripQuotes(string $s): string
    {
        return trim($s, "\"'");
    }

    /** UserCard → user-card */
    private function toKebab(string $name): string
    {
        if (str_contains($name, '-')) return strtolower($name);
        $kebab = preg_replace('/[A-Z]/', '-$0', lcfirst($name));
        return strtolower(ltrim($kebab, '-'));
    }
}
