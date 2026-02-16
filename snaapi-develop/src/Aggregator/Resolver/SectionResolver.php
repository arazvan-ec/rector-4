<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Section\Domain\Model\QuerySectionClient;
use Psr\Log\LoggerInterface;

final class SectionResolver implements EditorialResolverInterface
{
    public function __construct(
        private readonly QuerySectionClient $querySectionClient,
        private readonly LoggerInterface $logger,
    ) {}

    public static function slot(): ResolverSlot
    {
        return ResolverSlot::Section;
    }

    public function priority(): int
    {
        return 100;
    }

    public function resolve(NewsBase $editorial, ResolverContext $context): ResolverResult
    {
        $section = null;

        try {
            $section = $this->querySectionClient->findSectionById($editorial->sectionId());
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to resolve section', [
                'editorialId' => $editorial->id()->id(),
                'sectionId' => $editorial->sectionId(),
                'error' => $throwable->getMessage(),
            ]);
        }

        $context->set('resolved_section', $section);

        return new ResolverResult(self::slot(), ['section' => $section]);
    }
}
