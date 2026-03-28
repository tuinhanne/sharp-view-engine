<?php declare(strict_types=1);

namespace Sharp\Support\Cache;

use Sharp\Contract\CacheInterface;

final class NullCache implements CacheInterface
{
    public function has(string $key, int $sourceModified): bool  { return false; }
    public function getPath(string $key): string                  { return ''; }
    public function write(string $key, string $phpSource): string { return ''; }
    public function invalidate(string $key): void                 {}
}
