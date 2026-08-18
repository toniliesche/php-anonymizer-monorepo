<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler;

use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class SerializerServiceCompilerPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('serializer') || $container->hasAlias('serializer')) {
            return;
        }

        throw new InvalidArgumentException('The PHP Anonymizer Bundle requires a Symfony Serializer service named "serializer". Register Symfony Serializer (with your normalizers) before enabling the bundle.');
    }
}
