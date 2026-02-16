<?php

declare(strict_types=1);

namespace App\Aggregator;

use App\Aggregator\DTO\ResolvedEditorial;
use App\Aggregator\Service\InsertedNewsResolver;
use App\Aggregator\Service\MultimediaResolver;
use App\Aggregator\Service\RecommendedEditorialsResolver;
use App\Aggregator\Service\SignatureResolver;
use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use App\Exception\EditorialNotPublishedYetException;
use App\Infrastructure\Enum\SitesEnum;
use Ec\Editorial\Domain\Model\Body\Body;
use Ec\Editorial\Domain\Model\Body\BodyTagMembershipCard;
use Ec\Editorial\Domain\Model\Body\BodyTagPicture;
use Ec\Editorial\Domain\Model\Body\MembershipCardButton;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Membership\Infrastructure\Client\Http\QueryMembershipClient;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;
use Ec\Tag\Domain\Model\QueryTagClient;
use Ec\Tag\Domain\Model\Tag;
use Http\Promise\Promise;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;

class EditorialAggregator implements EditorialAggregatorInterface
{
    public function __construct(
        private readonly QueryEditorialClient $queryEditorialClient,
        private readonly QueryLegacyClient $queryLegacyClient,
        private readonly QuerySectionClient $querySectionClient,
        private readonly QueryTagClient $queryTagClient,
        private readonly QueryMultimediaClient $queryMultimediaClient,
        private readonly QueryMembershipClient $queryMembershipClient,
        private readonly UriFactoryInterface $uriFactory,
        private readonly SignatureResolver $signatureResolver,
        private readonly InsertedNewsResolver $insertedNewsResolver,
        private readonly RecommendedEditorialsResolver $recommendedEditorialsResolver,
        private readonly MultimediaResolver $multimediaResolver,
        private readonly LoggerInterface $logger,
    ) {}

