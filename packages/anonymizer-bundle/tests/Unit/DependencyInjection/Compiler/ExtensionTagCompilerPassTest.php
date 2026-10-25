<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Unit\DependencyInjection\Compiler;

use InvalidArgumentException;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler\ExtensionTagCompilerPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(ExtensionTagCompilerPass::class)]
final class ExtensionTagCompilerPassTest extends TestCase
{
    public function testAcceptsDistinctStrategyAndCodecTags(): void
    {
        $container = new ContainerBuilder();
        $container->register('strategy')->addTag('anonymizer.replacement_strategy', ['id' => 'hash']);
        $container->register('codec')->addTag('anonymizer.payload_codec', ['id' => 'csv']);

        new ExtensionTagCompilerPass()->process($container);

        self::assertCount(1, $container->findTaggedServiceIds('anonymizer.replacement_strategy'));
        self::assertCount(1, $container->findTaggedServiceIds('anonymizer.payload_codec'));
    }

    /** @param array<string, mixed> $attributes */
    #[DataProvider('invalidTagProvider')]
    public function testRejectsInvalidTags(string $tag, array $attributes, string $message): void
    {
        $container = new ContainerBuilder();
        $container->register('extension')->addTag($tag, $attributes);

        $this->expectExceptionObject(new InvalidArgumentException($message));
        new ExtensionTagCompilerPass()->process($container);
    }

    /** @return iterable<string, array{string, array<string, mixed>, string}> */
    public static function invalidTagProvider(): iterable
    {
        yield 'missing ID' => [
            'anonymizer.replacement_strategy',
            [],
            'Service "extension" uses tag "anonymizer.replacement_strategy" without a valid id.',
        ];
        yield 'invalid ID' => [
            'anonymizer.payload_codec',
            ['id' => 'not valid'],
            'Service "extension" uses tag "anonymizer.payload_codec" without a valid id.',
        ];
        yield 'built-in ID' => [
            'anonymizer.payload_codec',
            ['id' => 'json'],
            'Extension ID "json" is registered more than once for tag "anonymizer.payload_codec".',
        ];
    }

    public function testRejectsDuplicateIDs(): void
    {
        $container = new ContainerBuilder();
        $container->register('first')->addTag('anonymizer.replacement_strategy', ['id' => 'hash']);
        $container->register('second')->addTag('anonymizer.replacement_strategy', ['id' => 'hash']);

        $this->expectException(InvalidArgumentException::class);
        new ExtensionTagCompilerPass()->process($container);
    }

    public function testContinuesAfterBuiltInTagsAndRejectsLaterInvalidTag(): void
    {
        $container = new ContainerBuilder();
        $container->register('mixed')->addTag('anonymizer.payload_codec', [
            'id' => 'json', 'builtin' => true,
        ])->addTag('anonymizer.payload_codec', ['id' => 'not valid']);

        $this->expectException(InvalidArgumentException::class);
        new ExtensionTagCompilerPass()->process($container);
    }

    public function testRejectsIdentifierWithTrailingNewline(): void
    {
        $container = new ContainerBuilder();
        $container->register('extension')->addTag('anonymizer.replacement_strategy', ['id' => "valid\n"]);

        $this->expectException(InvalidArgumentException::class);
        new ExtensionTagCompilerPass()->process($container);
    }
}
