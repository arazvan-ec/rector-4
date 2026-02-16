<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

final class ResolverContext
{
    /** @var array<string, mixed> */
    private array $state = [];

    public function set(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->state);
    }
}
