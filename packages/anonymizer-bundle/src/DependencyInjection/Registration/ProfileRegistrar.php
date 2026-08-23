<?php

declare(strict_types=1);

/** @codeCoverageIgnoreFile */

namespace PhpAnonymizer\AnonymizerBundle\DependencyInjection\Registration;

use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileInspector;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileInspectorInterface;
use PhpAnonymizer\Anonymizer\Domain\Exception\InvalidProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\CompositeProfileProvider;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\ConfiguredProfileProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

// @codeCoverageIgnoreStart
/**
 * @internal
 */
final class ProfileRegistrar
{
    /**
     * @param array<string, mixed> $definitions
     *
     * @codeCoverageIgnore Framework wiring.
     */
    public function register(array $definitions, ContainerBuilder $container): void
    {
        $profiles = $this->validate($definitions);
        $references = [];
        foreach ($profiles as $name => $definition) {
            $id = ConfiguredProfileProvider::class . '.' . $name;
            $container->setDefinition($id, new Definition(ProfileDefinition::class)
                ->setFactory([ProfileDefinition::class, 'fromArray'])
                ->setArguments([$name, $definition]));
            $references[] = new Reference($id);
        }

        $container->setDefinition(ConfiguredProfileProvider::class, new Definition(ConfiguredProfileProvider::class, [$references]));
        $container->setDefinition(CompositeProfileProvider::class, new Definition(CompositeProfileProvider::class, [
            new Reference(ConfiguredProfileProvider::class),
            new TaggedIteratorArgument('anonymizer.profile_provider'),
        ]));
        $container->setDefinition(ProfileInspector::class, new Definition(ProfileInspector::class, [new Reference(CompositeProfileProvider::class)]));
        $container->setAlias(ProfileInspectorInterface::class, ProfileInspector::class)->setPublic(true);
    }

    /**
     * @param array<string, mixed> $definitions
     *
     * @return array<string, array<string, mixed>>
     */
    private function validate(array $definitions): array
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
// @codeCoverageIgnoreEnd
