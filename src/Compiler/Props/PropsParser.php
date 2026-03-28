<?php declare(strict_types=1);

namespace Sharp\Compiler\Props;

/**
 * Parses the DSL inside #props([...]) into PropDefinition objects.
 *
 * Input examples:
 *   title: string
 *   description?: string
 *   post: App/Model/Post
 *   count: int,
 */
final class PropsParser
{
    private const PRIMITIVES = ['string', 'int', 'float', 'bool', 'array', 'mixed'];

    /** @return PropDefinition[] */
    public function parse(string $args): array
    {
        $inner = trim($args);

        // Strip outer [ ... ]
        if (str_starts_with($inner, '[')) {
            $inner = substr($inner, 1);
        }
        if (str_ends_with($inner, ']')) {
            $inner = substr($inner, 0, -1);
        }

        $definitions = [];

        foreach ($this->splitEntries($inner) as $entry) {
            $entry = trim($entry);
            if ($entry === '') continue;

            // Match: word[?]: type
            if (!preg_match('/^(\w+)(\?)?\s*:\s*(.+)$/', $entry, $m)) continue;

            $name    = $m[1];
            $nullable = $m[2] === '?';
            $typeRaw = trim($m[3]);
            $isClass = !in_array($typeRaw, self::PRIMITIVES, true);
            $type    = $isClass ? '\\' . str_replace('/', '\\', $typeRaw) : $typeRaw;

            $definitions[] = new PropDefinition($name, $type, $nullable, $isClass);
        }

        return $definitions;
    }

    /** Split on top-level commas (respects nested brackets). */
    private function splitEntries(string $s): array
    {
        $parts = [];
        $buf   = '';
        $depth = 0;
        $len   = strlen($s);

        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            if ($ch === '(' || $ch === '[') { $depth++; $buf .= $ch; continue; }
            if ($ch === ')' || $ch === ']') { $depth--; $buf .= $ch; continue; }
            if ($ch === ',' && $depth === 0) { $parts[] = $buf; $buf = ''; continue; }
            $buf .= $ch;
        }

        if (trim($buf) !== '') $parts[] = $buf;

        return $parts;
    }
}
