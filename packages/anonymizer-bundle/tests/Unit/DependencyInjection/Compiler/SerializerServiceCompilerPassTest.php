<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Unit\DependencyInjection\Compiler;

use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler\SerializerServiceCompilerPass;
use PhpAnonymizer\AnonymizerSymfonySerializer\SymfonySerializerExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(SerializerServiceCompilerPass::class)]
final class SerializerServiceCompilerPassTest extends TestCase
{
    public function testAcceptsSerializerDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->register('serializer');
        $container->register(SymfonySerializerExtension::class)
            ->setArguments([new Reference('serializer')]);

        new SerializerServiceCompilerPass()->process($container);
        self::assertTrue($container->hasDefinition('serializer'));
    }

    public function testRejectsMissingSerializer(): void
    {
        $container = new ContainerBuilder();
        $container->register(SymfonySerializerExtension::class)
            ->setArguments([new Reference('serializer')]);
        $this->expectException(InvalidArgumentException::class);
        new SerializerServiceCompilerPass()->process($container);
    }

    public function testUsesDefaultSerializerWhenConfigurationArgumentIsNotAReference(): void
    {
        $container = new ContainerBuilder();
        $container->register('serializer');
        $container->register(SymfonySerializerExtension::class)
            ->setArguments(['custom_serializer']);

        new SerializerServiceCompilerPass()->process($container);
        self::assertTrue($container->hasDefinition('serializer'));
    }
}
