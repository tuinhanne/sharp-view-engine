<?php declare(strict_types=1);

namespace Sharp\Support\Cache;

use Sharp\Contract\CacheInterface;

final class FileCache implements CacheInterface
{
    public function __construct(
        private readonly string $compiledDir,
        private readonly string $graphDir,
        private readonly string $astDir,
    ) {
        $this->ensureDir($compiledDir);
        $this->ensureDir($graphDir);
        $this->ensureDir($astDir);
    }

    public function has(string $key, int $sourceModified): bool
    {
        $path = $this->getPath($key);
        if (!file_exists($path)) {
            return false;
        }
        return filemtime($path) >= $sourceModified;
    }

    public function getPath(string $key): string
    {
        return $this->compiledDir . DIRECTORY_SEPARATOR . md5($key) . '.php';
    }

    public function write(string $key, string $phpSource): string
    {
        $final = $this->getPath($key);
        $tmp   = $this->compiledDir . DIRECTORY_SEPARATOR . md5($key) . '.tmp.' . uniqid('', true);

        file_put_contents($tmp, $phpSource);

        // Atomic rename (Windows-safe: unlink first)
        @unlink($final);
        rename($tmp, $final);

        return $final;
    }

    public function invalidate(string $key): void
    {
        @unlink($this->getPath($key));
        @unlink($this->getGraphPath($key));
        @unlink($this->getAstFilePath($key));
    }

    public function writeAst(string $key, array $data): void
    {
        $path = $this->getAstFilePath($key);
        $tmp  = $path . '.tmp.' . uniqid('', true);
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @unlink($path);
        rename($tmp, $path);
    }

    public function getAstFilePath(string $key): string
    {
        return $this->astDir . DIRECTORY_SEPARATOR . md5($key) . '.ast';
    }

    public function writeGraph(string $key, array $record): void
    {
        $path = $this->getGraphPath($key);
        $tmp  = $path . '.tmp.' . uniqid('', true);
        file_put_contents($tmp, json_encode($record, JSON_PRETTY_PRINT));
        @unlink($path);
        rename($tmp, $path);
    }

    public function readGraph(string $key): ?array
    {
        $path = $this->getGraphPath($key);
        if (!file_exists($path)) return null;
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private function getGraphPath(string $key): string
    {
        return $this->graphDir . DIRECTORY_SEPARATOR . md5($key) . '.json';
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
