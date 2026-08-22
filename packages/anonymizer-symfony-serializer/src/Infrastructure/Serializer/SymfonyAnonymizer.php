<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer;

use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationContext;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ObjectAnonymizationRequest;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResultInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AuditAnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\DocumentPayloadAnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\NdjsonAnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\PayloadAnonymizationResult;

/**
 * @internal use SymfonySerializerExtension with AnonymizerBuilder
 *
 * @codeCoverageIgnore Transitional compatibility adapter.
 */
final readonly class SymfonyAnonymizer implements AnonymizerInterface
{
    public function __construct(
        private AnonymizerInterface $arrayAnonymizer,
        private SymfonyObjectAnonymizer $objectAnonymizer,
    ) {
    }

    /**
     * @return array<string, mixed>|object
     */
    #[Override]
    public function anonymize(string $profile, array|object $data, ?AnonymizationContext $context = null): array|object
    {
        if (is_array($data)) {
            return $context instanceof AnonymizationContext
                ? $this->arrayAnonymizer->anonymize($profile, $data, $context)
                : $this->arrayAnonymizer->anonymize($profile, $data);
        }

        return $context instanceof AnonymizationContext
            ? $this->objectAnonymizer->anonymizeWithContext($profile, $data, $context->attributes())
            : $this->objectAnonymizer->anonymize($profile, $data);
    }

    #[Override]
    public function anonymizeWithReport(string $profile, array|object $data, ?AnonymizationContext $context = null): AnonymizationResultInterface
    {
        if (is_array($data)) {
            return $context instanceof AnonymizationContext
                ? $this->arrayAnonymizer->anonymizeWithReport($profile, $data, $context)
                : $this->arrayAnonymizer->anonymizeWithReport($profile, $data);
        }

        return $context instanceof AnonymizationContext
            ? $this->objectAnonymizer->anonymizeWithReportAndContext($profile, $data, $context)
            : $this->objectAnonymizer->anonymizeWithReport($profile, $data);
    }

    #[Override]
    public function anonymizePayload(string $profile, string $codec, string $payload, ?AnonymizationContext $context = null): string
    {
        return $context instanceof AnonymizationContext
            ? $this->arrayAnonymizer->anonymizePayload($profile, $codec, $payload, $context)
            : $this->arrayAnonymizer->anonymizePayload($profile, $codec, $payload);
    }

    #[Override]
    public function anonymizePayloadWithReport(string $profile, string $codec, string $payload, ?AnonymizationContext $context = null): PayloadAnonymizationResult
    {
        return $context instanceof AnonymizationContext
            ? $this->arrayAnonymizer->anonymizePayloadWithReport($profile, $codec, $payload, $context)
            : $this->arrayAnonymizer->anonymizePayloadWithReport($profile, $codec, $payload);
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    #[Override]
    public function anonymizeRecords(string $profile, iterable $records, ?AnonymizationContext $context = null): iterable
    {
        return $this->arrayAnonymizer->anonymizeRecords($profile, $records, $context);
    }

    #[Override]
    public function anonymizeWithAudit(string $profile, array $data, ?AnonymizationContext $context = null): AuditAnonymizationResult
    {
        return $this->arrayAnonymizer->anonymizeWithAudit($profile, $data, $context);
    }

    #[Override]
    public function anonymizeDocument(string $profile, string $codec, string $payload, ?AnonymizationContext $context = null): string
    {
        return $this->arrayAnonymizer->anonymizeDocument($profile, $codec, $payload, $context);
    }

    #[Override]
    public function anonymizeDocumentWithReport(string $profile, string $codec, string $payload, ?AnonymizationContext $context = null): DocumentPayloadAnonymizationResult
    {
        return $this->arrayAnonymizer->anonymizeDocumentWithReport($profile, $codec, $payload, $context);
    }

    /**
     * @param iterable<mixed> $lines
     */
    #[Override]
    public function anonymizeNdjson(string $profile, iterable $lines, ?AnonymizationContext $context = null): NdjsonAnonymizationResult
    {
        return $this->arrayAnonymizer->anonymizeNdjson($profile, $lines, $context);
    }

    #[Override]
    public function anonymizeObject(ObjectAnonymizationRequest $request): object
    {
        return $this->objectAnonymizer->anonymizeWithContext($request->profile(), $request->object(), $request->context()->attributes(), $request->adapterOptions());
    }

    #[Override]
    public function anonymizeObjectWithReport(ObjectAnonymizationRequest $request): AnonymizationResultInterface
    {
        return $this->objectAnonymizer->anonymizeWithReportAndContext($request->profile(), $request->object(), $request->context(), $request->adapterOptions());
    }
}
