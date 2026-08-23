<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerFaker\Tests\Unit\Infrastructure\Replacement;

use PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement\FakerFormatter;
use PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement\FakerReplacementStrategy;
use PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement\FakerReplacementStrategyFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FakerFormatter::class)]
#[CoversClass(FakerReplacementStrategy::class)]
#[CoversClass(FakerReplacementStrategyFactory::class)]
final class FakerReplacementStrategyFactoryTest extends TestCase
{
    public function testCreatesAllDocumentedStrategies(): void
    {
        $strategies = FakerReplacementStrategyFactory::create(seed: 1234);

        self::assertCount(15, $strategies);
        self::assertSame(array_map(static fn (FakerFormatter $formatter): string => $formatter->id(), FakerFormatter::cases()), array_map(static fn (FakerReplacementStrategy $strategy): string => $strategy->id(), $strategies));
        foreach ($strategies as $strategy) {
            self::assertNotSame('', $strategy->replace('ignored'));
        }
    }

    public function testTextSeedsAreDeterministic(): void
    {
        $first = FakerReplacementStrategyFactory::create(seed: 'stable');
        $firstValues = array_map(static fn (FakerReplacementStrategy $strategy): string => $strategy->replace('x'), $first);
        $second = FakerReplacementStrategyFactory::create(seed: 'stable');

        self::assertSame($firstValues, array_map(static fn (FakerReplacementStrategy $strategy): string => $strategy->replace('x'), $second));
    }

    public function testIntegerSeedsPassThrough(): void
    {
        self::assertSame(42, FakerReplacementStrategyFactory::normalizeSeed(42));
        self::assertGreaterThanOrEqual(0, FakerReplacementStrategyFactory::normalizeSeed('text'));
    }

    public function testCreatesOneStrategyFromItsPublicId(): void
    {
        $faker = FakerReplacementStrategyFactory::createGenerator(seed: 1);
        $strategy = FakerReplacementStrategyFactory::createStrategy($faker, 'faker.city');

        self::assertSame('faker.city', $strategy->id());
        self::assertNotSame('', $strategy->replace('ignored'));
    }

    public function testCreatesOneStrategyThroughTheStableReplacementPort(): void
    {
        $strategy = FakerReplacementStrategyFactory::createReplacementStrategy(FakerReplacementStrategyFactory::createGenerator(seed: 1), 'faker.city');

        self::assertSame('faker.city', $strategy->id());
    }
}
