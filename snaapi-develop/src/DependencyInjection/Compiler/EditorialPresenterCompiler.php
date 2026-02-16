<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Presenter\EditorialPresenterRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class EditorialPresenterCompiler implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $presenters = $container->findTaggedServiceIds('app.editorial.presenter');
        $registryDefinition = $container->findDefinition(EditorialPresenterRegistry::class);

        foreach ($presenters as $idService => $parameters) {
            $definition = $container->getDefinition($idService);
            $registryDefinition->addMethodCall('addPresenter', [$definition]);
        }
    }
}
