<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

use Ec\Editorial\Domain\Model\Body\BodyTagMembershipCard;
use Ec\Editorial\Domain\Model\Body\BodyTagPicture;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use Psr\Log\LoggerInterface;

final class BodyTagPhotoResolver implements EditorialResolverInterface
{
    public function __construct(
        private readonly QueryMultimediaClient $queryMultimediaClient,
        private readonly LoggerInterface $logger,
    ) {}

    public static function slot(): ResolverSlot
    {
        return ResolverSlot::PhotoFromBodyTags;
    }

    public function priority(): int
    {
        return 0;
    }

    public function resolve(NewsBase $editorial, ResolverContext $context): ResolverResult
    {
        $result = [];

        /** @var BodyTagPicture[] $pictures */
        $pictures = $editorial->body()->bodyElementsOf(BodyTagPicture::class);
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
        $membershipCards = $editorial->body()->bodyElementsOf(BodyTagMembershipCard::class);
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

        return new ResolverResult(self::slot(), ['photoFromBodyTags' => $result]);
    }
}
