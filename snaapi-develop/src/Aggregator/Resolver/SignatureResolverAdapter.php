<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

use App\Aggregator\Service\SignatureResolver;
use Ec\Editorial\Domain\Model\NewsBase;
use Psr\Log\LoggerInterface;

final class SignatureResolverAdapter implements EditorialResolverInterface
{
    public function __construct(
        private readonly SignatureResolver $signatureResolver,
        private readonly LoggerInterface $logger,
    ) {}

    public static function slot(): ResolverSlot
    {
        return ResolverSlot::Signatures;
    }

    public function priority(): int
    {
        return 0;
    }

    public function resolve(NewsBase $editorial, ResolverContext $context): ResolverResult
    {
        try {
            $signatures = $this->signatureResolver->resolve($editorial);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to resolve signatures', [
                'editorialId' => $editorial->id()->id(),
                'error' => $throwable->getMessage(),
            ]);
            $signatures = [];
        }

        return new ResolverResult(self::slot(), ['signatures' => $signatures]);
    }
}
