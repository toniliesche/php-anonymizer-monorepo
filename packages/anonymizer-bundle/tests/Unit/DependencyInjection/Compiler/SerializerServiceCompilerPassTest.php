<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Unit\DependencyInjection\Compiler;

use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler\SerializerServiceCompilerPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

#[CoversClass(SerializerServiceCompilerPass::class)]
final class SerializerServiceCompilerPassTest extends TestCase
{
    public function testAcceptsSerializerDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->register('serializer');

        new SerializerServiceCompilerPass()->process($container);
        self::assertTrue($container->hasDefinition('serializer'));
    }

    public function testRejectsMissingSerializer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SerializerServiceCompilerPass()->process(new ContainerBuilder());
    }
}
