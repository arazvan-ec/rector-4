<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

use App\Aggregator\Service\InsertedNewsResolver;
use Ec\Editorial\Domain\Model\NewsBase;
use Psr\Log\LoggerInterface;

final class InsertedNewsResolverAdapter implements EditorialResolverInterface
{
    public function __construct(
        private readonly InsertedNewsResolver $insertedNewsResolver,
        private readonly LoggerInterface $logger,
    ) {}

    public static function slot(): ResolverSlot
    {
        return ResolverSlot::InsertedNews;
    }

    public function priority(): int
    {
        return 0;
    }

    public function resolve(NewsBase $editorial, ResolverContext $context): ResolverResult
    {
        try {
            $insertedNews = $this->insertedNewsResolver->resolve($editorial);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to resolve inserted news', [
                'editorialId' => $editorial->id()->id(),
                'error' => $throwable->getMessage(),
            ]);
            $insertedNews = [];
        }

        return new ResolverResult(self::slot(), ['insertedNews' => $insertedNews]);
    }
}
