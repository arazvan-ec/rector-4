<?php

declare(strict_types=1);

namespace App\Orchestrator\Chain;

use App\Aggregator\EditorialAggregatorInterface;
use App\Ec\Snaapi\Infrastructure\Client\Http\QueryLegacyClient;
use App\Presenter\EditorialPresenterRegistry;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Editorial\Domain\Model\QueryEditorialClient;
use Symfony\Component\HttpFoundation\Request;

class EditorialOrchestratorRefactored implements EditorialOrchestratorInterface
{
    public function __construct(
        private readonly QueryEditorialClient $queryEditorialClient,
        private readonly QueryLegacyClient $queryLegacyClient,
        private readonly EditorialAggregatorInterface $aggregator,
        private readonly EditorialPresenterRegistry $presenterRegistry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Request $request): array
    {
        /** @var string $id */
        $id = $request->get('id');

        /** @var NewsBase $editorial */
        $editorial = $this->queryEditorialClient->findEditorialById($id);

        // BR-EDIT-002: Legacy fallback - short-circuits entire pipeline
        if (null === $editorial->sourceEditorial()) {
            return $this->queryLegacyClient->findEditorialById($id);
        }

        $resolved = $this->aggregator->aggregate($id);

        return $this->presenterRegistry->present($resolved, 'apps');
    }

    public function canOrchestrate(): string
    {
        return 'editorial';
    }
}
