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
use PhpAnonymizer\AnonymizerBundle\Application\LegacyImport\LegacyRuleValueConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyRuleConverter::class)]
#[CoversClass(LegacyRuleCollectionConverter::class)]
#[CoversClass(LegacyRuleMerger::class)]
#[CoversClass(LegacyRuleNodeConverter::class)]
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
        $result = new LegacyRuleConverter(new LegacyRuleCollectionConverter(new LegacyRuleNodeConverter(new LegacyRuleValueConverter())), new LegacyRuleMerger())->convert(['rules' => [
            'customers[].email' => 'email',
            'name' => '[#firstName]',
        ]]);

        /** @var array{field: string, each: list<array<string, mixed>>} $customers */
        $customers = $result['anonymizer']['profiles']['legacy']['rules'][0];
        /** @var array{field: string, strategy: string} $name */
        $name = $result['anonymizer']['profiles']['legacy']['rules'][1];
        self::assertSame('customers', $customers['field']);
        self::assertTrue($customers['each'][0]['strategy'] !== null);
        self::assertSame('faker.first_name', $name['strategy']);
    }

    public function testRejectsObjectAccessAndMissingRules(): void
    {
        $converter = new LegacyRuleConverter(new LegacyRuleCollectionConverter(new LegacyRuleNodeConverter(new LegacyRuleValueConverter())), new LegacyRuleMerger());
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
        $result = new LegacyRuleConverter(new LegacyRuleCollectionConverter(new LegacyRuleNodeConverter(new LegacyRuleValueConverter())), new LegacyRuleMerger())->convert(['rules' => [[
            'name' => 'address',
            'children' => [['name' => 'city', 'value_type' => 'city']],
        ]]]);

        /** @var array{field: string, children: list<array{strategy: string}>} $address */
        $address = $result['anonymizer']['profiles']['legacy']['rules'][0];
        self::assertSame('address', $address['field']);
        self::assertSame('faker.city', $address['children'][0]['strategy']);
    }
}
