<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Integration;

use Override;
use PhpAnonymizer\Anonymizer\Application\Extension\Replacement\ReplacementStrategyInterface;

final class TestReplacementStrategy implements ReplacementStrategyInterface
{
    #[Override]
    public function id(): string
    {
        return 'custom';
    }

    #[Override]
    public function replace(string $value): string
    {
        return 'custom:' . $value;
    }
}
