<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler;

use Override;
use PhpAnonymizer\AnonymizerSymfonySerializer\SymfonySerializerExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

// @internal
final class SerializerServiceCompilerPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(SymfonySerializerExtension::class)) {
            return;
        }

        $argument = $container->getDefinition(SymfonySerializerExtension::class)->getArgument(0);
        $service = $argument instanceof Reference ? (string) $argument : 'serializer';
        if ($container->has($service)) {
            return;
        }

        throw new InvalidArgumentException(sprintf('The PHP Anonymizer Bundle requires the configured Symfony Serializer service "%s". Register Symfony Serializer (with your normalizers) before enabling anonymizer.serializer.', $service));
    }
}
