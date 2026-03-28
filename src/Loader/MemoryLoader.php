<?php declare(strict_types=1);

namespace Sharp\Loader;

use Sharp\Contract\LoaderInterface;
use Sharp\Exception\RenderException;

final class MemoryLoader implements LoaderInterface
{
    /** @var array<string, array{source: string, mtime: int}> */
    private array $templates = [];

    public function set(string $name, string $source): void
    {
        $this->templates[$name] = ['source' => $source, 'mtime' => time()];
    }

    public function supports(string $name): bool
    {
        return isset($this->templates[$name]);
    }

    public function load(string $name): string
    {
        if (!isset($this->templates[$name])) {
            throw new RenderException("Memory template not found: [{$name}]");
        }
        return $this->templates[$name]['source'];
    }

    public function getKey(string $name): string
    {
        return 'memory::' . $name;
    }

    public function getLastModified(string $name): int
    {
        return $this->templates[$name]['mtime'] ?? 0;
    }
}
