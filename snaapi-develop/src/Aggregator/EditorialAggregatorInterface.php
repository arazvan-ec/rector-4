<?php

declare(strict_types=1);

namespace App\Aggregator;

use App\Aggregator\DTO\ResolvedEditorial;

interface EditorialAggregatorInterface
{
    public function aggregate(string $editorialId): ResolvedEditorial;
}
