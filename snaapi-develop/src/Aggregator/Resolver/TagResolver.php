<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Tag\Domain\Model\QueryTagClient;
use Psr\Log\LoggerInterface;

final class TagResolver implements EditorialResolverInterface
{
    public function __construct(
        private readonly QueryTagClient $queryTagClient,
        private readonly LoggerInterface $logger,
    ) {}

    public static function slot(): ResolverSlot
    {
        return ResolverSlot::Tags;
    }

    public function priority(): int
    {
        return 0;
    }

    public function resolve(NewsBase $editorial, ResolverContext $context): ResolverResult
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
            }
        }

        return new ResolverResult(self::slot(), ['tags' => $tags]);
    }
}
