<?php declare(strict_types=1);

namespace Sharp\Compiler\Lexer;

use Sharp\Exception\ParseException;

final class Lexer
{
    private string $src;
    private int    $pos;
    private int    $len;
    private int    $line;
    private int    $col;
    private string $templateName = '';

    /** @var Token[] */
    private array $tokens;

    /** @return Token[] */
    public function tokenize(string $source, string $templateName = ''): array
    {
        $this->templateName = $templateName;
        $this->src    = $source;
        $this->pos    = 0;
        $this->len    = strlen($source);
        $this->line   = 1;
        $this->col    = 1;
        $this->tokens = [];

        $textBuf  = '';
        $textLine = 1;
        $textCol  = 1;

        while ($this->pos < $this->len) {
            $ch = $this->src[$this->pos];

            // ── {{ expr }} ───────────────────────────────────────────────
            if ($ch === '{' && $this->peek(1) === '{' && $this->peek(2) !== '-') {
                $this->flushText($textBuf, $textLine, $textCol);
                $openLine = $this->line;
                $this->advance(2);
                $expr = $this->scanUntil('}}');
                if ($this->pos >= $this->len) {
                    throw new ParseException("Unclosed '{{' — missing '}}'", $templateName, $openLine);
                }
                $this->emit(TokenType::ECHO_OPEN,  '{{',         $this->line, $this->col);
                $this->emit(TokenType::EXPR,        trim($expr),  $this->line, $this->col);
                $this->emit(TokenType::ECHO_CLOSE,  '}}',         $this->line, $this->col);
                $this->advance(2);
                $textBuf = ''; $textLine = $this->line; $textCol = $this->col;
                continue;
            }

            // ── {!! expr !!} ─────────────────────────────────────────────
            if ($ch === '{' && $this->peek(1) === '!' && $this->peek(2) === '!') {
                $this->flushText($textBuf, $textLine, $textCol);
                $openLine = $this->line;
                $this->advance(3);
                $expr = $this->scanUntil('!!}');
                if ($this->pos >= $this->len) {
                    throw new ParseException("Unclosed '{!!' — missing '!!}'", $templateName, $openLine);
                }
                $this->emit(TokenType::RAW_ECHO_OPEN,  '{!!',        $this->line, $this->col);
                $this->emit(TokenType::EXPR,            trim($expr),  $this->line, $this->col);
                $this->emit(TokenType::RAW_ECHO_CLOSE,  '!!}',        $this->line, $this->col);
                $this->advance(3);
                $textBuf = ''; $textLine = $this->line; $textCol = $this->col;
                continue;
            }

            // ── <!-- comment --> ─────────────────────────────────────────
            if ($ch === '<' && $this->peek(1) === '!' && $this->peek(2) === '-' && $this->peek(3) === '-') {
                $this->flushText($textBuf, $textLine, $textCol);
                $saveLine = $this->line; $saveCol = $this->col;
                $this->advance(4); // skip <!--
                $comment = $this->scanUntil('-->');
                if ($this->pos >= $this->len) {
                    throw new ParseException("Unclosed '<!--' — missing '-->'", $templateName, $saveLine);
                }
                $this->emit(TokenType::COMMENT, $comment, $saveLine, $saveCol);
                $this->advance(3); // skip -->
                $textBuf = ''; $textLine = $this->line; $textCol = $this->col;
                continue;
            }

            // ── #directive ───────────────────────────────────────────────
            if ($ch === '#' && $this->pos + 1 < $this->len && ctype_alpha($this->src[$this->pos + 1])) {
                $this->flushText($textBuf, $textLine, $textCol);
                $saveLine = $this->line; $saveCol = $this->col;
                $this->advance(1); // skip #
                $name = $this->scanIdentifier();
                $this->emit(TokenType::DIRECTIVE, $name, $saveLine, $saveCol);

                // Raw block directives: scan content until #end{name} without tokenizing inside
                if ($name === 'php') {
                    $content = $this->scanRawBlock('endphp');
                    $this->emit(TokenType::DIRECTIVE_ARGS, $content, $saveLine, $saveCol);
                    $textBuf = ''; $textLine = $this->line; $textCol = $this->col;
                    continue;
                }

                // Optional (args)
                if ($this->pos < $this->len && $this->src[$this->pos] === '(') {
                    $argsLine = $this->line; $argsCol = $this->col;
                    $this->advance(1); // skip (
                    $args = $this->scanBalancedParens();
                    $this->emit(TokenType::DIRECTIVE_ARGS, $args, $argsLine, $argsCol);
                }
                $textBuf = ''; $textLine = $this->line; $textCol = $this->col;
                continue;
            }

            // ── </tag> close ─────────────────────────────────────────────
            if ($ch === '<' && $this->peek(1) === '/') {
                $saveLine = $this->line; $saveCol = $this->col;
                $savedPos = $this->pos;

                $this->advance(2); // skip </
                $tagName = $this->scanTagName();

                if ($this->isComponentName($tagName)) {
                    $this->flushText($textBuf, $textLine, $textCol);
                    // skip to >
                    while ($this->pos < $this->len && $this->src[$this->pos] !== '>') {
                        $this->advance();
                    }
                    if ($this->pos < $this->len) $this->advance(); // >

                    $type = $tagName === 'slot' ? TokenType::SLOT_CLOSE : TokenType::COMPONENT_CLOSE;
                    $this->emit($type, '</' . $tagName . '>', $saveLine, $saveCol);
                    $textBuf = ''; $textLine = $this->line; $textCol = $this->col;
                    continue;
                }

                // Not a component tag — restore and treat as plain text
                $this->pos  = $savedPos;
                $this->line = $saveLine;
                $this->col  = $saveCol;
            }

            // ── <ComponentOpen ───────────────────────────────────────────
            if ($ch === '<' && $this->pos + 1 < $this->len && $this->src[$this->pos + 1] !== '/') {
                $saveLine = $this->line; $saveCol = $this->col;
                $savedPos = $this->pos;

                $this->advance(1); // skip <
                $tagName = $this->scanTagName();

                if ($this->isComponentName($tagName)) {
                    $this->flushText($textBuf, $textLine, $textCol);

                    $type = $tagName === 'slot' ? TokenType::SLOT_OPEN : TokenType::COMPONENT_OPEN;
                    $this->emit($type, $tagName, $saveLine, $saveCol);

                    // Scan attributes
                    $this->scanAttrs();

                    // Self-close />  or  open >
                    $this->skipWhitespace();
                    if ($this->pos < $this->len && $this->src[$this->pos] === '/' && $this->peek(1) === '>') {
                        $this->advance(2);
                        $this->emit(TokenType::COMPONENT_SELF_CLOSE, '/>', $this->line, $this->col);
                    } elseif ($this->pos < $this->len && $this->src[$this->pos] === '>') {
                        $this->advance();
                    }

                    $textBuf = ''; $textLine = $this->line; $textCol = $this->col;
                    continue;
                }

                // Not a component — restore
                $this->pos  = $savedPos;
                $this->line = $saveLine;
                $this->col  = $saveCol;
            }

            // ── plain text ───────────────────────────────────────────────
            if ($textBuf === '') {
                $textLine = $this->line;
                $textCol  = $this->col;
            }
            $textBuf .= $ch;
            $this->advance();
        }

        $this->flushText($textBuf, $textLine, $textCol);
        $this->emit(TokenType::EOF, '', $this->line, $this->col);

        return $this->tokens;
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    private function peek(int $offset): string
    {
        $idx = $this->pos + $offset;
        return $idx < $this->len ? $this->src[$idx] : '';
    }

    private function advance(int $n = 1): void
    {
        for ($i = 0; $i < $n; $i++) {
            if ($this->pos >= $this->len) break;
            if ($this->src[$this->pos] === "\n") {
                $this->line++;
                $this->col = 1;
            } else {
                $this->col++;
            }
            $this->pos++;
        }
    }

    private function emit(TokenType $type, string $value, int $line, int $col): void
    {
        $this->tokens[] = new Token($type, $value, $line, $col);
    }

    private function flushText(string $buf, int $line, int $col): void
    {
        if ($buf !== '') {
            $this->emit(TokenType::TEXT, $buf, $line, $col);
        }
    }

    /** Scan until the exact $needle, return content before it (does NOT consume needle). */
    private function scanUntil(string $needle): string
    {
        $buf    = '';
        $needleLen = strlen($needle);

        while ($this->pos < $this->len) {
            if (substr($this->src, $this->pos, $needleLen) === $needle) {
                break;
            }
            $buf .= $this->src[$this->pos];
            $this->advance();
        }

        return $buf;
    }

    /** Scan balanced parens — cursor is right after the opening `(`. Returns inner content. */
    private function scanBalancedParens(): string
    {
        $buf   = '';
        $depth = 1;

        while ($this->pos < $this->len && $depth > 0) {
            $c = $this->src[$this->pos];
            if ($c === '(') {
                $depth++;
                $buf .= $c;
                $this->advance();
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    $this->advance(); // skip closing )
                    break;
                }
                $buf .= $c;
                $this->advance();
            } else {
                $buf .= $c;
                $this->advance();
            }
        }

        return $buf;
    }

