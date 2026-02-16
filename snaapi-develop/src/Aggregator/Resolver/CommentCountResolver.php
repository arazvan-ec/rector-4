<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use Ec\Editorial\Domain\Model\NewsBase;
use Psr\Log\LoggerInterface;

final class CommentCountResolver implements EditorialResolverInterface
{
    public function __construct(
        private readonly QueryLegacyClient $queryLegacyClient,
        private readonly LoggerInterface $logger,
    ) {}

    public static function slot(): ResolverSlot
    {
        return ResolverSlot::CommentCount;
    }

    public function priority(): int
    {
        return 0;
    }

    public function resolve(NewsBase $editorial, ResolverContext $context): ResolverResult
    {
        $commentCount = 0;

        try {
            /** @var array{options: array{totalrecords?:int}} $comments */
            $comments = $this->queryLegacyClient->findCommentsByEditorialId($editorial->id()->id());
            $commentCount = $comments['options']['totalrecords'] ?? 0;
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to resolve comment count', [
                'editorialId' => $editorial->id()->id(),
                'error' => $throwable->getMessage(),
            ]);
        }

        return new ResolverResult(self::slot(), ['commentCount' => $commentCount]);
    }
}
