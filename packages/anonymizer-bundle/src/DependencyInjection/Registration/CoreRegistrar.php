<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\DependencyInjection\Registration;

use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationLimits;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilderFactory;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Payload\YamlPayloadCodec;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\CompositeProfileProvider;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final class CoreRegistrar
{
    /**
     * @param array{limits: array{max_input_bytes: int, max_depth: int, max_visited_nodes: int, max_collection_entries: int, max_embedded_payload_depth: int}} $config
     */
    public function register(array $config, ContainerBuilder $container): void
    {
        $limits = $config['limits'];
        $container->setDefinition(AnonymizationLimits::class, new Definition(AnonymizationLimits::class, [
            $limits['max_input_bytes'], $limits['max_depth'], $limits['max_visited_nodes'],
            $limits['max_collection_entries'], $limits['max_embedded_payload_depth'],
        ]));
        $container->setDefinition(YamlPayloadCodec::class, new Definition(YamlPayloadCodec::class)
            ->addTag('anonymizer.payload_codec', ['id' => 'yaml', 'builtin' => true]));

        $builder = new Definition(AnonymizerBuilder::class)->setFactory([AnonymizerBuilderFactory::class, 'create'])
            ->setArguments([
                new Reference(CompositeProfileProvider::class),
                new TaggedIteratorArgument('anonymizer.replacement_strategy'),
                new TaggedIteratorArgument('anonymizer.payload_codec'),
                new Reference(AnonymizationLimits::class),
                new TaggedIteratorArgument('anonymizer.value_transformation'),
                null,
                new TaggedIteratorArgument('anonymizer.extension'),
            ]);
        $container->setDefinition(AnonymizerBuilder::class, $builder);
        $container->setDefinition(AnonymizerInterface::class, new Definition(AnonymizerInterface::class)
            ->setFactory([new Reference(AnonymizerBuilder::class), 'build']))->setPublic(true);
    }
}
