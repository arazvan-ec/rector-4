<?php

declare(strict_types=1);

namespace App\Aggregator\DTO;

use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Section\Domain\Model\Section;

final readonly class ResolvedEditorial
{
    /**
     * @param array<string, mixed> $signatures
     * @param array<string, mixed>|null $multimedia
     * @param array<string, mixed>|null $multimediaOpening
     * @param ResolvedInsertedNews[] $insertedNews
     * @param ResolvedRecommendedEditorial[] $recommendedEditorials
     * @param array<string, mixed> $photoFromBodyTags
     * @param array<string, mixed> $membershipLinks
     */
    public function __construct(
        public NewsBase $editorial,
        public ?Section $section,
        public array $tags,
        public array $signatures,
        public ?array $multimedia,
        public ?array $multimediaOpening,
        public array $insertedNews,
        public array $recommendedEditorials,
        public array $photoFromBodyTags,
        public array $membershipLinks,
        public int $commentCount,
    ) {}
}
