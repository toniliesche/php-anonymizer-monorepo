<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Unit\Application\LegacyImport;

use ArrayObject;
use InvalidArgumentException;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileCompositionCompiler;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinitionData;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinitionParser;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileGlobalRuleParser;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileMetadataParser;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileRuleAppender;
use PhpAnonymizer\Anonymizer\Domain\Rule\Compiler\EmbeddedPayloadDefinitionParser;
use PhpAnonymizer\Anonymizer\Domain\Rule\Compiler\RuleOptionsCompiler;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleDefinition;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleTraversalCombinationValidator;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyImportException;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleCollectionConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleMerger;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleNodeConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRulePathConverter;
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleValueConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
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
#[UsesClass(ProfileDefinitionParser::class)]
#[UsesClass(ProfileMetadataParser::class)]
#[UsesClass(ProfileCompositionCompiler::class)]
#[UsesClass(ProfileDefinitionData::class)]
#[UsesClass(ProfileRuleAppender::class)]
#[UsesClass(ProfileGlobalRuleParser::class)]
#[UsesClass(EmbeddedPayloadDefinitionParser::class)]
#[UsesClass(RuleTraversalCombinationValidator::class)]
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

    public function testConvertsTrimmedNestedArrayPathsAndTerminalArrays(): void
    {
        $values = new LegacyRuleValueConverter();
        $nodes = new LegacyRuleNodeConverter($values, new LegacyRulePathConverter($values));

        self::assertSame([
            'field' => 'customers',
            'each' => [[
                'field' => 'addresses',
                'each' => [['field' => 'email', 'strategy' => 'faker.email']],
            ]],
        ], $nodes->convertPath('.customers[].addresses[].email.', 'email'));
        self::assertSame([
            'field' => 'tags',
            'each' => true,
            'on_scalar' => 'redact',
        ], $nodes->convertPath('tags[]', 'unknown'));
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

    public function testRootRulesTakePrecedenceAndFakerConfigurationIsOptional(): void
    {
        $converter = new LegacyRuleConverter(new LegacyRuleCollectionConverter(new LegacyRuleNodeConverter(new LegacyRuleValueConverter(), new LegacyRulePathConverter(new LegacyRuleValueConverter()))), new LegacyRuleMerger());

        $withoutFaker = $converter->convert(['anonymizer' => ['rules' => ['nested' => 'email']]]);

        /** @var array{anonymizer: array{profiles: array{legacy: array{rules: list<array{strategy: string}>}}}} $withoutFaker */
        self::assertArrayNotHasKey('faker', $withoutFaker['anonymizer']);
        self::assertSame('faker.email', $withoutFaker['anonymizer']['profiles']['legacy']['rules'][0]['strategy']);

        $rootWins = $converter->convert([
            'rules' => ['root' => 'email'],
            'anonymizer' => ['rules' => ['nested' => 'email']],
            'faker' => ['enabled' => false, 'locale' => 'de_DE'],
        ]);

        /** @var array{anonymizer: array{profiles: array{legacy: array{rules: list<array{field: string}>}}}} $rootWins */
        self::assertSame('root', $rootWins['anonymizer']['profiles']['legacy']['rules'][0]['field']);
        self::assertArrayNotHasKey('faker', $rootWins['anonymizer']);

        $fallbackLocale = $converter->convert([
            'rules' => ['root' => 'email'],
            'faker' => ['enabled' => true, 'locale' => 123],
        ]);

        /** @var array{anonymizer: array{faker: array{enabled: bool, locale: string, seed: mixed}}} $fallbackLocale */
        self::assertSame(['enabled' => true, 'locale' => 'en_US', 'seed' => null], $fallbackLocale['anonymizer']['faker']);

        $disabledByDefault = $converter->convert([
            'rules' => ['root' => 'email'],
            'faker' => ['locale' => 'de_DE'],
        ]);
        self::assertIsArray($disabledByDefault['anonymizer']);
        self::assertArrayNotHasKey('faker', $disabledByDefault['anonymizer']);
    }

    public function testDoesNotReadRulesFromArrayAccessAnonymizerValues(): void
    {
        $converter = new LegacyRuleConverter(new LegacyRuleCollectionConverter(new LegacyRuleNodeConverter(new LegacyRuleValueConverter(), new LegacyRulePathConverter(new LegacyRuleValueConverter()))), new LegacyRuleMerger());

        $this->expectException(LegacyImportException::class);
        $this->expectExceptionMessageIsOrContains('The legacy document must contain a non-empty rules map.');
        $converter->convert(['anonymizer' => new ArrayObject(['rules' => ['email' => 'email']])]);
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

    public function testRejectsMalformedChildCollectionsBeforeMergingThem(): void
    {
        foreach ([
            [
                ['field' => 'user', 'children' => ['named' => ['field' => 'name']]],
                ['field' => 'user', 'children' => [['field' => 'email']]],
            ],
            [
                ['field' => 'user', 'children' => [['field' => 'name']]],
                ['field' => 'user', 'children' => ['named' => ['field' => 'email']]],
            ],
            [
                ['field' => 'user', 'children' => [['field' => 'name'], null]],
                ['field' => 'user', 'children' => [['field' => 'email']]],
            ],
            [
                ['field' => 'user', 'children' => [['field' => 'name']]],
                ['field' => 'user', 'children' => [['field' => 'email'], null]],
            ],
        ] as $rules) {
            try {
                new LegacyRuleMerger()->merge($rules);
                self::fail('Malformed child rule collections must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testPathLeafPrefersValueTypeAndIgnoresNonStringDefinitionsSafely(): void
    {
        $values = new LegacyRuleValueConverter();
        $paths = new LegacyRulePathConverter($values);

        self::assertSame(['field' => 'email', 'strategy' => 'faker.email'], $paths->convert('email', ['name' => 'email', 'value_type' => 'email', 'fallback' => ['value_type' => 'firstName']]));
        self::assertSame(['field' => 'email', 'strategy' => 'redact'], $paths->convert('email', ['unexpected' => true]));
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
        self::assertSame([
            'field' => 'users',
            'each' => [['field' => 'email', 'strategy' => 'faker.email']],
            'on_scalar' => 'redact',
        ], $nodes->convertNode(['name' => 'users', 'is_array' => true, 'children' => [['name' => 'email', 'value_type' => 'email']]]));
        self::assertSame('redact', $values->strategy('unknown'));
        self::assertSame('email', $values->fallback(['value_type' => 'email']));

        $this->expectException(LegacyImportException::class);
        $nodes->convertPath('users.[].email', 'email');
    }

    public function testPreservesMultipleLegacyNodeDefinitions(): void
    {
        $values = new LegacyRuleValueConverter();
        $nodes = new LegacyRuleNodeConverter($values, new LegacyRulePathConverter($values));
        $collections = new LegacyRuleCollectionConverter($nodes);

        self::assertSame([
            ['field' => 'email', 'strategy' => 'faker.email'],
            ['field' => 'name', 'strategy' => 'faker.first_name'],
        ], $collections->convert([
            ['name' => 'email', 'value_type' => 'email'],
            ['name' => 'name', 'value_type' => 'firstName'],
        ]));
    }

    public function testRejectsEmptyLegacyPaths(): void
    {
        $values = new LegacyRuleValueConverter();
        $nodes = new LegacyRuleNodeConverter($values, new LegacyRulePathConverter($values));

        $this->expectException(LegacyImportException::class);
        $nodes->convertPath('', 'email');
    }

    public function testRejectsDotOnlyAndInteriorEmptyLegacyPaths(): void
    {
        $values = new LegacyRuleValueConverter();
        $nodes = new LegacyRuleNodeConverter($values, new LegacyRulePathConverter($values));

        try {
            $nodes->convertPath('.', 'email');
            self::fail('Dot-only paths should be rejected.');
        } catch (LegacyImportException $exception) {
            self::assertSame('A legacy rule path cannot be empty.', $exception->getMessage());
        }

        $this->expectExceptionMessageMatches('/Invalid legacy path/');
        $nodes->convertPath('users..email', 'email');
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
            $nodes->convertNode(['name' => 'user', 'children' => ['city' => ['name' => 'city']]]);
            self::fail('Associative children should be rejected.');
        } catch (LegacyImportException $exception) {
            self::assertStringContainsString('must be a list', $exception->getMessage());
        }

        try {
            $nodes->convertNode(['name' => 'user', 'data_access' => 'property']);
            self::fail('Object access should be rejected.');
        } catch (LegacyImportException $exception) {
            self::assertStringContainsString('object access', $exception->getMessage());
        }

        self::assertSame(['field' => 'user', 'children' => [['field' => 'email', 'strategy' => 'faker.email']]], $nodes->convertPath('user.email', ['name' => 'email', 'value_type' => 'email']));

        try {
            $nodes->convertPath('user..email', 'email');
            self::fail('Interior-empty paths should be rejected.');
        } catch (LegacyImportException $exception) {
            self::assertStringContainsString('Invalid legacy path', $exception->getMessage());
        }

        $this->expectException(LegacyImportException::class);
        $nodes->convertNode([]);
    }
}
