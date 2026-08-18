<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\DependencyInjection;

use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ArrayAnonymizer;
use PhpAnonymizer\Anonymizer\Domain\Exception\InvalidProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\CompositeProfileProvider;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\ConfiguredProfileProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class AnonymizerExtension extends Extension
{
    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{profiles: array<string, mixed>} $config */
        $config = $this->processConfiguration(new AnonymizerConfiguration(), $configs);
        $profiles = $this->createProfiles($config['profiles']);
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
        $container->setAlias(AnonymizerInterface::class, ArrayAnonymizer::class)->setPublic(true);
    }

    #[Override]
    public function getAlias(): string
    {
        return 'anonymizer';
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
