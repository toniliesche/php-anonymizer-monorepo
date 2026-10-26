<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\DependencyInjection\Registration;

use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Audit\AuditLoggerAdapter;
use PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement\FakerReplacementStrategyFactory;
use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaReplacementStrategyFactory;
use PhpAnonymizer\AnonymizerPseudonymization\Infrastructure\Replacement\HmacPseudonymStrategy;
use PhpAnonymizer\AnonymizerPseudonymization\Infrastructure\Transformation\DateShiftTransformation;
use PhpAnonymizer\AnonymizerSymfonySerializer\SymfonySerializerExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final class OptionalIntegrationRegistrar
{
    /**
     * @param array{
     *     faker: array{enabled: bool, locale: string, seed: null|int|string},
     *     personas: array{enabled: bool, locale: string},
     *     pseudonymization: array{enabled: bool, key_provider: null|string},
     *     serializer: array{enabled: bool, service: null|string},
     *     audit: array{enabled: bool, logger_service: null|string}
     * } $config
     */
    public function register(array $config, ContainerBuilder $container): void
    {
        $this->registerFaker($config['faker'], $container);
        $this->registerPersonas($config['personas'], $container);
        $this->registerPseudonymization($config['pseudonymization'], $container);
        $this->registerSerializer($config['serializer'], $container);
        $this->registerAudit($config['audit'], $container);
    }

    /**
     * @param array{enabled: bool, locale: string, seed: null|int|string} $config
     */
    private function registerFaker(array $config, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }
        $factory = $this->factory(FakerReplacementStrategyFactory::class, 'faker');
        $container->register('anonymizer.faker.generator')->setFactory([$factory, 'createGenerator'])->setArguments([$config['locale'], $config['seed']]);
        foreach (FakerReplacementStrategyFactory::ids() as $id) {
            $container->register('anonymizer.faker.' . str_replace('.', '_', $id))
                ->setFactory([$factory, 'createStrategy'])
                ->setArguments([new Reference('anonymizer.faker.generator'), $id])
                ->addTag('anonymizer.replacement_strategy', ['id' => $id, 'builtin' => true]);
        }
    }

    /**
     * @param array{enabled: bool, locale: string} $config
     */
    private function registerPersonas(array $config, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }
        $factory = $this->factory(PersonaReplacementStrategyFactory::class, 'personas');
        foreach (PersonaReplacementStrategyFactory::ids() as $id) {
            $container->register('anonymizer.personas.' . str_replace('.', '_', $id))
                ->setFactory([$factory, 'createStrategy'])
                ->setArguments([$config['locale'], $id])
                ->addTag('anonymizer.replacement_strategy', ['id' => $id, 'builtin' => true]);
        }
    }

    /**
     * @param array{enabled: bool, key_provider: null|string} $config
     */
    private function registerPseudonymization(array $config, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }
        // @codeCoverageIgnoreStart Optional package absence cannot occur in this installed monorepo.
        if (!class_exists(HmacPseudonymStrategy::class)) {
            // @codeCoverageIgnoreStart Defensive optional-package absence.
            throw new InvalidConfigurationException('Pseudonymisation is enabled but php-anonymizer/anonymizer-pseudonymization is not installed.'); // @codeCoverageIgnore
            // @codeCoverageIgnoreEnd
        }
        // @codeCoverageIgnoreEnd
        if ($config['key_provider'] === null || $config['key_provider'] === '') {
            throw new InvalidConfigurationException('anonymizer.pseudonymization.key_provider is required when pseudonymization is enabled.');
        }

        $keyProvider = $config['key_provider'];
        $key = new Reference($keyProvider);
        $container->setDefinition(HmacPseudonymStrategy::class, new Definition(HmacPseudonymStrategy::class, [$key])
            ->addTag('anonymizer.replacement_strategy', ['id' => 'pseudonymize', 'builtin' => true]));
        $container->setDefinition(DateShiftTransformation::class, new Definition(DateShiftTransformation::class, [$key])
            ->addTag('anonymizer.value_transformation', ['id' => 'date_shift', 'builtin' => true]));
    }

    /**
     * @param array{enabled: bool, service: null|string} $config
     */
    private function registerSerializer(array $config, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }
        $extension = $this->factory(SymfonySerializerExtension::class, 'serializer');
        $service = is_string($config['service']) && $config['service'] !== '' ? $config['service'] : 'serializer';
        $container->setDefinition($extension, new Definition($extension, [new Reference($service)])
            ->addTag('anonymizer.extension', ['priority' => 0]));
    }

    /**
     * @param array{enabled: bool, logger_service: null|string} $config
     */
    private function registerAudit(array $config, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }
        if ($config['logger_service'] === null || $config['logger_service'] === '') {
            throw new InvalidConfigurationException('anonymizer.audit.logger_service is required when anonymizer.audit.enabled is true.');
        }

        $loggerService = $config['logger_service'];
        $container->setDefinition(AuditLoggerAdapter::class, new Definition(AuditLoggerAdapter::class, [
            new Reference(AnonymizerInterface::class), new Reference($loggerService),
        ]));
    }

    /**
     * @return class-string
     */
    private function factory(string $class, string $integration): string
    {
        if (!class_exists($class)) {
            $package = match ($integration) {
                'personas' => 'php-anonymizer/anonymizer-personas',
                'serializer' => 'php-anonymizer/anonymizer-symfony-serializer',
                default => 'php-anonymizer/anonymizer-faker',
            };

            throw new InvalidConfigurationException(sprintf('%s integration is enabled but %s is not installed. Install it with composer require %s.', ucfirst($integration), $package, $package));
        }

        return $class;
    }
}
