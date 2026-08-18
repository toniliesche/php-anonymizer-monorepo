<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Integration;

use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ArrayAnonymizer;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Path\JsonPointer;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Policy\PreserveStringSelection;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Policy\PreserveTraversalPolicy;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Policy\RedactTraversalPolicy;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationReport;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Traversal\ArrayTraversal;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Traversal\RuleTreeTraversal;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\EmbeddedPayloadTraversal;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\JsonPayloadCodec;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\PayloadCodecRegistry;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileRegistry;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\Validation\EmbeddedProfileReferenceGraph;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\Validation\ProfileReferenceValidator;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\DefaultRedactionStrategy;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyRegistry;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\StarMaskStrategy;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\Compiler\RuleOptionsCompiler;
use PhpAnonymizer\Anonymizer\Domain\Rule\Payload\EmbeddedPayloadDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleDefinition;
use PhpAnonymizer\AnonymizerBundle\AnonymizerBundle;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\AnonymizerConfiguration;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\AnonymizerExtension;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler\ExtensionTagCompilerPass;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\Compiler\SerializerServiceCompilerPass;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Payload\YamlPayloadCodec;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\CompositeProfileProvider;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\ConfiguredProfileProvider;
use PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyAnonymizer;
use PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyObjectAnonymizer;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(AnonymizerBundle::class)]
#[CoversClass(AnonymizerConfiguration::class)]
#[CoversClass(AnonymizerExtension::class)]
#[CoversClass(ExtensionTagCompilerPass::class)]
#[CoversClass(SerializerServiceCompilerPass::class)]
#[CoversClass(SymfonyAnonymizer::class)]
#[CoversClass(SymfonyObjectAnonymizer::class)]
#[CoversClass(CompositeProfileProvider::class)]
#[CoversClass(ConfiguredProfileProvider::class)]
#[CoversClass(YamlPayloadCodec::class)]
#[CoversClass(ArrayAnonymizer::class)]
#[CoversClass(JsonPointer::class)]
#[CoversClass(PreserveStringSelection::class)]
#[CoversClass(PreserveTraversalPolicy::class)]
#[CoversClass(RedactTraversalPolicy::class)]
#[CoversClass(AnonymizationReport::class)]
#[CoversClass(AnonymizationResult::class)]
#[CoversClass(ArrayTraversal::class)]
#[CoversClass(RuleTreeTraversal::class)]
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
final class AnonymizerBundleTest extends KernelTestCase
{
    public function testConfiguredProfileIsAvailableThroughAutowiring(): void
    {
        new AnonymizerBundle()->build(new ContainerBuilder());
        self::bootKernel(['debug' => false]);

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        /** @var AnonymizerInterface $anonymizer */
        $anonymizer = $kernel->getContainer()->get(AnonymizerInterface::class);

        self::assertSame(
            ['email' => '*****'],
            $anonymizer->anonymize('customer', ['email' => 'hello']),
        );
        self::assertSame(['email' => 'custom:hello'], $anonymizer->anonymize('custom', ['email' => 'hello']));
        self::assertSame(['email' => 'custom:hello'], $anonymizer->anonymize('provided', ['email' => 'hello']));
        self::assertSame(['body' => 'custom:hello'], $anonymizer->anonymize('payload', ['body' => 'hello']));
    }

    public function testFakerConfigurationRegistersStrategies(): void
    {
        $container = new ContainerBuilder();
        new AnonymizerExtension()->load([['faker' => ['enabled' => true, 'locale' => 'en_US', 'seed' => 1234]]], $container);

        self::assertTrue($container->hasDefinition('anonymizer.faker.faker_first_name'));
        $tags = $container->getDefinition(YamlPayloadCodec::class)->getTag('anonymizer.payload_codec');
        /** @var array<int, array{id: string}> $tags */
        self::assertSame('yaml', $tags[0]['id']);

        $method = new ReflectionMethod(AnonymizerExtension::class, 'requireFakerFactory');
        $this->expectException(InvalidConfigurationException::class);
        $method->invoke(new AnonymizerExtension(), 'Missing\\FakerFactory');
    }

    public function testPersonaConfigurationRegistersStrategies(): void
    {
        $container = new ContainerBuilder();
        new AnonymizerExtension()->load([['personas' => ['enabled' => true, 'locale' => 'es']]], $container);

        self::assertTrue($container->hasDefinition('anonymizer.personas.persona_first_name'));
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function tearDown(): void
    {
        restore_exception_handler();
        parent::tearDown();
    }

}
