<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

final readonly class ResolverResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public ResolverSlot $slot,
        public array $data,
    ) {}
}
