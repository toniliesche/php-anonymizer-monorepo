<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Unit\Application\LegacyImport;

use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\Compiler\RuleOptionsCompiler;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleDefinition;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyImportException;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleCollectionConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleMerger;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleNodeConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRulePathConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleValueConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyRuleConverter::class)]
#[CoversClass(LegacyRuleCollectionConverter::class)]
#[CoversClass(LegacyRuleMerger::class)]
#[CoversClass(LegacyRuleNodeConverter::class)]
#[CoversClass(LegacyRulePathConverter::class)]
#[CoversClass(LegacyRuleValueConverter::class)]
#[CoversClass(LegacyImportException::class)]
#[CoversClass(ProfileDefinition::class)]
#[CoversClass(RuleOptionsCompiler::class)]
#[CoversClass(RuleDefinition::class)]
final class LegacyRuleConverterTest extends TestCase
{
    public function testConvertsNestedListsAndFakerFields(): void
    {
        /** @var array{anonymizer: array{profiles: array{legacy: array{rules: list<array<string, mixed>>}}}} $result */
        $result = new LegacyRuleConverter(new LegacyRuleCollectionConverter(new LegacyRuleNodeConverter(new LegacyRuleValueConverter(), new LegacyRulePathConverter(new LegacyRuleValueConverter()))), new LegacyRuleMerger())->convert(['rules' => [
            'customers[].email' => 'email',
            'name' => '[#firstName]',
        ]]);

        /** @var array{field: string, each: list<array<string, mixed>>} $customers */
        $customers = $result['anonymizer']['profiles']['legacy']['rules'][0];
        /** @var array{field: string, strategy: string} $name */
        $name = $result['anonymizer']['profiles']['legacy']['rules'][1];
        self::assertSame('customers', $customers['field']);
        self::assertNotNull($customers['each'][0]['strategy']);
        self::assertSame('faker.first_name', $name['strategy']);
    }

    public function testRejectsObjectAccessAndMissingRules(): void
    {
        $converter = new LegacyRuleConverter(new LegacyRuleCollectionConverter(new LegacyRuleNodeConverter(new LegacyRuleValueConverter(), new LegacyRulePathConverter(new LegacyRuleValueConverter()))), new LegacyRuleMerger());
        try {
            $converter->convert(['rules' => ['user' => ['name' => 'user', 'data_access' => 'property']]]);
            self::fail('Object access should be rejected.');
        } catch (LegacyImportException $exception) {
            self::assertStringContainsString('object access', $exception->getMessage());
        }

        $this->expectException(LegacyImportException::class);
        $converter->convert([]);
    }

    public function testConvertsLegacyNodeLists(): void
    {
        /** @var array{anonymizer: array{profiles: array{legacy: array{rules: list<array<string, mixed>>}}}} $result */
        $result = new LegacyRuleConverter(new LegacyRuleCollectionConverter(new LegacyRuleNodeConverter(new LegacyRuleValueConverter(), new LegacyRulePathConverter(new LegacyRuleValueConverter()))), new LegacyRuleMerger())->convert(['rules' => [[
            'name' => 'address',
            'children' => [['name' => 'city', 'value_type' => 'city']],
        ]]]);

        /** @var array{field: string, children: list<array{strategy: string}>} $address */
        $address = $result['anonymizer']['profiles']['legacy']['rules'][0];
        self::assertSame('address', $address['field']);
        self::assertSame('faker.city', $address['children'][0]['strategy']);
    }

    public function testConvertsFakerConfigurationAndNestedAnonymizerRules(): void
    {
        $converter = new LegacyRuleConverter(new LegacyRuleCollectionConverter(new LegacyRuleNodeConverter(new LegacyRuleValueConverter(), new LegacyRulePathConverter(new LegacyRuleValueConverter()))), new LegacyRuleMerger());

        $result = $converter->convert([
            'anonymizer' => ['rules' => ['email' => 'email']],
            'faker' => ['enabled' => true, 'locale' => 'de_DE', 'seed' => 42],
        ]);

        /** @var array{anonymizer: array{faker: array{enabled: bool, locale: string, seed: int}}} $result */
        self::assertSame(['enabled' => true, 'locale' => 'de_DE', 'seed' => 42], $result['anonymizer']['faker']);
    }

    public function testMergesNestedRulesAndRejectsConflicts(): void
    {
        $merger = new LegacyRuleMerger();
        self::assertSame([['field' => 'user', 'children' => [['field' => 'name', 'strategy' => 'redact']]]], $merger->merge([
            ['field' => 'user', 'children' => [['field' => 'name', 'strategy' => 'redact']]],
            ['field' => 'user', 'children' => [['field' => 'name', 'strategy' => 'redact']]],
        ]));

        $this->expectException(LegacyImportException::class);
        $merger->merge([['field' => 'email', 'strategy' => 'redact'], ['field' => 'email', 'strategy' => 'faker.email']]);
    }

