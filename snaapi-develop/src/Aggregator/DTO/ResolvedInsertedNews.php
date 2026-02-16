<?php

declare(strict_types=1);

namespace App\Aggregator\DTO;

use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Section\Domain\Model\Section;

final readonly class ResolvedInsertedNews
{
    /**
     * @param array<string, mixed> $signatures
     */
    public function __construct(
        public NewsBase $editorial,
        public ?Section $section,
        public array $signatures,
        public ?string $multimediaId,
    ) {}
}
