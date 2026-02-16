<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Aggregator\EditorialAggregator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class EditorialResolverCompiler implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $resolvers = $container->findTaggedServiceIds('app.aggregator.resolver');
        $aggregatorDefinition = $container->findDefinition(EditorialAggregator::class);

        foreach ($resolvers as $idService => $parameters) {
            $definition = $container->getDefinition($idService);
            $aggregatorDefinition->addMethodCall('addResolver', [$definition]);
        }
    }
}
