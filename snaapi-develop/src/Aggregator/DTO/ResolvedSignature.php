<?php

declare(strict_types=1);

namespace App\Aggregator\DTO;

use Ec\Journalist\Domain\Model\Journalist;

final readonly class ResolvedSignature
{
    public function __construct(
        public string $aliasId,
        public Journalist $journalist,
    ) {}
}
