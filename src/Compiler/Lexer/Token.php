<?php declare(strict_types=1);

namespace Sharp\Compiler\Lexer;

final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string    $value,
        public int       $line,
        public int       $col,
    ) {}

    public function is(TokenType $type): bool
    {
        return $this->type === $type;
    }

    public function __toString(): string
    {
        return "[{$this->type->value}:{$this->line}:{$this->col}] {$this->value}";
    }
}
