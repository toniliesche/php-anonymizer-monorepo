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
            ->arrayNode('limits')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('max_input_bytes')->defaultValue(10_485_760)->min(1)->end()
            ->integerNode('max_depth')->defaultValue(64)->min(1)->end()
            ->integerNode('max_visited_nodes')->defaultValue(100_000)->min(1)->end()
            ->integerNode('max_collection_entries')->defaultValue(50_000)->min(1)->end()
            ->integerNode('max_embedded_payload_depth')->defaultValue(8)->min(1)->end()
            ->end()
            ->end()
            ->arrayNode('audit')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')->defaultFalse()->end()
            ->scalarNode('logger_service')->defaultNull()->end()
            ->end()
            ->end()
            ->arrayNode('pseudonymization')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')->defaultFalse()->end()
            ->scalarNode('key_provider')->defaultNull()->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
