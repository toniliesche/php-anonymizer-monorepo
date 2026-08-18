<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Unit\Infrastructure\Profile;

use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\Compiler\RuleOptionsCompiler;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleDefinition;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\CompositeProfileProvider;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Profile\ConfiguredProfileProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompositeProfileProvider::class)]
#[CoversClass(ConfiguredProfileProvider::class)]
#[CoversClass(ProfileDefinition::class)]
#[CoversClass(RuleOptionsCompiler::class)]
#[CoversClass(RuleDefinition::class)]
final class CompositeProfileProviderTest extends TestCase
{
    public function testYieldsConfiguredAndTaggedProfiles(): void
    {
        $configured = ProfileDefinition::fromArray('configured', [
            'mode' => 'redact',
            'rules' => [['field' => 'email']],
        ]);
        $tagged = ProfileDefinition::fromArray('tagged', [
            'mode' => 'preserve',
            'rules' => [['field' => 'email']],
        ]);

        $provider = new CompositeProfileProvider(
            new ConfiguredProfileProvider([$configured]),
            [new ConfiguredProfileProvider([$tagged])],
        );

        self::assertSame(['configured', 'tagged'], array_map(
            static fn(ProfileDefinition $profile): string => $profile->name(),
            iterator_to_array($provider->profiles(), false),
        ));
    }
}