    private function scanIdentifier(): string
    {
        $buf = '';
        while ($this->pos < $this->len && (ctype_alnum($this->src[$this->pos]) || $this->src[$this->pos] === '_')) {
            $buf .= $this->src[$this->pos];
            $this->advance();
        }
        return $buf;
    }

    private function scanTagName(): string
    {
        $buf = '';
        while ($this->pos < $this->len) {
            $c = $this->src[$this->pos];
            if (ctype_alnum($c) || $c === '-' || $c === '_') {
                $buf .= $c;
                $this->advance();
            } else {
                break;
            }
        }
        return $buf;
    }

    private function isComponentName(string $name): bool
    {
        if ($name === '') return false;
        if ($name === 'slot') return true;
        if (ctype_upper($name[0])) return true;   // PascalCase
        if (str_contains($name, '-')) return true; // kebab-case
        return false;
    }

    private function skipWhitespace(): void
    {
        while ($this->pos < $this->len && ctype_space($this->src[$this->pos])) {
            $this->advance();
        }
    }

    /**
     * Scan raw content until `#endDirective` (e.g. `#endphp`), consuming the end marker.
     * Used for blocks whose inner content must not be tokenized as Sharp syntax.
     */
    private function scanRawBlock(string $endDirective): string
    {
        $buf    = '';
        $endTag = '#' . $endDirective;
        $endLen = strlen($endTag);

        while ($this->pos < $this->len) {
            if (substr($this->src, $this->pos, $endLen) === $endTag) {
                $this->advance($endLen); // consume #endphp
                break;
            }
            $buf .= $this->src[$this->pos];
            $this->advance();
        }

        return $buf;
    }

