<?php declare(strict_types=1);

namespace Sharp\Tests\Unit\Compiler;

use PHPUnit\Framework\TestCase;
use Sharp\Compiler\Lexer\Lexer;
use Sharp\Compiler\Parser\Parser;
use Sharp\Compiler\Ast\Nodes\{
    RootNode, TextNode, EchoNode, IfNode, ForeachNode, WhileNode,
    ExtendsNode, SectionNode, YieldNode, ParentNode, IncludeNode, DirectiveNode,
    PushNode, PrependNode, StackNode,
};

final class ParserTest extends TestCase
{
    private function parse(string $source): RootNode
    {
        $tokens = (new Lexer())->tokenize($source);
        return (new Parser())->parse($tokens);
    }

    public function test_plain_text(): void
    {
        $root = $this->parse('Hello');
        self::assertInstanceOf(TextNode::class, $root->children[0]);
        self::assertSame('Hello', $root->children[0]->content);
    }

    public function test_echo_node(): void
    {
        $root = $this->parse('{{ $name }}');
        self::assertInstanceOf(EchoNode::class, $root->children[0]);
        self::assertSame('$name', $root->children[0]->expression);
    }

    public function test_if_endif(): void
    {
        $root = $this->parse('#if($x)yes#endif');
        self::assertInstanceOf(IfNode::class, $root->children[0]);
        $node = $root->children[0];
        self::assertCount(1, $node->branches);
        self::assertSame('$x', $node->branches[0]['condition']);
    }

    public function test_if_else_endif(): void
    {
        $root = $this->parse('#if($x)A#else B#endif');
        $node = $root->children[0];
        self::assertInstanceOf(IfNode::class, $node);
        self::assertNotNull($node->elseChildren);
    }

    public function test_if_elseif_else_endif(): void
    {
        $root = $this->parse('#if($a)A#elseif($b)B#else C#endif');
        $node = $root->children[0];
        self::assertCount(2, $node->branches);
        self::assertNotNull($node->elseChildren);
    }

    public function test_foreach(): void
    {
        $root = $this->parse('#foreach($items as $item)x#endforeach');
        self::assertInstanceOf(ForeachNode::class, $root->children[0]);
        self::assertSame('$items as $item', $root->children[0]->expression);
    }

    public function test_while(): void
    {
        $root = $this->parse('#while($i > 0)x#endwhile');
        self::assertInstanceOf(WhileNode::class, $root->children[0]);
    }

    public function test_extends(): void
    {
        $root = $this->parse("#extends('layouts.main')");
        self::assertInstanceOf(ExtendsNode::class, $root->children[0]);
        self::assertSame('layouts.main', $root->children[0]->layout);
    }

    public function test_section_endsection(): void
    {
        $root = $this->parse("#section('content')hello#endsection");
        self::assertInstanceOf(SectionNode::class, $root->children[0]);
        self::assertSame('content', $root->children[0]->name);
    }

    public function test_yield(): void
    {
        $root = $this->parse("#yield('content')");
        self::assertInstanceOf(YieldNode::class, $root->children[0]);
        self::assertSame('content', $root->children[0]->name);
    }

    public function test_parent(): void
    {
        $root = $this->parse('#parent');
        self::assertInstanceOf(ParentNode::class, $root->children[0]);
    }

    public function test_include(): void
    {
        $root = $this->parse("#include('partials.header')");
        self::assertInstanceOf(IncludeNode::class, $root->children[0]);
        self::assertSame('partials.header', $root->children[0]->view);
    }

    public function test_custom_directive(): void
    {
        $root = $this->parse('#money($price)');
        self::assertInstanceOf(DirectiveNode::class, $root->children[0]);
        self::assertSame('money', $root->children[0]->name);
        self::assertSame('$price', $root->children[0]->args);
    }

    public function test_nested_if_foreach(): void
    {
        $source = '#if($show)#foreach($items as $item){{ $item }}#endforeach#endif';
        $root   = $this->parse($source);
        $ifNode = $root->children[0];
        self::assertInstanceOf(IfNode::class, $ifNode);
        self::assertInstanceOf(ForeachNode::class, $ifNode->branches[0]['children'][0]);
    }

    public function test_unclosed_block_throws(): void
    {
        $this->expectException(\Sharp\Exception\ParseException::class);
        $this->parse('#if($x)missing endif');
    }

    public function test_push_endpush(): void
    {
        $root = $this->parse("#push('scripts')<script src=\"app.js\"></script>#endpush");
        self::assertInstanceOf(PushNode::class, $root->children[0]);
        self::assertSame('scripts', $root->children[0]->name);
        self::assertNotEmpty($root->children[0]->children);
    }

    public function test_prepend_endprepend(): void
    {
        $root = $this->parse("#prepend('styles')<link rel=\"stylesheet\" href=\"app.css\">#endprepend");
        self::assertInstanceOf(PrependNode::class, $root->children[0]);
        self::assertSame('styles', $root->children[0]->name);
    }

    public function test_stack_leaf(): void
    {
        $root = $this->parse("#stack('scripts')");
        self::assertInstanceOf(StackNode::class, $root->children[0]);
        self::assertSame('scripts', $root->children[0]->name);
        self::assertEmpty($root->children[0]->children);
    }

    public function test_endpush_without_push_throws(): void
    {
        $this->expectException(\Sharp\Exception\ParseException::class);
        $this->parse('#endpush');
    }

    public function test_endprepend_without_prepend_throws(): void
    {
        $this->expectException(\Sharp\Exception\ParseException::class);
        $this->parse('#endprepend');
    }

    public function test_push_nested_in_foreach(): void
    {
        $source = "#foreach(\$items as \$item)#push('scripts')x#endpush#endforeach";
        $root   = $this->parse($source);
        self::assertInstanceOf(ForeachNode::class, $root->children[0]);
        self::assertInstanceOf(PushNode::class, $root->children[0]->children[0]);
    }
}
