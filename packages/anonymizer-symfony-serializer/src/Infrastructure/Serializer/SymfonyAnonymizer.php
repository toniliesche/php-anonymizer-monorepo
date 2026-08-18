<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer;

use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\PayloadAnonymizationResult;

final readonly class SymfonyAnonymizer implements AnonymizerInterface
{
    public function __construct(
        private AnonymizerInterface $arrayAnonymizer,
        private SymfonyObjectAnonymizer $objectAnonymizer,
    ) {}

    /** @return array<string, mixed>|object */
    #[Override]
    public function anonymize(string $profile, array|object $data): array|object
    {
        if (is_array($data)) {
            /** @var array<string, mixed> $result */
            $result = $this->arrayAnonymizer->anonymize($profile, $data);
            return $result;
        }

        return $this->objectAnonymizer->anonymize($profile, $data);
    }

    #[Override]
    public function anonymizeWithReport(string $profile, array|object $data): object
    {
        return is_array($data)
            ? $this->arrayAnonymizer->anonymizeWithReport($profile, $data)
            : $this->objectAnonymizer->anonymizeWithReport($profile, $data);
    }

    #[Override]
    public function anonymizePayload(string $profile, string $codec, string $payload): string
    {
        return $this->arrayAnonymizer->anonymizePayload($profile, $codec, $payload);
    }

    #[Override]
    public function anonymizePayloadWithReport(string $profile, string $codec, string $payload): PayloadAnonymizationResult
    {
        return $this->arrayAnonymizer->anonymizePayloadWithReport($profile, $codec, $payload);
    }
}
