<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerSymfonySerializer\Application\Anonymization\Result;

use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResultInterface;

final readonly class ObjectAnonymizationResult implements AnonymizationResultInterface
{
    public function __construct(
        private object $anonymizedObject,
        private AnonymizationResult $report,
    ) {
    }

    #[Override]
    public function data(): object
    {
        return $this->anonymizedObject;
    }

    public function object(): object
    {
        return $this->anonymizedObject;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function anonymizedPaths(): array
    {
        return $this->report->anonymizedPaths();
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function missingPaths(): array
    {
        return $this->report->missingPaths();
    }
}
