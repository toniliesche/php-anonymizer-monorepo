<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerLaravel\Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;
use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationLimits;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerFacade;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerRuntime;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ArrayAnonymizer;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Path\JsonPointer;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Policy\PreserveStringSelection;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Policy\PreserveTraversalPolicy;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Policy\RedactTraversalPolicy;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationReport;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Traversal\ArrayTraversal;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Traversal\ExecutionBudget;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Traversal\RuleTreeTraversal;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\EmbeddedPayloadTraversal;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\JsonPayloadCodec;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\PayloadCodecRegistry;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileCollection;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileInspection;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileInspector;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileInspectorInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileProviderInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileRegistry;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\Validation\EmbeddedProfileReferenceGraph;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\Validation\ProfileReferenceValidator;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\DefaultRedactionStrategy;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyRegistry;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\StarMaskStrategy;
use PhpAnonymizer\Anonymizer\Application\Extension\Transformation\CoreTransformationFactory;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\Compiler\RuleOptionsCompiler;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleDefinition;
use PhpAnonymizer\AnonymizerLaravel\AnonymizerServiceProvider;
use PhpAnonymizer\AnonymizerLaravel\Infrastructure\Payload\YamlPayloadCodec;
use PhpAnonymizer\AnonymizerLaravel\Infrastructure\Profile\ProfileComposer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(AnonymizerServiceProvider::class)]
#[UsesClass(AnonymizerBuilder::class)]
#[UsesClass(AnonymizerFacade::class)]
#[UsesClass(AnonymizerRuntime::class)]
#[UsesClass(ProfileDefinition::class)]
#[UsesClass(AnonymizationLimits::class)]
#[UsesClass(ArrayAnonymizer::class)]
#[UsesClass(JsonPointer::class)]
#[UsesClass(PreserveStringSelection::class)]
#[UsesClass(PreserveTraversalPolicy::class)]
#[UsesClass(RedactTraversalPolicy::class)]
#[UsesClass(AnonymizationReport::class)]
#[UsesClass(AnonymizationResult::class)]
#[UsesClass(ArrayTraversal::class)]
#[UsesClass(ExecutionBudget::class)]
#[UsesClass(RuleTreeTraversal::class)]
#[UsesClass(EmbeddedPayloadTraversal::class)]
#[UsesClass(JsonPayloadCodec::class)]
#[UsesClass(PayloadCodecRegistry::class)]
#[UsesClass(ProfileCollection::class)]
#[UsesClass(ProfileInspection::class)]
#[UsesClass(ProfileInspector::class)]
#[UsesClass(ProfileRegistry::class)]
#[UsesClass(EmbeddedProfileReferenceGraph::class)]
#[UsesClass(ProfileReferenceValidator::class)]
#[UsesClass(DefaultRedactionStrategy::class)]
#[UsesClass(ReplacementStrategyRegistry::class)]
#[UsesClass(StarMaskStrategy::class)]
#[UsesClass(CoreTransformationFactory::class)]
#[UsesClass(RuleOptionsCompiler::class)]
#[UsesClass(RuleDefinition::class)]
#[UsesClass(YamlPayloadCodec::class)]
#[UsesClass(ProfileComposer::class)]
final class AnonymizerServiceProviderTest extends TestCase
{
    public function testItComposesConfiguredAndTaggedProfilesAndBindsInterfaces(): void
    {
        self::assertNotNull($this->app);
        $anonymizer = $this->resolve(AnonymizerInterface::class);
        self::assertInstanceOf(AnonymizerInterface::class, $anonymizer);

        self::assertSame(['email' => '[REDACTED]'], $anonymizer->anonymize('consumer', ['email' => 'secret']));
        self::assertSame(['email' => '[REDACTED]'], $anonymizer->anonymize('tagged', ['email' => 'secret']));
        $inspector = $this->resolve(ProfileInspectorInterface::class);
        self::assertInstanceOf(ProfileInspectorInterface::class, $inspector);
        self::assertNotNull($inspector->inspect('tagged'));
    }

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [AnonymizerServiceProvider::class, TestProfileProviderServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        /** @var \Illuminate\Foundation\Application $app */
        /** @var Repository $config */
        $config = $app->make('config');
        $config->set('anonymizer.profiles', [
            'consumer' => [
                'mode' => 'redact',
                'rules' => [['field' => 'email']],
            ],
        ]);
    }

    private function resolve(string $abstract): mixed
    {
        self::assertNotNull($this->app);

        return $this->app->make($abstract);
    }
}

final class TestProfileProviderServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton(TestProfileProvider::class);
        $this->app->tag(TestProfileProvider::class, 'anonymizer.profile_provider');
    }
}

final readonly class TestProfileProvider implements ProfileProviderInterface
{
    #[Override]
    public function profiles(): iterable
    {
        yield ProfileDefinition::fromArray('tagged', [
            'mode' => 'redact',
            'rules' => [['field' => 'email']],
        ]);
    }
}