    public function aggregate(string $editorialId): ResolvedEditorial
    {
        // CRITICAL: editorial fetch — failure aborts the entire request
        /** @var NewsBase $editorial */
        $editorial = $this->queryEditorialClient->findEditorialById($editorialId);

        if (!$editorial->isVisible()) {
            throw new EditorialNotPublishedYetException();
        }

        // LOW: section — degraded response without section metadata
        $section = $this->resolveSection($editorial);

        // LOW: membership links — requires section for siteId
        $membershipPromise = null;
        $membershipLinksList = [];
        if (null !== $section) {
            try {
                [$membershipPromise, $membershipLinksList] = $this->getPromiseMembershipLinks($editorial, $section->siteId());
            } catch (\Throwable $throwable) {
                $this->logger->warning('Failed to initiate membership links resolution', [
                    'editorialId' => $editorialId,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        // LOW: signatures — delegated to SignatureResolver (per-journalist error handling)
        $signatures = $this->resolveSignatures($editorial, $section);

        // LOW: inserted news — delegated to InsertedNewsResolver (per-item error handling)
        $insertedNews = $this->insertedNewsResolver->resolve($editorial);

        // LOW: recommended editorials — delegated (per-item error handling)
        $recommendedResult = $this->recommendedEditorialsResolver->resolve($editorial);
        $recommendedEditorials = $recommendedResult['resolved'];

        // LOW: multimedia — delegated to MultimediaResolver (internal error handling)
        $multimediaResult = $this->multimediaResolver->resolve($editorial);

        // LOW: photos from body tags — per-photo error handling
        $photoFromBodyTags = $this->retrievePhotosFromBodyTags($editorial->body());

        // LOW: tags — per-tag error handling
        $tags = $this->resolveTags($editorial);

        // LOW: comment count — degraded to 0 on failure
        $commentCount = $this->resolveCommentCount($editorialId);

        // LOW: membership links — resolve async promise
        $membershipLinks = $this->resolvePromiseMembershipLinks($membershipPromise, $membershipLinksList);

        return new ResolvedEditorial(
            editorial: $editorial,
            section: $section,
            tags: $tags,
            signatures: $signatures,
            multimedia: $multimediaResult['multimedia'],
            multimediaOpening: $multimediaResult['multimediaOpening'],
            insertedNews: $insertedNews,
            recommendedEditorials: $recommendedEditorials,
            photoFromBodyTags: $photoFromBodyTags,
            membershipLinks: $membershipLinks,
            commentCount: $commentCount,
        );
    }

    private function resolveSection(NewsBase $editorial): ?Section
    {
        try {
            return $this->querySectionClient->findSectionById($editorial->sectionId());
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to resolve section', [
                'editorialId' => $editorial->id()->id(),
                'sectionId' => $editorial->sectionId(),
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveSignatures(NewsBase $editorial, ?Section $section): array
    {
        if (null === $section) {
            return [];
        }

        try {
            $hasTwitter = \in_array($editorial->editorialType(), [\Ec\Editorial\Domain\Model\EditorialBlog::EDITORIAL_TYPE]);

            return $this->signatureResolver->resolve($editorial, $section, $hasTwitter);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to resolve signatures', [
                'editorialId' => $editorial->id()->id(),
                'error' => $throwable->getMessage(),
            ]);

            return [];
        }
    }

    private function resolveCommentCount(string $editorialId): int
    {
        try {
            /** @var array{options: array{totalrecords?:int}} $comments */
            $comments = $this->queryLegacyClient->findCommentsByEditorialId($editorialId);

            return $comments['options']['totalrecords'] ?? 0;
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to resolve comment count', [
                'editorialId' => $editorialId,
                'error' => $throwable->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * @return Tag[]
     */
    private function resolveTags(NewsBase $editorial): array
    {
        $tags = [];
        foreach ($editorial->tags()->getArrayCopy() as $tag) {
            try {
                $tags[] = $this->queryTagClient->findTagById($tag->id());
            } catch (\Throwable $exception) {
                $this->logger->warning('Failed to resolve tag', [
                    'tagId' => $tag->id(),
                    'error' => $exception->getMessage(),
                ]);
                continue;
            }
        }

        return $tags;
    }

    /**
     * @return array<string, mixed>
     */
    private function retrievePhotosFromBodyTags(Body $body): array
    {
        $result = [];

        /** @var BodyTagPicture[] $pictures */
        $pictures = $body->bodyElementsOf(BodyTagPicture::class);
        foreach ($pictures as $bodyTagPicture) {
            $id = $bodyTagPicture->id()->id();
            try {
                $photo = $this->queryMultimediaClient->findPhotoById($id);
                $result[$id] = $photo;
            } catch (\Throwable $throwable) {
                $this->logger->warning('Failed to resolve body tag photo', [
                    'photoId' => $id,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        /** @var BodyTagMembershipCard[] $membershipCards */
        $membershipCards = $body->bodyElementsOf(BodyTagMembershipCard::class);
        foreach ($membershipCards as $bodyTagMembershipCard) {
            $id = $bodyTagMembershipCard->bodyTagPictureMembership()->id()->id();
            try {
                $photo = $this->queryMultimediaClient->findPhotoById($id);
                $result[$id] = $photo;
            } catch (\Throwable $throwable) {
                $this->logger->warning('Failed to resolve membership card photo', [
                    'photoId' => $id,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * @return array{0: Promise|null, 1: array<int, string>}
     */
    private function getPromiseMembershipLinks(Editorial $editorial, string $siteId): array
    {
        $linksData = $this->getLinksFromBody($editorial->body());

        $links = [];
        $uris = [];
        foreach ($linksData as $membershipLink) {
            $uris[] = $this->uriFactory->createUri($membershipLink);
            $links[] = $membershipLink;
        }

        /** @var Promise $promise */
        $promise = $this->queryMembershipClient->getMembershipUrl(
            $editorial->id()->id(),
            $uris,
            SitesEnum::getEncodenameById($siteId),
            true
        );

        return [$promise, $links];
    }

    /**
     * @return array<int, string>
     */
    private function getLinksFromBody(Body $body): array
    {
        $linksData = [];

        $bodyElementsMembership = $body->bodyElementsOf(BodyTagMembershipCard::class);
        /** @var BodyTagMembershipCard $bodyElement */
        foreach ($bodyElementsMembership as $bodyElement) {
            /** @var MembershipCardButton $button */
            foreach ($bodyElement->buttons()->buttons() as $button) {
                $linksData[] = $button->urlMembership();
                $linksData[] = $button->url();
            }
        }

        return $linksData;
    }

    /**
     * @param array<int, string> $links
     *
     * @return array<string, mixed>
     */
    private function resolvePromiseMembershipLinks(?Promise $promise, array $links): array
    {
        $membershipLinkResult = [];
        if ($promise) {
            try {
                /** @var array<string, mixed> $membershipLinkResult */
                $membershipLinkResult = $promise->wait();
            } catch (\Throwable $throwable) {
                $this->logger->warning('Failed to resolve membership links', [
                    'error' => $throwable->getMessage(),
                ]);

                return [];
            }
        }

        if (empty($membershipLinkResult)) {
            return [];
        }

        return array_combine($links, $membershipLinkResult);
    }
}
