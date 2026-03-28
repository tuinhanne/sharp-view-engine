<?php declare(strict_types=1);

namespace Sharp\Runtime\Directive;

use Sharp\Exception\CompileException;

final class DirectiveRegistry
{
    /** @var array<string, callable> */
    private array $directives = [];

    /**
     * Register a custom directive.
     *
     * The callable receives the raw argument string(s) and must return a PHP code string.
     * Example:
     *   $registry->register('money', fn($e) => "<?php echo number_format({$e}, 2); ?>");
     */
    public function register(string $name, callable $handler): void
    {
        $this->directives[strtolower($name)] = $handler;
    }

    public function has(string $name): bool
    {
        return isset($this->directives[strtolower($name)]);
    }

    /**
     * Call the directive and return the inlined PHP string.
     * The $args string is the raw content inside the parentheses.
     */
    public function call(string $name, string $args): string
    {
        $name = strtolower($name);

        if (!$this->has($name)) {
            throw new CompileException("Undefined directive: #{$name}");
        }

        // Split args on commas respecting nested parentheses and strings
        $argList = $this->splitArgs($args);

        return ($this->directives[$name])(...$argList);
    }

    /**
     * Split a comma-separated argument list while respecting
     * nested parentheses and quoted strings.
     *
     * @return string[]
     */
    private function splitArgs(string $args): array
    {
        if (trim($args) === '') return [];

        $parts  = [];
        $buf    = '';
        $depth  = 0;
        $inStr  = false;
        $strCh  = '';
        $len    = strlen($args);

        for ($i = 0; $i < $len; $i++) {
            $ch = $args[$i];

            if ($inStr) {
                $buf .= $ch;
                if ($ch === $strCh && ($i === 0 || $args[$i - 1] !== '\\')) {
                    $inStr = false;
                }
                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $inStr  = true;
                $strCh  = $ch;
                $buf   .= $ch;
                continue;
            }

            if ($ch === '(') { $depth++; $buf .= $ch; continue; }
            if ($ch === ')') { $depth--; $buf .= $ch; continue; }

            if ($ch === ',' && $depth === 0) {
                $parts[] = trim($buf);
                $buf     = '';
                continue;
            }

            $buf .= $ch;
        }

        if (trim($buf) !== '') {
            $parts[] = trim($buf);
        }

        return $parts;
    }
}
