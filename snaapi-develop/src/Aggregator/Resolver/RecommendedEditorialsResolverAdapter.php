<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

use App\Aggregator\Service\RecommendedEditorialsResolver;
use Ec\Editorial\Domain\Model\NewsBase;
use Psr\Log\LoggerInterface;

final class RecommendedEditorialsResolverAdapter implements EditorialResolverInterface
{
    public function __construct(
        private readonly RecommendedEditorialsResolver $recommendedEditorialsResolver,
        private readonly LoggerInterface $logger,
    ) {}

    public static function slot(): ResolverSlot
    {
        return ResolverSlot::RecommendedEditorials;
    }

    public function priority(): int
    {
        return 0;
    }

    public function resolve(NewsBase $editorial, ResolverContext $context): ResolverResult
    {
        try {
            $result = $this->recommendedEditorialsResolver->resolve($editorial);
            $recommendedEditorials = $result['resolved'];
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to resolve recommended editorials', [
                'editorialId' => $editorial->id()->id(),
                'error' => $throwable->getMessage(),
            ]);
            $recommendedEditorials = [];
        }

        return new ResolverResult(self::slot(), ['recommendedEditorials' => $recommendedEditorials]);
    }
}
