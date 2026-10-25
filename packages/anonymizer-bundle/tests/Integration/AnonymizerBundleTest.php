<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Integration;

use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationLimits;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilderFactory;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerFacade;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerRuntime;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ArrayAnonymizer;
use PhpAnonymizer\Anonymizer\Application\Anonymization\NdjsonAnonymizationService;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Path\JsonPointer;
use PhpAnonymizer\Anonymizer\Application\Anonymization\PayloadAnonymizationService;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Policy\PreserveStringSelection;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Policy\PreserveTraversalPolicy;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Policy\RedactTraversalPolicy;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationReport;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\RuntimeAssembler;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Traversal\ArrayTraversal;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Traversal\CompiledProfile;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Traversal\CompiledRuleList;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Traversal\ExecutionBudget;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Traversal\RuleSelector;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Traversal\RuleTreeTraversal;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Traversal\SelectedRule;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Traversal\SelectedValueDispatcher;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Traversal\TraversalExecutionContext;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\EmbeddedPayloadTraversal;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\JsonPayloadCodec;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\PayloadCodecRegistry;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileCollection;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileRegistry;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\Validation\EmbeddedProfileReferenceGraph;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\Validation\ProfileReferenceValidator;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\DefaultRedactionStrategy;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyRegistry;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\StarMaskStrategy;
use PhpAnonymizer\Anonymizer\Application\Extension\Transformation\CoreTransformationFactory;
use PhpAnonymizer\Anonymizer\Application\Extension\Transformation\DateGeneralizeTransformation;
use PhpAnonymizer\Anonymizer\Application\Extension\Transformation\NullifyTransformation;
use PhpAnonymizer\Anonymizer\Application\Extension\Transformation\NumericBucketTransformation;
use PhpAnonymizer\Anonymizer\Application\Extension\Transformation\PartialMaskTransformation;
use PhpAnonymizer\Anonymizer\Application\Extension\Transformation\TransformationRegistry;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileCompositionCompiler;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinitionData;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinitionParser;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileGlobalRuleParser;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileMetadataParser;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileRuleAppender;
use PhpAnonymizer\Anonymizer\Domain\Rule\Compiler\EmbeddedPayloadDefinitionParser;
use PhpAnonymizer\Anonymizer\Domain\Rule\Compiler\RuleOptionsCompiler;
use PhpAnonymizer\Anonymizer\Domain\Rule\Payload\EmbeddedPayloadDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleTraversalCombinationValidator;
use PhpAnonymizer\AnonymizerBundle\AnonymizerBundle;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleCollectionConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleMerger;
use PhpAnonymizer\AnonymizerBundle\Command\ImportLegacyRulesCommand;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\AnonymizerConfiguration;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\AnonymizerExtension;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler\ExtensionTagCompilerPass;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler\SerializerServiceCompilerPass;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Registration\CoreRegistrar;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Registration\LegacyImportRegistrar;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Registration\OptionalIntegrationRegistrar;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Registration\ProfileRegistrar;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\LegacyImport\LegacyDocumentReader;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Payload\YamlPayloadCodec;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\CompositeProfileProvider;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\ConfiguredProfileProvider;
use PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement\FakerFormatter;
use PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement\FakerReplacementStrategyFactory;
use PhpAnonymizer\AnonymizerPersonas\Infrastructure\Replacement\PersonaReplacementStrategyFactory;
use PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyObjectAnonymizerAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(AnonymizerBundle::class)]
#[UsesClass(AnonymizerBuilder::class)]
#[UsesClass(AnonymizerBuilderFactory::class)]
#[UsesClass(AnonymizerFacade::class)]
#[UsesClass(AnonymizerRuntime::class)]
#[UsesClass(ProfileCollection::class)]
#[UsesClass(SymfonyObjectAnonymizerAdapter::class)]
#[CoversClass(AnonymizerConfiguration::class)]
#[CoversClass(AnonymizerExtension::class)]
#[CoversClass(ExtensionTagCompilerPass::class)]
#[CoversClass(SerializerServiceCompilerPass::class)]
#[CoversClass(CompositeProfileProvider::class)]
#[CoversClass(ConfiguredProfileProvider::class)]
#[CoversClass(YamlPayloadCodec::class)]
#[UsesClass(FakerReplacementStrategyFactory::class)]
#[UsesClass(FakerFormatter::class)]
#[UsesClass(PersonaReplacementStrategyFactory::class)]
#[CoversClass(OptionalIntegrationRegistrar::class)]
#[CoversClass(ProfileRegistrar::class)]
#[CoversClass(ArrayAnonymizer::class)]
#[CoversClass(JsonPointer::class)]
#[CoversClass(AnonymizationLimits::class)]
#[CoversClass(ExecutionBudget::class)]
#[CoversClass(PreserveStringSelection::class)]
#[CoversClass(PreserveTraversalPolicy::class)]
#[CoversClass(RedactTraversalPolicy::class)]
#[CoversClass(AnonymizationReport::class)]
#[CoversClass(AnonymizationResult::class)]
#[CoversClass(ArrayTraversal::class)]
#[CoversClass(RuleTreeTraversal::class)]
#[UsesClass(CompiledProfile::class)]
#[UsesClass(CompiledRuleList::class)]
#[UsesClass(RuleSelector::class)]
#[UsesClass(SelectedRule::class)]
#[CoversClass(EmbeddedPayloadTraversal::class)]
#[CoversClass(JsonPayloadCodec::class)]
#[CoversClass(PayloadCodecRegistry::class)]
#[CoversClass(ProfileRegistry::class)]
#[CoversClass(EmbeddedProfileReferenceGraph::class)]
#[CoversClass(ProfileReferenceValidator::class)]
#[CoversClass(DefaultRedactionStrategy::class)]
#[CoversClass(ReplacementStrategyRegistry::class)]
#[CoversClass(StarMaskStrategy::class)]
#[CoversClass(ProfileDefinition::class)]
#[CoversClass(RuleOptionsCompiler::class)]
#[CoversClass(RuleDefinition::class)]
#[CoversClass(EmbeddedPayloadDefinition::class)]
#[CoversClass(CoreTransformationFactory::class)]
#[CoversClass(DateGeneralizeTransformation::class)]
#[CoversClass(NullifyTransformation::class)]
#[CoversClass(NumericBucketTransformation::class)]
#[CoversClass(PartialMaskTransformation::class)]
#[CoversClass(TransformationRegistry::class)]
#[UsesClass(NdjsonAnonymizationService::class)]
#[UsesClass(PayloadAnonymizationService::class)]
#[UsesClass(RuntimeAssembler::class)]
#[UsesClass(SelectedValueDispatcher::class)]
#[UsesClass(TraversalExecutionContext::class)]
#[CoversClass(ProfileCompositionCompiler::class)]
#[CoversClass(ProfileDefinitionData::class)]
#[CoversClass(ProfileDefinitionParser::class)]
#[CoversClass(ProfileMetadataParser::class)]
#[CoversClass(ProfileRuleAppender::class)]
#[CoversClass(CoreRegistrar::class)]
#[CoversClass(LegacyImportRegistrar::class)]
#[UsesClass(ProfileGlobalRuleParser::class)]
#[UsesClass(EmbeddedPayloadDefinitionParser::class)]
#[UsesClass(RuleTraversalCombinationValidator::class)]
final class AnonymizerBundleTest extends KernelTestCase
{
    #[Override]
    protected function tearDown(): void
    {
        restore_exception_handler();
        parent::tearDown();
    }

