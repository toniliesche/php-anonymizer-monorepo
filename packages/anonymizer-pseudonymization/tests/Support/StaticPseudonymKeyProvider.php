<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerPseudonymization\Tests\Support;

use Override;
use PhpAnonymizer\AnonymizerPseudonymization\Application\PseudonymKeyProviderInterface;
use RuntimeException;

final readonly class StaticPseudonymKeyProvider implements PseudonymKeyProviderInterface
{
    public function __construct(private string $prefix = 'secret-', private ?string $fixed = null, private ?string $failure = null)
    {
    }

    #[Override]
    public function keyFor(string $keyId): string
    {
        if ($this->failure !== null) {
            throw new RuntimeException($this->failure);
        }

        return $this->fixed ?? $this->prefix . $keyId;
    }
}
