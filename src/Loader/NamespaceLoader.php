<?php declare(strict_types=1);

namespace Sharp\Loader;

use Sharp\Contract\LoaderInterface;
use Sharp\Exception\RenderException;
use Sharp\Sharp;

final class NamespaceLoader implements LoaderInterface
{
    /** @param array<string, string> $namespaces  namespace → absolute base path */
    public function __construct(private readonly array $namespaces = []) {}

    public function supports(string $name): bool
    {
        return str_contains($name, '::');
    }

    public function load(string $name): string
    {
        $path = $this->getFilePath($name);
        if (!file_exists($path)) {
            throw new RenderException("Namespaced view not found: [{$name}] → {$path}");
        }
        return file_get_contents($path);
    }

    public function getKey(string $name): string
    {
        return $name;
    }

    public function getLastModified(string $name): int
    {
        $path = $this->getFilePath($name);
        return file_exists($path) ? (int) filemtime($path) : 0;
    }

    public function getFilePath(string $name): string
    {
        [$ns, $view] = explode('::', $name, 2);

        if (!isset($this->namespaces[$ns])) {
            throw new RenderException("Unknown view namespace: [{$ns}]");
        }

        $relative = str_replace('.', DIRECTORY_SEPARATOR, $view) . Sharp::EXTENSION;
        return rtrim($this->namespaces[$ns], '/\\') . DIRECTORY_SEPARATOR . $relative;
    }
}
