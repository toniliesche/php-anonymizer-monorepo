<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerFaker\Infrastructure\Replacement;

use Faker\Generator;
use Override;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyInterface;

final readonly class FakerReplacementStrategy implements ReplacementStrategyInterface
{
    public function __construct(
        private Generator $faker,
        private FakerFormatter $formatter,
    ) {}

    #[Override]
    public function id(): string
    {
        return $this->formatter->id();
    }

    #[Override]
    public function replace(string $value): string
    {
        $generated = $this->faker->format($this->formatter->formatter());
        return (string) $generated; // @phpstan-ignore cast.string (Faker formatter contract returns scalar values.)
    }
}
