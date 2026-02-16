<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

use App\Aggregator\Service\MultimediaResolver;
use Ec\Editorial\Domain\Model\NewsBase;
use Psr\Log\LoggerInterface;

final class MultimediaResolverAdapter implements EditorialResolverInterface
{
    public function __construct(
        private readonly MultimediaResolver $multimediaResolver,
        private readonly LoggerInterface $logger,
    ) {}

    public static function slot(): ResolverSlot
    {
        return ResolverSlot::Multimedia;
    }

    public function priority(): int
    {
        return 0;
    }

    public function resolve(NewsBase $editorial, ResolverContext $context): ResolverResult
    {
        try {
            $result = $this->multimediaResolver->resolve($editorial);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to resolve multimedia', [
                'editorialId' => $editorial->id()->id(),
                'error' => $throwable->getMessage(),
            ]);
            $result = ['multimedia' => null, 'multimediaOpening' => null];
        }

        return new ResolverResult(self::slot(), [
            'multimedia' => $result['multimedia'] ?? null,
            'multimediaOpening' => $result['multimediaOpening'] ?? null,
        ]);
    }
}
