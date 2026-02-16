<?php

declare(strict_types=1);

namespace App\Aggregator\Service;

use App\Aggregator\DTO\ResolvedSignature;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Journalist\Domain\Model\JournalistFactory;
use Ec\Journalist\Domain\Model\QueryJournalistClient;
use Psr\Log\LoggerInterface;

class SignatureResolver
{
    public function __construct(
        private readonly QueryJournalistClient $queryJournalistClient,
        private readonly JournalistFactory $journalistFactory,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Resolve journalist signatures for an editorial.
     *
     * @return ResolvedSignature[]
     */
    public function resolve(NewsBase $editorial): array
    {
        $signatures = [];

        foreach ($editorial->signatures()->getArrayCopy() as $signature) {
            $result = $this->resolveAlias($signature->id()->id());
            if ($result !== null) {
                $signatures[] = $result;
            }
        }

        return $signatures;
    }

    private function resolveAlias(string $aliasId): ?ResolvedSignature
    {
        try {
            $aliasIdModel = $this->journalistFactory->buildAliasId($aliasId);
            $journalist = $this->queryJournalistClient->findJournalistByAliasId($aliasIdModel);

            return new ResolvedSignature(aliasId: $aliasId, journalist: $journalist);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to resolve journalist alias', [
                'aliasId' => $aliasId,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }
}
