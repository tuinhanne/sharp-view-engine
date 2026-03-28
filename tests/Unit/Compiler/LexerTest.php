<?php declare(strict_types=1);

namespace Sharp\Tests\Unit\Compiler;

use PHPUnit\Framework\TestCase;
use Sharp\Compiler\Lexer\Lexer;
use Sharp\Compiler\Lexer\TokenType;

final class LexerTest extends TestCase
{
    private Lexer $lexer;

    protected function setUp(): void
    {
        $this->lexer = new Lexer();
    }

    private function tokenize(string $source): array
    {
        return $this->lexer->tokenize($source);
    }

    private function types(array $tokens): array
    {
        return array_map(fn($t) => $t->type, $tokens);
    }

    public function test_plain_text(): void
    {
        $tokens = $this->tokenize('Hello world');
        self::assertSame(TokenType::TEXT, $tokens[0]->type);
        self::assertSame('Hello world', $tokens[0]->value);
    }

    public function test_echo_expression(): void
    {
        $tokens = $this->tokenize('{{ $name }}');
        self::assertSame(TokenType::ECHO_OPEN,  $tokens[0]->type);
        self::assertSame(TokenType::EXPR,        $tokens[1]->type);
        self::assertSame('$name',                $tokens[1]->value);
        self::assertSame(TokenType::ECHO_CLOSE,  $tokens[2]->type);
    }

    public function test_raw_echo_expression(): void
    {
        $tokens = $this->tokenize('{!! $html !!}');
        self::assertSame(TokenType::RAW_ECHO_OPEN,  $tokens[0]->type);
        self::assertSame(TokenType::EXPR,             $tokens[1]->type);
        self::assertSame('$html',                     $tokens[1]->value);
        self::assertSame(TokenType::RAW_ECHO_CLOSE,   $tokens[2]->type);
    }

    public function test_comment_is_stripped(): void
    {
        $tokens = $this->tokenize('<!-- this is a comment -->');
        self::assertSame(TokenType::COMMENT, $tokens[0]->type);
    }

    public function test_directive_without_args(): void
    {
        $tokens = $this->tokenize('#else');
        self::assertSame(TokenType::DIRECTIVE, $tokens[0]->type);
        self::assertSame('else',               $tokens[0]->value);
    }

    public function test_directive_with_args(): void
    {
        $tokens = $this->tokenize('#if($user->isAdmin)');
        self::assertSame(TokenType::DIRECTIVE,      $tokens[0]->type);
        self::assertSame('if',                       $tokens[0]->value);
        self::assertSame(TokenType::DIRECTIVE_ARGS,  $tokens[1]->type);
        self::assertSame('$user->isAdmin',           $tokens[1]->value);
    }

    public function test_directive_nested_parens_in_args(): void
    {
        $tokens = $this->tokenize('#if(count($items) > 0)');
        self::assertSame('count($items) > 0', $tokens[1]->value);
    }

    public function test_component_pascal_self_close(): void
    {
        $tokens = $this->tokenize('<UserCard />');
        self::assertSame(TokenType::COMPONENT_OPEN,       $tokens[0]->type);
        self::assertSame('UserCard',                       $tokens[0]->value);
        self::assertSame(TokenType::COMPONENT_SELF_CLOSE,  $tokens[1]->type);
    }

    public function test_component_kebab_self_close(): void
    {
        $tokens = $this->tokenize('<user-card />');
        self::assertSame(TokenType::COMPONENT_OPEN,       $tokens[0]->type);
        self::assertSame('user-card',                      $tokens[0]->value);
        self::assertSame(TokenType::COMPONENT_SELF_CLOSE,  $tokens[1]->type);
    }

    public function test_component_with_props(): void
    {
        $tokens = $this->tokenize('<UserCard :user="$user" class="card" />');
        $types  = $this->types($tokens);

        self::assertContains(TokenType::ATTR_NAME,  $types);
        self::assertContains(TokenType::ATTR_VALUE, $types);

        // Find :user attr
        $attrNames = array_filter($tokens, fn($t) => $t->type === TokenType::ATTR_NAME);
        $names     = array_map(fn($t) => $t->value, array_values($attrNames));
        self::assertContains(':user', $names);
        self::assertContains('class', $names);
    }

    public function test_component_close_tag(): void
    {
        $tokens = $this->tokenize('<user-card></user-card>');
        self::assertSame(TokenType::COMPONENT_OPEN,  $tokens[0]->type);
        self::assertSame(TokenType::COMPONENT_CLOSE, $tokens[1]->type);
    }

    public function test_slot_open_close(): void
    {
        $tokens = $this->tokenize('<slot name="footer"></slot>');
        self::assertSame(TokenType::SLOT_OPEN,  $tokens[0]->type);
        self::assertSame(TokenType::SLOT_CLOSE, $tokens[count($tokens) - 2]->type);
    }

    public function test_line_tracking(): void
    {
        $tokens = $this->tokenize("line one\n{{ \$x }}");
        $echo   = array_values(array_filter($tokens, fn($t) => $t->type === TokenType::ECHO_OPEN))[0];
        self::assertSame(2, $echo->line);
    }

    public function test_regular_html_tag_is_text(): void
    {
        $source = '<div class="foo">hello</div>';
        $tokens = $this->tokenize($source);
        // No COMPONENT_OPEN token expected
        $types  = $this->types($tokens);
        self::assertNotContains(TokenType::COMPONENT_OPEN, $types);
        self::assertContains(TokenType::TEXT, $types);
    }
}
