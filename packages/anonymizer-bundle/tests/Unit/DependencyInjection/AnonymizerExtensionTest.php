<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Unit\DependencyInjection;

use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationLimits;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ArrayAnonymizer;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AuditAnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\BatchAnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ContextualAnonymizerInterface;
use PhpAnonymizer\Anonymizer\Domain\Exception\InvalidProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\Compiler\RuleOptionsCompiler;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleDefinition;
use PhpAnonymizer\AnonymizerBundle\AnonymizerBundle;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleCollectionConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleMerger;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleNodeConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRulePathConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleValueConverter;
use PhpAnonymizer\AnonymizerBundle\Command\ImportLegacyRulesCommand;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\AnonymizerConfiguration;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\AnonymizerExtension;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler\ExtensionTagCompilerPass;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler\SerializerServiceCompilerPass;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Audit\AuditLoggerAdapter;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\AtomicFileWriter;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\BundleConfigRenderer;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\LegacyDocumentReader;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\ConfiguredProfileProvider;
use PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement\FakerReplacementStrategyFactory;
use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaReplacementStrategyFactory;
use PhpAnonymizer\AnonymizerPseudonymization\Infrastructure\Replacement\HmacPseudonymStrategy;
use PhpAnonymizer\AnonymizerPseudonymization\Infrastructure\Transformation\DateShiftTransformation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

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

    public function testRegistersConfiguredLimitsExactly(): void
    {
        $container = new ContainerBuilder();
        new AnonymizerExtension()->load([['limits' => [
            'max_input_bytes' => 123,
            'max_depth' => 7,
            'max_visited_nodes' => 8,
            'max_collection_entries' => 9,
            'max_embedded_payload_depth' => 10,
        ]]], $container);

        self::assertSame([123, 7, 8, 9, 10], $container->getDefinition(AnonymizationLimits::class)->getArguments());
    }

    public function testRejectsZeroLimits(): void
    {
        foreach (['max_input_bytes', 'max_depth', 'max_visited_nodes', 'max_collection_entries', 'max_embedded_payload_depth'] as $name) {
            try {
                new AnonymizerExtension()->load([['limits' => [$name => 0]]], new ContainerBuilder());
                self::fail(sprintf('Expected %s to reject zero.', $name));
            } catch (InvalidConfigurationException $exception) {
                self::assertSame(InvalidConfigurationException::class, $exception::class);
            }
        }
    }

    public function testUsesDocumentedLimitDefaults(): void
    {
        $container = new ContainerBuilder();
        new AnonymizerExtension()->load([[]], $container);

        self::assertSame([10_485_760, 64, 100_000, 50_000, 8], $container->getDefinition(AnonymizationLimits::class)->getArguments());
    }

    public function testAcceptsOneAsTheMinimumForEveryLimit(): void
    {
        $container = new ContainerBuilder();
        new AnonymizerExtension()->load([['limits' => [
            'max_input_bytes' => 1,
            'max_depth' => 1,
            'max_visited_nodes' => 1,
            'max_collection_entries' => 1,
            'max_embedded_payload_depth' => 1,
        ]]], $container);

        self::assertSame([1, 1, 1, 1, 1], $container->getDefinition(AnonymizationLimits::class)->getArguments());
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
        self::assertSame([PersonaReplacementStrategyFactory::class, 'createStrategy'], $persona->getFactory());
        self::assertSame(['de', 'persona.name'], $persona->getArguments());
    }

    public function testAuditRequiresLoggerWhenEnabled(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new AnonymizerExtension()->load([['audit' => ['enabled' => true]]], new ContainerBuilder());
    }

    public function testRegistersConfiguredAuditLogger(): void
    {
        $container = new ContainerBuilder();
        new AnonymizerExtension()->load([['audit' => ['enabled' => true, 'logger_service' => 'logger']]], $container);

        self::assertTrue($container->hasDefinition(AuditLoggerAdapter::class));
        $audit = $container->getDefinition(AuditLoggerAdapter::class)->getArguments();
        self::assertSame([ArrayAnonymizer::class, 'logger'], $this->referenceIds($audit));
        self::assertTrue($container->hasAlias(AuditAnonymizerInterface::class));
        self::assertTrue($container->getAlias(AuditAnonymizerInterface::class)->isPublic());
    }

    public function testRegistersConfiguredPseudonymization(): void
    {
        $container = new ContainerBuilder();
        new AnonymizerExtension()->load([['pseudonymization' => ['enabled' => true, 'key_provider' => 'keys']]], $container);

        self::assertSame(['keys'], $this->referenceIds($container->getDefinition(HmacPseudonymStrategy::class)->getArguments()));

        /** @var list<array{id: string}> $tags */
        $tags = $container->getDefinition(HmacPseudonymStrategy::class)->getTag('anonymizer.replacement_strategy');
        self::assertSame([['id' => 'pseudonymize', 'builtin' => true]], $tags);
        self::assertSame(['keys'], $this->referenceIds($container->getDefinition(DateShiftTransformation::class)->getArguments()));
        self::assertSame([['id' => 'date_shift', 'builtin' => true]], $container->getDefinition(DateShiftTransformation::class)->getTag('anonymizer.value_transformation'));
        self::assertTrue($container->getAlias(ContextualAnonymizerInterface::class)->isPublic());
    }

    public function testPseudonymizationRequiresKeyProvider(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new AnonymizerExtension()->load([['pseudonymization' => ['enabled' => true]]], new ContainerBuilder());
    }

    public function testReportsMissingFakerIntegrationsPrecisely(): void
    {
        $method = new ReflectionMethod(AnonymizerExtension::class, 'requireFakerFactory');
        $extension = new AnonymizerExtension();
        foreach ([
            ['personas', 'Personas integration is enabled but php-anonymizer/anonymizer-personas is not installed.'],
            ['faker', 'Faker integration is enabled but php-anonymizer/anonymizer-faker is not installed.'],
        ] as [$integration, $prefix]) {
            try {
                $method->invoke($extension, 'Missing\Integration\Factory', $integration);
                self::fail('Expected missing integration exception.');
            } catch (InvalidConfigurationException $exception) {
                self::assertStringStartsWith($prefix, $exception->getMessage());
            }
        }
    }

    public function testRegistersLegacyImportDependencyGraph(): void
    {
        $container = new ContainerBuilder();
        new AnonymizerExtension()->load([[]], $container);

        foreach ([
            LegacyRuleMerger::class,
            LegacyRuleValueConverter::class,
            LegacyRulePathConverter::class,
            LegacyRuleNodeConverter::class,
            LegacyRuleCollectionConverter::class,
            LegacyRuleConverter::class,
            LegacyDocumentReader::class,
            BundleConfigRenderer::class,
            AtomicFileWriter::class,
        ] as $service) {
            self::assertTrue($container->hasDefinition($service), $service);
        }

        $path = $container->getDefinition(LegacyRulePathConverter::class)->getArguments();
        self::assertSame([LegacyRuleValueConverter::class], $this->referenceIds($path));

        $node = $container->getDefinition(LegacyRuleNodeConverter::class)->getArguments();
        self::assertSame([
            LegacyRuleValueConverter::class,
            LegacyRulePathConverter::class,
        ], $this->referenceIds($node));

        $collection = $container->getDefinition(LegacyRuleCollectionConverter::class)->getArguments();
        self::assertSame([LegacyRuleNodeConverter::class], $this->referenceIds($collection));

        $command = $container->getDefinition(ImportLegacyRulesCommand::class)->getArguments();
        self::assertSame([
            LegacyDocumentReader::class,
            LegacyRuleConverter::class,
            BundleConfigRenderer::class,
            AtomicFileWriter::class,
        ], $this->referenceIds($command));
        $converter = $container->getDefinition(LegacyRuleConverter::class)->getArguments();
        self::assertSame([
            LegacyRuleCollectionConverter::class,
            LegacyRuleMerger::class,
        ], $this->referenceIds($converter));
        self::assertTrue($container->getAlias(BatchAnonymizerInterface::class)->isPublic());
    }

    public function testBundleRegistersBothCompilerPasses(): void
    {
        $container = new ContainerBuilder();
        new AnonymizerBundle()->build($container);

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        self::assertNotSame([], array_filter($passes, static fn (object $pass): bool => $pass instanceof ExtensionTagCompilerPass));
        self::assertNotSame([], array_filter($passes, static fn (object $pass): bool => $pass instanceof SerializerServiceCompilerPass));
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function invalidProfilesProvider(): iterable
    {
        yield 'scalar profiles' => ['invalid'];

        yield 'list profiles' => [[['mode' => 'redact']]];

        yield 'scalar definition' => [['customer' => 'invalid']];

        yield 'invalid definition' => [['customer' => ['mode' => 'invalid', 'rules' => [['field' => 'email']]]]];
    }

    /** @param array<int|string, mixed> $arguments
     * @return list<string>
     */
    private function referenceIds(array $arguments): array
    {
        $ids = [];
        foreach ($arguments as $argument) {
            self::assertInstanceOf(Reference::class, $argument);
            $ids[] = $argument->__toString();
        }

        return $ids;
    }
}
