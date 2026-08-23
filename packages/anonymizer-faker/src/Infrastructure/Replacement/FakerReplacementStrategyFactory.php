<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement;

use Faker\Factory;
use Faker\Generator;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyInterface;
use function Safe\unpack;

final class FakerReplacementStrategyFactory
{
    /**
     * @return list<FakerReplacementStrategy>
     */
    public static function create(string $locale = 'en_US', int|string|null $seed = null): array
    {
        $faker = self::createGenerator($locale, $seed);

        return array_map(
            static fn (FakerFormatter $formatter): FakerReplacementStrategy => new FakerReplacementStrategy($faker, $formatter),
            FakerFormatter::cases(),
        );
    }

    public static function createStrategy(Generator $faker, string $id): FakerReplacementStrategy
    {
        return new FakerReplacementStrategy($faker, FakerFormatter::from($id));
    }

    public static function createReplacementStrategy(Generator $faker, string $id): ReplacementStrategyInterface
    {
        return self::createStrategy($faker, $id);
    }

    public static function createGenerator(string $locale = 'en_US', int|string|null $seed = null): Generator
    {
        $faker = Factory::create($locale);
        if ($seed !== null) {
            $faker->seed(self::normalizeSeed($seed));
        }

        return $faker;
    }

    public static function normalizeSeed(int|string $seed): int
    {
        if (is_int($seed)) {
            return $seed;
        }

        $bytes = hash('sha256', $seed, true);

        /** @var array{seed: int} $parts */
        $parts = unpack('Nseed', $bytes);

        return $parts['seed'];
    }
}
