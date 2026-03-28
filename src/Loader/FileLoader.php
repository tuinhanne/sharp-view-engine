<?php declare(strict_types=1);

namespace Sharp\Loader;

use Sharp\Contract\LoaderInterface;
use Sharp\Exception\RenderException;
use Sharp\Sharp;

final class FileLoader implements LoaderInterface
{
    public function __construct(private readonly string $viewPath) {}

    public function supports(string $name): bool
    {
        return !str_contains($name, '::');
    }

    public function load(string $name): string
    {
        $path = $this->getFilePath($name);
        if (!file_exists($path)) {
            throw new RenderException("View not found: [{$name}] → {$path}");
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
        $relative = str_replace('.', DIRECTORY_SEPARATOR, $name) . Sharp::EXTENSION;
        return $this->viewPath . DIRECTORY_SEPARATOR . $relative;
    }
}
