<?php

declare(strict_types=1);

namespace App\Aggregator;

use App\Aggregator\DTO\ResolvedEditorial;
use App\Aggregator\DTO\ResolvedEditorialBuilder;
use App\Aggregator\Resolver\EditorialResolverInterface;
use App\Aggregator\Resolver\ResolverContext;
use App\Exception\EditorialNotPublishedYetException;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Editorial\Domain\Model\QueryEditorialClient;

class EditorialAggregator implements EditorialAggregatorInterface
{
    /** @var EditorialResolverInterface[] */
    private array $resolvers = [];

    public function __construct(
        private readonly QueryEditorialClient $queryEditorialClient,
    ) {}

    public function addResolver(EditorialResolverInterface $resolver): void
    {
        $this->resolvers[] = $resolver;
        usort($this->resolvers, static fn (
            EditorialResolverInterface $a,
            EditorialResolverInterface $b,
        ) => $b->priority() <=> $a->priority());
    }

    public function aggregate(string $editorialId): ResolvedEditorial
    {
        /** @var NewsBase $editorial */
        $editorial = $this->queryEditorialClient->findEditorialById($editorialId);

        if (!$editorial->isVisible()) {
            throw new EditorialNotPublishedYetException();
        }

        $builder = new ResolvedEditorialBuilder($editorial);
        $context = new ResolverContext();

        foreach ($this->resolvers as $resolver) {
            $result = $resolver->resolve($editorial, $context);
            $builder->applyResult($result);
        }

        return $builder->build();
    }
}
