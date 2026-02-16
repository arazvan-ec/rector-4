<?php

declare(strict_types=1);

namespace App\Aggregator\DTO;

use App\Aggregator\Resolver\ResolverResult;
use App\Aggregator\Resolver\ResolverSlot;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\Tag;

final class ResolvedEditorialBuilder
{
    private ?Section $section = null;

    /** @var Tag[] */
    private array $tags = [];

    /** @var ResolvedSignature[] */
    private array $signatures = [];

    /** @var array<string, mixed>|null */
    private ?array $multimedia = null;

    /** @var array<string, mixed>|null */
    private ?array $multimediaOpening = null;

    /** @var ResolvedInsertedNews[] */
    private array $insertedNews = [];

    /** @var ResolvedRecommendedEditorial[] */
    private array $recommendedEditorials = [];

    /** @var array<string, mixed> */
    private array $photoFromBodyTags = [];

    /** @var array<string, mixed> */
    private array $membershipLinks = [];

    private int $commentCount = 0;

    public function __construct(
        private readonly NewsBase $editorial,
    ) {}

    public function applyResult(ResolverResult $result): void
    {
        match ($result->slot) {
            ResolverSlot::Section => $this->section = $result->data['section'] ?? null,
            ResolverSlot::Tags => $this->tags = $result->data['tags'] ?? [],
            ResolverSlot::Signatures => $this->signatures = $result->data['signatures'] ?? [],
            ResolverSlot::Multimedia => $this->applyMultimediaResult($result->data),
            ResolverSlot::InsertedNews => $this->insertedNews = $result->data['insertedNews'] ?? [],
            ResolverSlot::RecommendedEditorials => $this->recommendedEditorials = $result->data['recommendedEditorials'] ?? [],
            ResolverSlot::PhotoFromBodyTags => $this->photoFromBodyTags = $result->data['photoFromBodyTags'] ?? [],
            ResolverSlot::MembershipLinks => $this->membershipLinks = $result->data['membershipLinks'] ?? [],
            ResolverSlot::CommentCount => $this->commentCount = $result->data['commentCount'] ?? 0,
        };
    }

    public function build(): ResolvedEditorial
    {
        return new ResolvedEditorial(
            editorial: $this->editorial,
            section: $this->section,
            tags: $this->tags,
            signatures: $this->signatures,
            multimedia: $this->multimedia,
            multimediaOpening: $this->multimediaOpening,
            insertedNews: $this->insertedNews,
            recommendedEditorials: $this->recommendedEditorials,
            photoFromBodyTags: $this->photoFromBodyTags,
            membershipLinks: $this->membershipLinks,
            commentCount: $this->commentCount,
        );
    }

    public function getSection(): ?Section
    {
        return $this->section;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyMultimediaResult(array $data): void
    {
        $this->multimedia = $data['multimedia'] ?? null;
        $this->multimediaOpening = $data['multimediaOpening'] ?? null;
    }
}
