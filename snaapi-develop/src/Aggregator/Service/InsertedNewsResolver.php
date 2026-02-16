<?php

declare(strict_types=1);

namespace App\Aggregator\Service;

use App\Aggregator\DTO\ResolvedInsertedNews;
use Ec\Editorial\Domain\Model\Body\BodyTagInsertedNews;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Ec\Section\Domain\Model\QuerySectionClient;
use Ec\Section\Domain\Model\Section;
use Psr\Log\LoggerInterface;

class InsertedNewsResolver
{
    public function __construct(
        private readonly QueryEditorialClient $queryEditorialClient,
        private readonly QuerySectionClient $querySectionClient,
        private readonly SignatureResolver $signatureResolver,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Resolve inserted news from editorial body.
     *
     * @return array<string, ResolvedInsertedNews>
     */
    public function resolve(NewsBase $editorial): array
    {
        $result = [];

        /** @var BodyTagInsertedNews[] $insertedNews */
        $insertedNews = $editorial->body()->bodyElementsOf(BodyTagInsertedNews::class);

        foreach ($insertedNews as $insertedNew) {
            $idInserted = $insertedNew->editorialId()->id();

            try {
                /** @var Editorial $insertedEditorial */
                $insertedEditorial = $this->queryEditorialClient->findEditorialById($idInserted);

                if (!$insertedEditorial->isVisible()) {
                    continue;
                }

                /** @var Section $section */
                $section = $this->querySectionClient->findSectionById($insertedEditorial->sectionId());

                $signatures = $this->signatureResolver->resolve($insertedEditorial, $section);

                $multimediaId = !empty($insertedEditorial->multimedia()->id()->id())
                    ? $insertedEditorial->multimedia()->id()->id()
                    : $insertedEditorial->metaImage();

                $result[$idInserted] = new ResolvedInsertedNews(
                    editorial: $insertedEditorial,
                    section: $section,
                    signatures: $signatures,
                    multimediaId: $multimediaId,
                );
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to resolve inserted news', [
                    'editorialId' => $idInserted,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $result;
    }
}
