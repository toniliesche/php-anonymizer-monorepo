<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\DependencyInjection;

use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ArrayAnonymizer;
use PhpAnonymizer\Anonymizer\Domain\Exception\InvalidProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleCollectionConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleMerger;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleNodeConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRulePathConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleValueConverter;
use PhpAnonymizer\AnonymizerBundle\Command\ImportLegacyRulesCommand;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\AtomicFileWriter;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\BundleConfigRenderer;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\LegacyDocumentReader;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Payload\YamlPayloadCodec;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\CompositeProfileProvider;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\ConfiguredProfileProvider;
use PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement\FakerReplacementStrategyFactory;
use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaReplacementStrategyFactory;
use PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyAnonymizer;
use PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyObjectAnonymizer;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class AnonymizerExtension extends Extension
{
    private const array PERSONA_IDS = ['persona.name', 'persona.first_name', 'persona.last_name', 'persona.email', 'persona.username', 'persona.company', 'persona.phone', 'persona.street', 'persona.street_number', 'persona.city', 'persona.postcode', 'persona.country'];
    private const array FAKER_IDS = ['faker.building_number', 'faker.city', 'faker.company', 'faker.country', 'faker.email', 'faker.first_name', 'faker.house_number', 'faker.last_name', 'faker.name', 'faker.password', 'faker.postcode', 'faker.street', 'faker.street_number', 'faker.username', 'faker.zip'];
    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->setDefinition(LegacyRuleMerger::class, new Definition(LegacyRuleMerger::class));
        $container->setDefinition(LegacyRuleValueConverter::class, new Definition(LegacyRuleValueConverter::class));
        $container->setDefinition(LegacyRulePathConverter::class, new Definition(LegacyRulePathConverter::class, [new Reference(LegacyRuleValueConverter::class)]));
        $container->setDefinition(LegacyRuleNodeConverter::class, new Definition(LegacyRuleNodeConverter::class, [new Reference(LegacyRuleValueConverter::class), new Reference(LegacyRulePathConverter::class)]));
        $container->setDefinition(LegacyRuleCollectionConverter::class, new Definition(LegacyRuleCollectionConverter::class, [new Reference(LegacyRuleNodeConverter::class)]));
        $container->setDefinition(LegacyRuleConverter::class, new Definition(LegacyRuleConverter::class, [new Reference(LegacyRuleCollectionConverter::class), new Reference(LegacyRuleMerger::class)]));
        $container->setDefinition(LegacyDocumentReader::class, new Definition(LegacyDocumentReader::class));
        $container->setDefinition(BundleConfigRenderer::class, new Definition(BundleConfigRenderer::class));
        $container->setDefinition(AtomicFileWriter::class, new Definition(AtomicFileWriter::class));
        $container->setDefinition(ImportLegacyRulesCommand::class, new Definition(ImportLegacyRulesCommand::class, [
            new Reference(LegacyDocumentReader::class), new Reference(LegacyRuleConverter::class),
            new Reference(BundleConfigRenderer::class), new Reference(AtomicFileWriter::class),
        ]))->addTag('console.command');

        /** @var array{profiles: array<string, mixed>, faker: array{enabled: bool, locale: string, seed: int|string|null}, personas: array{enabled: bool, locale: string}} $config */
        $config = $this->processConfiguration(new AnonymizerConfiguration(), $configs);
        $profiles = $this->createProfiles($config['profiles']);
        $container->setDefinition(YamlPayloadCodec::class, new Definition(YamlPayloadCodec::class))
            ->addTag('anonymizer.payload_codec', ['id' => 'yaml', 'builtin' => true]);

        if ($config['faker']['enabled']) {
            $fakerFactory = $this->requireFakerFactory(FakerReplacementStrategyFactory::class);
            $container->register('anonymizer.faker.generator')
                ->setFactory([$fakerFactory, 'createGenerator'])
                ->setArguments([$config['faker']['locale'], $config['faker']['seed']]);
            foreach (self::FAKER_IDS as $id) {
                $container->register('anonymizer.faker.' . str_replace('.', '_', $id))
                    ->setFactory([$fakerFactory, 'createStrategy'])
                    ->setArguments([new Reference('anonymizer.faker.generator'), $id])
                    ->addTag('anonymizer.replacement_strategy', ['id' => $id, 'builtin' => true]);
            }
        }
        if ($config['personas']['enabled']) {
            $personaFactory = $this->requireFakerFactory(PersonaReplacementStrategyFactory::class, 'personas');
            foreach (self::PERSONA_IDS as $id) {
                $container->register('anonymizer.personas.' . str_replace('.', '_', $id))
                    ->setFactory([$personaFactory, 'createStrategy'])
                    ->setArguments([$config['personas']['locale'], $id])
                    ->addTag('anonymizer.replacement_strategy', ['id' => $id, 'builtin' => true]);
            }
        }
        $profileReferences = [];
        foreach ($profiles as $name => $definition) {
            $profileServiceId = ConfiguredProfileProvider::class . '.' . $name;
            $container->setDefinition(
                $profileServiceId,
                new Definition(ProfileDefinition::class)
                    ->setFactory([ProfileDefinition::class, 'fromArray'])
                    ->setArguments([$name, $definition]),
            );
            $profileReferences[] = new Reference($profileServiceId);
        }

        $container->setDefinition(
            ConfiguredProfileProvider::class,
            new Definition(ConfiguredProfileProvider::class, [$profileReferences]),
        );
        $container->setDefinition(
            CompositeProfileProvider::class,
            new Definition(CompositeProfileProvider::class, [
                new Reference(ConfiguredProfileProvider::class),
                new TaggedIteratorArgument('anonymizer.profile_provider'),
            ]),
        );

        $anonymizer = new Definition(ArrayAnonymizer::class, [
            new Reference(CompositeProfileProvider::class),
            new TaggedIteratorArgument('anonymizer.replacement_strategy'),
            new TaggedIteratorArgument('anonymizer.payload_codec'),
        ]);
        $container->setDefinition(ArrayAnonymizer::class, $anonymizer);
        $container->setDefinition(SymfonyObjectAnonymizer::class, new Definition(SymfonyObjectAnonymizer::class, [
            new Reference(ArrayAnonymizer::class),
            new Reference('serializer'),
        ]));
        $container->setDefinition(SymfonyAnonymizer::class, new Definition(SymfonyAnonymizer::class, [
            new Reference(ArrayAnonymizer::class),
            new Reference(SymfonyObjectAnonymizer::class),
        ]));
        $container->setAlias(AnonymizerInterface::class, SymfonyAnonymizer::class)->setPublic(true);
    }

    #[Override]
    public function getAlias(): string
    {
        return 'anonymizer';
    }

    private function requireFakerFactory(string $class, string $integration = 'faker'): string
    {
        if (!class_exists($class)) {
            $package = $integration === 'personas' ? 'php-anonymizer/anonymizer-personas' : 'php-anonymizer/anonymizer-faker';
            throw new InvalidConfigurationException(sprintf('%s integration is enabled but %s is not installed. Install it with composer require %s.', ucfirst($integration), $package, $package));
        }

        return $class;
    }

    /** @param array<string, mixed> $definitions
     *  @return array<string, array<string, mixed>>
     */
    private function createProfiles(array $definitions): array
    {
        $profiles = [];
        foreach ($definitions as $name => $definition) {
            if (!is_array($definition)) {
                throw new InvalidConfigurationException(sprintf('The profile at "anonymizer.profiles.%s" must be a map.', $name));
            }

            /** @var array<string, mixed> $definition */

            try {
                ProfileDefinition::fromArray($name, $definition);
                $profiles[$name] = $definition;
            } catch (InvalidProfileDefinition $exception) {
                throw new InvalidConfigurationException(sprintf('Invalid configuration at "anonymizer.profiles.%s": %s', $name, $exception->getMessage()), $exception->getCode(), previous: $exception);
            }
        }

        return $profiles;
    }
}
