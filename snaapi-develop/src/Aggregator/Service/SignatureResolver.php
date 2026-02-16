<?php

declare(strict_types=1);

namespace App\Aggregator\Service;

use App\Application\DataTransformer\Apps\JournalistsDataTransformer;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Editorial\Domain\Model\Signature;
use Ec\Journalist\Domain\Model\Journalist;
use Ec\Journalist\Domain\Model\JournalistFactory;
use Ec\Journalist\Domain\Model\QueryJournalistClient;
use Ec\Section\Domain\Model\Section;
use Psr\Log\LoggerInterface;

class SignatureResolver
{
    public function __construct(
        private readonly QueryJournalistClient $queryJournalistClient,
        private readonly JournalistFactory $journalistFactory,
        private readonly JournalistsDataTransformer $journalistsDataTransformer,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Resolve journalist signatures for an editorial.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolve(NewsBase $editorial, Section $section, bool $hasTwitter = false): array
    {
        $signatures = [];

        /** @var Signature $signature */
        foreach ($editorial->signatures()->getArrayCopy() as $signature) {
            $result = $this->resolveAlias($signature->id()->id(), $section, $hasTwitter);
            if (!empty($result)) {
                $signatures[] = $result;
            }
        }

        return $signatures;
    }

    /**
     * Resolve a single journalist alias to its formatted representation.
     *
     * @return array<string, mixed>
     */
    private function resolveAlias(string $aliasId, Section $section, bool $hasTwitter = false): array
    {
        $signature = [];
        $aliasIdModel = $this->journalistFactory->buildAliasId($aliasId);

        try {
            /** @var Journalist $journalist */
            $journalist = $this->queryJournalistClient->findJournalistByAliasId($aliasIdModel);
            $signature = $this->journalistsDataTransformer->write($aliasId, $journalist, $section, $hasTwitter)->read();
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to resolve journalist alias', [
                'aliasId' => $aliasId,
                'error' => $throwable->getMessage(),
            ]);
        }

        return $signature;
    }
}
