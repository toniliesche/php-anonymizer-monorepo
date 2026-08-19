<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerPseudonymization\Application;

interface PseudonymKeyProviderInterface
{
    public function keyFor(string $keyId): string;
}
