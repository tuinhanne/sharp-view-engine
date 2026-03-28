<?php declare(strict_types=1);

namespace Sharp\Contract;

interface LoaderInterface
{
    public function supports(string $name): bool;

    public function load(string $name): string;

    public function getKey(string $name): string;

    public function getLastModified(string $name): int;
}