    public function testMergesDistinctNestedChildrenAndKeepsFollowingRules(): void
    {
        $result = new LegacyRuleMerger()->merge([
            ['field' => 'user', 'children' => [
                ['field' => 'name', 'strategy' => 'redact'],
                ['field' => 'id', 'strategy' => 'redact'],
            ]],
            ['field' => 'user', 'children' => [
                ['field' => 'email', 'strategy' => 'redact'],
                ['field' => 'phone', 'strategy' => 'redact'],
            ]],
            ['field' => 'active', 'strategy' => 'redact'],
        ]);

        self::assertSame([
            ['field' => 'user', 'children' => [
                ['field' => 'name', 'strategy' => 'redact'],
                ['field' => 'id', 'strategy' => 'redact'],
                ['field' => 'email', 'strategy' => 'redact'],
                ['field' => 'phone', 'strategy' => 'redact'],
            ]],
            ['field' => 'active', 'strategy' => 'redact'],
        ], $result);
    }

    public function testConflictWithNonStringFieldUsesUnknownLabel(): void
    {
        $this->expectExceptionMessageMatches('/unknown/');
        new LegacyRuleMerger()->merge([
            ['field' => 12, 'strategy' => 'redact'],
            ['field' => 12, 'strategy' => 'faker.email'],
        ]);
    }

    public function testRejectsMalformedLegacyCollectionsAndPaths(): void
    {
        $values = new LegacyRuleValueConverter();
        $nodes = new LegacyRuleNodeConverter($values, new LegacyRulePathConverter($values));
        $collections = new LegacyRuleCollectionConverter($nodes);

        try {
            $collections->convert([['name' => 'email', 1 => 'invalid']]);
            self::fail('Numeric node keys should be rejected.');
        } catch (LegacyImportException $exception) {
            self::assertStringContainsString('string keys', $exception->getMessage());
        }

        self::assertSame(['field' => 'tags', 'each' => true, 'on_scalar' => 'redact'], $nodes->convertNode(['name' => 'tags', 'is_array' => true, 'fallback' => ['value_type' => 'email']]));
        self::assertSame('redact', $values->strategy('unknown'));
        self::assertSame('email', $values->fallback(['value_type' => 'email']));

        $this->expectException(LegacyImportException::class);
        $nodes->convertPath('users.[].email', 'email');
    }

    public function testRejectsEmptyLegacyPaths(): void
    {
        $values = new LegacyRuleValueConverter();
        $nodes = new LegacyRuleNodeConverter($values, new LegacyRulePathConverter($values));

        $this->expectException(LegacyImportException::class);
        $nodes->convertPath('', 'email');
    }

    public function testRejectsNonMapNodesAndNonStringPaths(): void
    {
        $values = new LegacyRuleValueConverter();
        $nodes = new LegacyRuleNodeConverter($values, new LegacyRulePathConverter($values));
        $collections = new LegacyRuleCollectionConverter($nodes);

        try {
            $collections->convert([['name' => 'email'], 'invalid']);
            self::fail('Scalar legacy nodes should be rejected.');
        } catch (LegacyImportException $exception) {
            self::assertStringContainsString('must be maps', $exception->getMessage());
        }

        $this->expectException(LegacyImportException::class);
        /** @var array<int, mixed> $invalidPaths */
        $invalidPaths = [1 => 'email'];
        $collections->convert($invalidPaths);
    }

    public function testRejectsInvalidNodeDefinitions(): void
    {
        $values = new LegacyRuleValueConverter();
        $nodes = new LegacyRuleNodeConverter($values, new LegacyRulePathConverter($values));

        try {
            $nodes->convertNode(['name' => 'user', 'children' => ['invalid']]);
            self::fail('Scalar children should be rejected.');
        } catch (LegacyImportException $exception) {
            self::assertStringContainsString('must be maps', $exception->getMessage());
        }

        try {
            $nodes->convertNode(['name' => 'user', 'data_access' => 'property']);
            self::fail('Object access should be rejected.');
        } catch (LegacyImportException $exception) {
            self::assertStringContainsString('object access', $exception->getMessage());
        }

        self::assertSame(['field' => 'user', 'children' => [['field' => 'email', 'strategy' => 'faker.email']]], $nodes->convertPath('user.email', ['name' => 'email', 'value_type' => 'email']));

        $this->expectException(LegacyImportException::class);
        $nodes->convertNode([]);
    }
}
