<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer;

use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ObjectAnonymizationRequest;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResultInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\ObjectAnonymizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal symfony Serializer object adapter implementation
 *
 * @codeCoverageIgnore Container/module adapter wiring.
 */
final readonly class SymfonyObjectAnonymizerAdapter implements ObjectAnonymizerInterface
{
    public function __construct(private NormalizerInterface&DenormalizerInterface $serializer)
    {
    }

    #[Override]
    public function anonymize(ObjectAnonymizationRequest $request, AnonymizerInterface $anonymizer): AnonymizationResultInterface
    {
        return new SymfonyObjectAnonymizer($anonymizer, $this->serializer)->anonymizeWithReportAndContext(
            $request->profile(),
            $request->object(),
            $request->context(),
            $request->adapterOptions(),
        );
    }
}
