<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer;

use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationContext;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ObjectAnonymizationRequest;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResultInterface;
use PhpAnonymizer\Anonymizer\Application\Extension\ObjectAnonymizerInterface;
use PhpAnonymizer\AnonymizerSymfonySerializer\Application\Anonymization\Result\ObjectAnonymizationResult;
use PhpAnonymizer\AnonymizerSymfonySerializer\Application\Exception\ObjectDenormalizationFailure;
use PhpAnonymizer\AnonymizerSymfonySerializer\Application\Exception\ObjectNormalizationFailure;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;
use Webmozart\Assert\Assert;

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
        return $this->anonymizeWithReportAndContext(
            $request->profile(),
            $request->object(),
            $request->context(),
            $request->adapterOptions(),
            $anonymizer,
        );
    }

    /**
     * @param array<string, mixed> $serializerContext
     */
    private function anonymizeWithReportAndContext(string $profile, object $object, AnonymizationContext $context, array $serializerContext, AnonymizerInterface $anonymizer): ObjectAnonymizationResult
    {
        $class = $object::class;

        try {
            $normalized = $this->serializer->normalize($object, null, $serializerContext);
        } catch (Throwable $exception) {
            throw new ObjectNormalizationFailure(sprintf('Unable to normalize %s for profile "%s".', $class, $profile), 0, $exception);
        }

        if (!is_array($normalized) || array_is_list($normalized)) {
            throw new ObjectNormalizationFailure(sprintf('The serializer must normalize %s to an associative array.', $class));
        }

        Assert::isNonEmptyMap($normalized);
        $result = $anonymizer->anonymizeWithReport($profile, $normalized, $context);
        Assert::isInstanceOf($result, AnonymizationResult::class);

        try {
            $anonymized = $this->serializer->denormalize($result->data(), $class, null, $serializerContext);
        } catch (Throwable $exception) {
            throw new ObjectDenormalizationFailure(sprintf('Unable to denormalize the anonymized %s for profile "%s".', $class, $profile), 0, $exception);
        }
        if (!$anonymized instanceof $class) {
            throw new ObjectDenormalizationFailure(sprintf('The serializer returned %s instead of %s.', get_debug_type($anonymized), $class));
        }

        return new ObjectAnonymizationResult($anonymized, $result);
    }
}
