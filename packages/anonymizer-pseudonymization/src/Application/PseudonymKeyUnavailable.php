<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerPseudonymization\Application;

use RuntimeException;

final class PseudonymKeyUnavailable extends RuntimeException
{
    public static function forId(string $keyId): self
    {
        return new self(sprintf('Pseudonym key "%s" is unavailable.', $keyId));
    }
}
