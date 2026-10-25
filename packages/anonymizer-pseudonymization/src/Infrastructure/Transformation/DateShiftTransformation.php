<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerPseudonymization\Infrastructure\Transformation;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ValueTransformationContext;
use PhpAnonymizer\Anonymizer\Application\Extension\Transformation\ContextualValueTransformationInterface;
use PhpAnonymizer\AnonymizerPseudonymization\Application\PseudonymKeyProviderInterface;
use Throwable;
use function Safe\preg_match;
use function Safe\unpack;

/**
 * @codeCoverageIgnore
 */
final readonly class DateShiftTransformation implements ContextualValueTransformationInterface
{
    public function __construct(private PseudonymKeyProviderInterface $keys)
    {
    }

    #[Override]
    public function id(): string
    {
        return 'date_shift';
    }

    #[Override]
    public function supportedTypes(): array
    {
        return ['temporal', 'string'];
    }

    #[Override]
    public function outputType(): string
    {
        return 'same';
    }

    /**
     * @param array<string,mixed> $options
     */ #[Override]
    public function validateOptions(array $options): void
    {
        if (!isset($options['max_days']) || !is_int($options['max_days']) || $options['max_days'] < 1 || $options['max_days'] > 36525) {
            throw new InvalidArgumentException('date_shift max_days must be between 1 and 36525.');
        }
        if (!isset($options['scope_attribute']) || !is_string($options['scope_attribute']) || $options['scope_attribute'] === '') {
            throw new InvalidArgumentException('date_shift requires scope_attribute.');
        }
    }

    /**
     * @param array<string,mixed> $options
     */ #[Override]
    public function transform(mixed $value, array $options): mixed
    {
        throw new InvalidArgumentException('date_shift requires contextual anonymization.');
    }

    /**
     * @param array<string,mixed> $options
     */
    // @phpstan-ignore complexity.functionLike (bounded validation flow)
    #[Override]
    public function transformWithContext(mixed $value, array $options, ValueTransformationContext $context): mixed
    {
        $this->validateOptions($options);
        $isString = is_string($value);

        try {
            // @phpstan-ignore theCodingMachineSafe.class (guarded date conversion)
            $date = $isString ? new DateTimeImmutable($value) : ($value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : null);
        } catch (Throwable) {
            throw new InvalidArgumentException('date_shift requires a valid date.');
        }
        if (!$date instanceof DateTimeImmutable || ($isString && preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:\.\d+)?(?:Z|[+-]\d\d:\d\d)$/D', $value) !== 1)) {
            throw new InvalidArgumentException('date_shift requires a valid RFC 3339 date or DateTimeInterface.');
        }
        $scopeAttribute = $options['scope_attribute'];
        $maxDays = $options['max_days'];
        if (!is_string($scopeAttribute) || !is_int($maxDays)) {
            throw new InvalidArgumentException('date_shift options are invalid.');
        } $scope = $context->attributes()[$scopeAttribute] ?? null;
        if ($scope === null) {
            throw new InvalidArgumentException('date_shift scope attribute is missing from context.');
        }

        try {
            $key = $this->keys->keyFor($context->keyId() ?? '');
        } catch (Throwable) {
            throw new InvalidArgumentException('date_shift key is unavailable.');
        }
        if ($key === '') {
            throw new InvalidArgumentException('date_shift key is unavailable.');
        }
        $parts = ['php-anonymizer/date-shift/v1', $context->keyId() ?? '', $context->profile(), (string) $context->path(), $scopeAttribute, $scope];
        $message = '';
        foreach ($parts as $part) {
            $message .= pack('N', mb_strlen($part, 'UTF-8')) . $part;
        }

        /** @var array{1:int} $unpacked */
        $unpacked = unpack('N', hash_hmac('sha256', $message, $key, true));
        $number = $unpacked[1];
        $offset = ($number % (2 * $maxDays + 1)) - $maxDays;
        $shifted = $date->modify(sprintf('%+d days', $offset));

        return $isString ? $shifted->format(DateTimeInterface::RFC3339_EXTENDED) : $shifted;
    }
}
