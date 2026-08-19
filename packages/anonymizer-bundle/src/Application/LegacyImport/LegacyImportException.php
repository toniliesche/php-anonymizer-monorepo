<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Application\LegacyImport;

use RuntimeException;

final class LegacyImportException extends RuntimeException
{
    public static function message(string $message): self
    {
        return new self($message);
    }
}
