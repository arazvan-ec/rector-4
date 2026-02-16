<?php

declare(strict_types=1);

namespace App\Presenter\Apps;

use App\Aggregator\DTO\ResolvedEditorial;
use App\Aggregator\DTO\ResolvedSignature;
use App\Application\DataTransformer\Apps\AppsDataTransformer;
use App\Application\DataTransformer\Apps\JournalistsDataTransformer;
use App\Application\DataTransformer\Apps\Media\MediaDataTransformerHandler;
use App\Application\DataTransformer\Apps\MultimediaDataTransformer;
use App\Application\DataTransformer\Apps\RecommendedEditorialsDataTransformer;
use App\Application\DataTransformer\Apps\StandfirstDataTransformer;
use App\Application\DataTransformer\BodyDataTransformer;
use App\Presenter\EditorialPresenterInterface;
use Ec\Editorial\Domain\Model\EditorialBlog;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Editorial\Exceptions\MultimediaDataTransformerNotFoundException;
use Ec\Section\Domain\Model\Section;

class AppsEditorialPresenter implements EditorialPresenterInterface
{
    public function __construct(
        private readonly AppsDataTransformer $detailsAppsDataTransformer,
        private readonly BodyDataTransformer $bodyDataTransformer,
        private readonly StandfirstDataTransformer $standFirstDataTransformer,
        private readonly RecommendedEditorialsDataTransformer $recommendedEditorialsDataTransformer,
        private readonly MultimediaDataTransformer $multimediaDataTransformer,
        private readonly MediaDataTransformerHandler $mediaDataTransformerHandler,
        private readonly JournalistsDataTransformer $journalistsDataTransformer,
    ) {}

    public function supports(string $format): bool
    {
        return 'apps' === $format;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ResolvedEditorial $resolved): array
    {
        $editorial = $resolved->editorial;
        $section = $resolved->section;

        // 1. Details transformation (same as current)
        $editorialResult = $this->detailsAppsDataTransformer->write(
            $editorial,
            $section,
            $resolved->tags
        )->read();

        // 2. Comment count (resolved by aggregator)
        $editorialResult['countComments'] = $resolved->commentCount;

        // 3. Signatures (format raw ResolvedSignature DTOs into apps format)
        $hasTwitter = \in_array($editorial->editorialType(), [EditorialBlog::EDITORIAL_TYPE]);
        $editorialResult['signatures'] = $this->formatSignatures($resolved->signatures, $section, $hasTwitter);

        // 4. Body transformation - needs $resolveData in legacy format
        $resolveData = $this->buildResolveData($resolved);
        $editorialResult['body'] = $this->bodyDataTransformer->execute(
            $editorial->body(),
            $resolveData
        );

        // 5. Multimedia transformation
        $editorialResult['multimedia'] = $this->transformMultimedia($editorial, $resolveData);

        // 6. Standfirst
        $editorialResult['standfirst'] = $this->standFirstDataTransformer
            ->write($editorial->standFirst())
            ->read();

        // 7. Recommended editorials
        $recommendedNews = array_map(
            fn ($resolved) => $resolved->editorial,
            $resolved->recommendedEditorials
        );
        $editorialResult['recommendedEditorials'] = $this->recommendedEditorialsDataTransformer
            ->write($recommendedNews, $resolveData)
            ->read();

        return $editorialResult;
    }

    /**
     * Build the legacy $resolveData array from the typed DTO.
     * This maintains backward compatibility with BodyDataTransformer and
     * RecommendedEditorialsDataTransformer which expect this format.
     *
     * @return array<string, mixed>
     */
    private function buildResolveData(ResolvedEditorial $resolved): array
    {
        $resolveData = [];

        // Multimedia
        $resolveData['multimedia'] = $resolved->multimedia ?? [];
        $resolveData['multimediaOpening'] = $resolved->multimediaOpening ?? [];

        // Inserted news in legacy format
        $resolveData['insertedNews'] = [];
        foreach ($resolved->insertedNews as $id => $insertedNews) {
            $resolveData['insertedNews'][$id] = [
                'editorial' => $insertedNews->editorial,
                'section' => $insertedNews->section,
                'signatures' => $this->formatSignatures($insertedNews->signatures, $insertedNews->section),
                'multimediaId' => $insertedNews->multimediaId,
            ];
        }

        // Recommended editorials in legacy format
        $resolveData['recommendedEditorials'] = [];
        foreach ($resolved->recommendedEditorials as $id => $recommended) {
            $resolveData['recommendedEditorials'][$id] = [
                'editorial' => $recommended->editorial,
                'section' => $recommended->section,
                'signatures' => $this->formatSignatures($recommended->signatures, $recommended->section),
                'multimediaId' => $recommended->multimediaId,
            ];
        }

        // Photos from body tags
        $resolveData['photoFromBodyTags'] = $resolved->photoFromBodyTags;

        // Membership links
        $resolveData['membershipLinkCombine'] = $resolved->membershipLinks;

        return $resolveData;
    }

    /**
     * Format raw ResolvedSignature DTOs into the apps-specific array format.
     *
     * @param ResolvedSignature[] $signatures
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatSignatures(array $signatures, ?Section $section, bool $hasTwitter = false): array
    {
        if (null === $section) {
            return [];
        }

        $formatted = [];
        foreach ($signatures as $signature) {
            $result = $this->journalistsDataTransformer
                ->write($signature->aliasId, $signature->journalist, $section, $hasTwitter)
                ->read();
            if (!empty($result)) {
                $formatted[] = $result;
            }
        }

        return $formatted;
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $resolveData
     *
     * @return array<string, mixed>|null
     *
     * @throws MultimediaDataTransformerNotFoundException
     */
    private function transformMultimedia(NewsBase $editorial, array $resolveData): ?array
    {
        if (!empty($resolveData['multimediaOpening'])) {
            return $this->mediaDataTransformerHandler->execute(
                $resolveData['multimediaOpening'],
                $editorial->opening()
            );
        }

        if (!empty($resolveData['multimedia'])) {
            return $this->multimediaDataTransformer
                ->write($resolveData['multimedia'], $editorial->multimedia())
                ->read();
        }

        return null;
    }
}
