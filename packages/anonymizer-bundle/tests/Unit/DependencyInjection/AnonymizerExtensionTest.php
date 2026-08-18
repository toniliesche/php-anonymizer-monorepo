<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Unit\DependencyInjection;

use PhpAnonymizer\Anonymizer\Application\Anonymization\ArrayAnonymizer;
use PhpAnonymizer\Anonymizer\Domain\Exception\InvalidProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\Compiler\RuleOptionsCompiler;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleDefinition;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\AnonymizerConfiguration;
use PhpAnonymizer\AnonymizerBundle\DependencyInjection\AnonymizerExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(AnonymizerExtension::class)]
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
