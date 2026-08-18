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
                ->arrayNode('faker')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('locale')->defaultValue('en_US')->cannotBeEmpty()->end()
                        ->scalarNode('seed')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('personas')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('locale')->defaultValue('en')->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
