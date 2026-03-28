<?php declare(strict_types=1);

namespace Sharp\Contract;

interface CacheInterface
{
    public function has(string $key, int $sourceModified): bool;

    public function getPath(string $key): string;

    /** Returns the path to the written file. */
    public function write(string $key, string $phpSource): string;

    public function invalidate(string $key): void;
}
