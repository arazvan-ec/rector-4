<?php

declare(strict_types=1);

namespace App\Presenter;

use App\Aggregator\DTO\ResolvedEditorial;

interface EditorialPresenterInterface
{
    /**
     * @return array<string, mixed>
     */
    public function present(ResolvedEditorial $resolved): array;

    public function supports(string $format): bool;
}
