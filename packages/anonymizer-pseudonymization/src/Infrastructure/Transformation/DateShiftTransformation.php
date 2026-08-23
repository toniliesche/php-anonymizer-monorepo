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
use Webmozart\Assert\Assert;
use function Safe\preg_match;
use function Safe\unpack;

// @internal
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
    #[Override]
    public function transformWithContext(mixed $value, array $options, ValueTransformationContext $context): mixed
    {
        $this->validateOptions($options);
        $isString = is_string($value);
        $date = $this->date($value, $isString);

        $scopeData = $this->scope($options, $context);
        [$scopeAttribute, $maxDays, $scope] = $scopeData;
        $offset = $this->offset($context, $scopeAttribute, $scope, $maxDays);
        $shifted = $date->modify(sprintf('%+d days', $offset));

        return $isString ? $shifted->format(DateTimeInterface::RFC3339_EXTENDED) : $shifted;
    }

    private function date(mixed $value, bool $isString): DateTimeImmutable
    {
        $stringValue = is_string($value) ? $value : null;

        try {
            // @phpstan-ignore theCodingMachineSafe.class (guarded date conversion)
            $date = $isString ? new DateTimeImmutable($stringValue ?? '') : ($value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : null);
        } catch (Throwable) {
            throw new InvalidArgumentException('date_shift requires a valid date.');
        }
        if (!$date instanceof DateTimeImmutable || ($isString && preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:\.\d+)?(?:Z|[+-]\d\d:\d\d)$/D', $stringValue ?? '') !== 1)) {
            throw new InvalidArgumentException('date_shift requires a valid RFC 3339 date or DateTimeInterface.');
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{0: string, 1: int, 2: string}
     */
    private function scope(array $options, ValueTransformationContext $context): array
    {
        $scopeAttribute = $options['scope_attribute'] ?? null;
        $maxDays = $options['max_days'] ?? null;

        Assert::string($scopeAttribute);
        Assert::integer($maxDays);
        $scope = $context->attributes()[$scopeAttribute] ?? null;
        if (!is_string($scope)) {
            throw new InvalidArgumentException('date_shift scope attribute is missing from context.');
        }

        return [$scopeAttribute, $maxDays, $scope];
    }

    private function offset(ValueTransformationContext $context, string $scopeAttribute, string $scope, int $maxDays): int
    {
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

        $unpacked = unpack('N', hash_hmac('sha256', $message, $key, true));
        Assert::keyExists($unpacked, 1);
        Assert::integer($unpacked[1]);

        return ($unpacked[1] % (2 * $maxDays + 1)) - $maxDays;
    }
}
