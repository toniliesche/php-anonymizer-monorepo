<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerSymfonySerializer\Application\Anonymization\Result;

use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResult;

final readonly class ObjectAnonymizationResult
{
    public function __construct(
        public object $object,
        public AnonymizationResult $report,
    ) {}
}
