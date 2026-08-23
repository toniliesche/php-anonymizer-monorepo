<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\DependencyInjection;

use Override;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Registration\CoreRegistrar;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Registration\LegacyImportRegistrar;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Registration\OptionalIntegrationRegistrar;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Registration\ProfileRegistrar;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class AnonymizerExtension extends Extension
{
    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{profiles: array<string, mixed>, faker: array{enabled: bool, locale: string, seed: null|int|string}, personas: array{enabled: bool, locale: string}, limits: array{max_input_bytes: int, max_depth: int, max_visited_nodes: int, max_collection_entries: int, max_embedded_payload_depth: int}, audit: array{enabled: bool, logger_service: null|string}, pseudonymization: array{enabled: bool, key_provider: null|string}, serializer: array{enabled: bool, service: null|string}} $config */
        $config = $this->processConfiguration(new AnonymizerConfiguration(), $configs);

        new LegacyImportRegistrar()->register($container);
        new ProfileRegistrar()->register($config['profiles'], $container);
        new OptionalIntegrationRegistrar()->register($config, $container);
        new CoreRegistrar()->register($config, $container);
    }

    #[Override]
    public function getAlias(): string
    {
        return 'anonymizer';
    }
    // @codeCoverageIgnoreEnd
}