    public function testConfiguredProfileIsAvailableThroughAutowiring(): void
    {
        new AnonymizerBundle()->build(new ContainerBuilder());
        self::bootKernel(['debug' => false]);

        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);

        $anonymizer = $kernel->getContainer()->get(AnonymizerInterface::class);
        self::assertInstanceOf(AnonymizerInterface::class, $anonymizer);

        self::assertSame(['email' => '*****'], $anonymizer->anonymize('customer', ['email' => 'hello']));
        self::assertSame(['email' => 'custom:hello'], $anonymizer->anonymize('custom', ['email' => 'hello']));
        self::assertSame(['email' => 'custom:hello'], $anonymizer->anonymize('provided', ['email' => 'hello']));
        self::assertSame(['body' => 'custom:hello'], $anonymizer->anonymize('payload', ['body' => 'hello']));
    }

    public function testBuildRegistersBothCompilerPasses(): void
    {
        $container = new ContainerBuilder();
        new AnonymizerBundle()->build($container);
        $passes = $container->getCompiler()->getPassConfig()->getBeforeOptimizationPasses();
        self::assertNotSame([], array_filter($passes, static fn (object $pass): bool => $pass instanceof ExtensionTagCompilerPass));
        self::assertNotSame([], array_filter($passes, static fn (object $pass): bool => $pass instanceof SerializerServiceCompilerPass));
    }

    public function testFakerConfigurationRegistersStrategies(): void
    {
        $container = new ContainerBuilder();
        new AnonymizerExtension()->load([['faker' => ['enabled' => true, 'locale' => 'en_US', 'seed' => 1234]]], $container);

        self::assertTrue($container->hasDefinition('anonymizer.faker.faker_first_name'));
        self::assertTrue($container->hasDefinition(LegacyRuleConverter::class));
        self::assertTrue($container->hasDefinition(ImportLegacyRulesCommand::class));
        self::assertTrue($container->hasDefinition(LegacyRuleMerger::class));
        self::assertTrue($container->hasDefinition(LegacyRuleCollectionConverter::class));
        self::assertTrue($container->hasDefinition(LegacyDocumentReader::class));
        self::assertSame([['id' => 'faker.first_name', 'builtin' => true]], $container->getDefinition('anonymizer.faker.faker_first_name')->getTag('anonymizer.replacement_strategy'));
        $tags = $container->getDefinition(YamlPayloadCodec::class)->getTag('anonymizer.payload_codec');

        /** @var list<array{id: string}> $tags */
        self::assertSame('yaml', $tags[0]['id']);

        $method = new ReflectionMethod(OptionalIntegrationRegistrar::class, 'factory');
        $this->expectException(InvalidConfigurationException::class);
        $method->invoke(new OptionalIntegrationRegistrar(), 'Missing\FakerFactory', 'faker');
    }

    public function testPersonaConfigurationRegistersStrategies(): void
    {
        $container = new ContainerBuilder();
        new AnonymizerExtension()->load([['personas' => ['enabled' => true, 'locale' => 'es']]], $container);

        self::assertTrue($container->hasDefinition('anonymizer.personas.persona_first_name'));
        self::assertSame([['id' => 'persona.first_name', 'builtin' => true]], $container->getDefinition('anonymizer.personas.persona_first_name')->getTag('anonymizer.replacement_strategy'));
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }
}
