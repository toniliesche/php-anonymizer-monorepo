<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer;

use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResult;
use PhpAnonymizer\AnonymizerSymfonySerializer\Application\Anonymization\Result\ObjectAnonymizationResult;
use PhpAnonymizer\AnonymizerSymfonySerializer\Application\Exception\ObjectDenormalizationFailure;
use PhpAnonymizer\AnonymizerSymfonySerializer\Application\Exception\ObjectNormalizationFailure;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;

final readonly class SymfonyObjectAnonymizer
{
    public function __construct(
        private AnonymizerInterface $anonymizer,
        private NormalizerInterface&DenormalizerInterface $serializer,
    ) {}

    /** @param array<string, mixed> $context */
    public function anonymize(string $profile, object $object, array $context = []): object
    {
        return $this->anonymizeWithReport($profile, $object, $context)->object();
    }

    /** @param array<string, mixed> $context */
    public function anonymizeWithReport(string $profile, object $object, array $context = []): ObjectAnonymizationResult
    {
        $class = $object::class;
        if (array_key_exists('object_to_populate', $context)) {
            throw new ObjectDenormalizationFailure('Object anonymization does not support the object_to_populate context option; the source object is never mutated.');
        }

        try {
            $normalized = $this->serializer->normalize($object, null, $context);
        } catch (Throwable $exception) {
            throw new ObjectNormalizationFailure(sprintf('Unable to normalize %s for profile "%s".', $class, $profile), 0, $exception);
        }
        if (!is_array($normalized) || array_is_list($normalized)) {
            throw new ObjectNormalizationFailure(sprintf('The serializer must normalize %s to an associative array.', $class));
        }
        /** @var non-empty-array<string, mixed> $normalized */
        /** @var AnonymizationResult $result */
        $result = $this->anonymizer->anonymizeWithReport($profile, $normalized);
        try {
            $anonymized = $this->serializer->denormalize($result->data(), $class, null, $context);
        } catch (Throwable $exception) {
            throw new ObjectDenormalizationFailure(sprintf('Unable to denormalize the anonymized %s for profile "%s".', $class, $profile), 0, $exception);
        }
        if (!$anonymized instanceof $class) {
            throw new ObjectDenormalizationFailure(sprintf('The serializer returned %s instead of %s.', get_debug_type($anonymized), $class));
        }

        return new ObjectAnonymizationResult($anonymized, $result);
    }
}
