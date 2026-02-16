<?php

declare(strict_types=1);

namespace App\Aggregator\Service;

use App\Aggregator\DTO\ResolvedRecommendedEditorial;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\EditorialId;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;
use Psr\Log\LoggerInterface;

class RecommendedEditorialsResolver
{
    public function __construct(
        private readonly QueryEditorialClient $queryEditorialClient,
        private readonly QuerySectionClient $querySectionClient,
        private readonly SignatureResolver $signatureResolver,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Resolve recommended editorials.
     *
     * @return array{resolved: array<string, ResolvedRecommendedEditorial>, editorials: array<int, NewsBase>}
     */
    public function resolve(NewsBase $editorial): array
    {
        $resolved = [];
        $editorials = [];

        $recommendedEditorials = $editorial->recommendedEditorials();

        /** @var EditorialId $recommendedEditorialId */
        foreach ($recommendedEditorials->editorialIds() as $recommendedEditorialId) {
            try {
                $idRecommended = $recommendedEditorialId->id();

                /** @var Editorial $recommendedEditorial */
                $recommendedEditorial = $this->queryEditorialClient->findEditorialById($idRecommended);

                if (!$recommendedEditorial->isVisible()) {
                    continue;
                }

                /** @var Section $section */
                $section = $this->querySectionClient->findSectionById($recommendedEditorial->sectionId());

                $signatures = $this->signatureResolver->resolve($recommendedEditorial, $section);

                $multimediaId = !empty($recommendedEditorial->multimedia()->id()->id())
                    ? $recommendedEditorial->multimedia()->id()->id()
                    : $recommendedEditorial->metaImage();

                $resolved[$idRecommended] = new ResolvedRecommendedEditorial(
                    editorial: $recommendedEditorial,
                    section: $section,
                    signatures: $signatures,
                    multimediaId: $multimediaId,
                );

                $editorials[] = $recommendedEditorial;
            } catch (\Throwable $throwable) {
                $this->logger->warning('Failed to resolve recommended editorial', [
                    'editorialId' => $recommendedEditorialId->id(),
                    'error' => $throwable->getMessage(),
                ]);
                continue;
            }
        }

        return [
            'resolved' => $resolved,
            'editorials' => $editorials,
        ];
    }
}
