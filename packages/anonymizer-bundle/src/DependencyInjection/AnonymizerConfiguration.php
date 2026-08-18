<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\DependencyInjection;

use Override;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class AnonymizerConfiguration implements ConfigurationInterface
{
    #[Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('anonymizer');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('profiles')
                    ->useAttributeAsKey('name')
                    ->prototype('variable')
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