    /** Scan and emit ATTR_NAME / ATTR_VALUE tokens until `/` or `>` */
    private function scanAttrs(): void
    {
        while ($this->pos < $this->len) {
            $this->skipWhitespace();
            $c = $this->src[$this->pos] ?? '';

            if ($c === '/' || $c === '>') break;

            $attrLine = $this->line;
            $attrCol  = $this->col;

            // Dynamic attribute prefix  :attr="expr"
            $isDynamic = false;
            if ($c === ':') {
                $isDynamic = true;
                $this->advance();
            }

            // Attribute name
            $attrName = '';
            while ($this->pos < $this->len) {
                $c = $this->src[$this->pos];
                if ($c === '=' || $c === '/' || $c === '>' || ctype_space($c)) break;
                $attrName .= $c;
                $this->advance();
            }

            if ($attrName === '') break;

            $prefix = $isDynamic ? ':' : '';
            $this->emit(TokenType::ATTR_NAME, $prefix . $attrName, $attrLine, $attrCol);

            $this->skipWhitespace();

            // Optional value
            if (($this->src[$this->pos] ?? '') === '=') {
                $this->advance(); // =
                $this->skipWhitespace();
                $quote = $this->src[$this->pos] ?? '';
                if ($quote === '"' || $quote === "'") {
                    $this->advance(); // opening quote
                    $valLine = $this->line; $valCol = $this->col;
                    $value   = '';
                    while ($this->pos < $this->len && $this->src[$this->pos] !== $quote) {
                        $value .= $this->src[$this->pos];
                        $this->advance();
                    }
                    if ($this->pos < $this->len) $this->advance(); // closing quote
                    $this->emit(TokenType::ATTR_VALUE, $value, $valLine, $valCol);
                }
            }
        }
    }
}
