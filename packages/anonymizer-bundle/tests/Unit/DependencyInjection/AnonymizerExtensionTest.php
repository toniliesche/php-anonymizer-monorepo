<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Unit\DependencyInjection;

use PhpAnonymizer\Anonymizer\Application\Anonymization\ArrayAnonymizer;
use PhpAnonymizer\Anonymizer\Domain\Exception\InvalidProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\Compiler\RuleOptionsCompiler;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleDefinition;
use PhpAnonymizer\AnonymizerBundle\AnonymizerBundle;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\AnonymizerConfiguration;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\AnonymizerExtension;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler\ExtensionTagCompilerPass;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler\SerializerServiceCompilerPass;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\ConfiguredProfileProvider;
use PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement\FakerReplacementStrategyFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(AnonymizerExtension::class)]
#[CoversClass(AnonymizerBundle::class)]
#[CoversClass(AnonymizerConfiguration::class)]
#[CoversClass(InvalidProfileDefinition::class)]
#[CoversClass(ProfileDefinition::class)]
#[CoversClass(RuleOptionsCompiler::class)]
#[CoversClass(RuleDefinition::class)]
final class AnonymizerExtensionTest extends TestCase
{
    #[DataProvider('invalidProfilesProvider')]
    public function testRejectsInvalidProfileConfiguration(mixed $profiles): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new AnonymizerExtension()->load([['profiles' => $profiles]], new ContainerBuilder());
    }

    public function testLoadsValidProfilesAndRegistersCoreServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new AnonymizerExtension();
        $extension->load([[
            'profiles' => [
                'customer' => [
                    'mode' => 'redact',
                    'rules' => [['field' => 'email']],
                ],
            ],
        ]], $container);

        self::assertSame('anonymizer', $extension->getAlias());
        self::assertTrue($container->hasDefinition(ArrayAnonymizer::class));
        self::assertTrue($container->hasDefinition(ConfiguredProfileProvider::class . '.customer'));
    }

    public function testRegistersFakerAndPersonaFactoriesWithCompleteDefinitions(): void
    {
        $container = new ContainerBuilder();
        new AnonymizerExtension()->load([[
            'faker' => ['enabled' => true, 'locale' => 'en_US', 'seed' => 17],
            'personas' => ['enabled' => true, 'locale' => 'de'],
        ]], $container);

        $generator = $container->getDefinition('anonymizer.faker.generator');
        self::assertSame([FakerReplacementStrategyFactory::class, 'createGenerator'], $generator->getFactory());
        self::assertSame(['en_US', 17], $generator->getArguments());
        $faker = $container->getDefinition('anonymizer.faker.faker_name');
        self::assertSame([FakerReplacementStrategyFactory::class, 'createStrategy'], $faker->getFactory());
        self::assertCount(2, $faker->getArguments());
        /** @var list<array{id: string}> $tags */
        $tags = $faker->getTag('anonymizer.replacement_strategy');
        self::assertSame('faker.name', $tags[0]['id']);
        $persona = $container->getDefinition('anonymizer.personas.persona_name');
        self::assertSame(['de', 'persona.name'], $persona->getArguments());
    }

    public function testBundleRegistersBothCompilerPasses(): void
    {
        $container = new ContainerBuilder();
        new AnonymizerBundle()->build($container);

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        self::assertTrue(array_filter($passes, static fn(object $pass): bool => $pass instanceof ExtensionTagCompilerPass) !== []);
        self::assertTrue(array_filter($passes, static fn(object $pass): bool => $pass instanceof SerializerServiceCompilerPass) !== []);
    }

    /** @return iterable<string, array{0: mixed}> */
    public static function invalidProfilesProvider(): iterable
    {
        yield 'scalar profiles' => ['invalid'];
        yield 'list profiles' => [[['mode' => 'redact']]];
        yield 'scalar definition' => [['customer' => 'invalid']];
        yield 'invalid definition' => [['customer' => ['mode' => 'invalid', 'rules' => [['field' => 'email']]]]];
    }
}
