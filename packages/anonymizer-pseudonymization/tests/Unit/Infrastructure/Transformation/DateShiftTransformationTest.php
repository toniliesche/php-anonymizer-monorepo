<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerPseudonymization\Tests\Unit\Infrastructure\Transformation;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationContext;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Path\JsonPointer;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ValueTransformationContext;
use PhpAnonymizer\AnonymizerPseudonymization\Infrastructure\Transformation\DateShiftTransformation;
use PhpAnonymizer\AnonymizerPseudonymization\Tests\Support\StaticPseudonymKeyProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use function Safe\unpack;

#[CoversClass(DateShiftTransformation::class)]
#[UsesClass(AnonymizationContext::class)]
#[UsesClass(JsonPointer::class)]
#[UsesClass(ValueTransformationContext::class)]
final class DateShiftTransformationTest extends TestCase
{
    public function testMetadataAndContextualTransformationAreDeterministic(): void
    {
        $transformation = new DateShiftTransformation(new StaticPseudonymKeyProvider());
        $context = $this->context();
        $options = ['max_days' => 10, 'scope_attribute' => 'tenant'];

        self::assertSame('date_shift', $transformation->id());
        self::assertSame(['temporal', 'string'], $transformation->supportedTypes());
        self::assertSame('same', $transformation->outputType());
        $first = $transformation->transformWithContext('2024-01-10T12:00:00Z', $options, $context);
        self::assertSame($first, $transformation->transformWithContext('2024-01-10T12:00:00Z', $options, $context));
        self::assertIsString($first);
        self::assertMatchesRegularExpression('/^2024-01-\d\dT12:00:00\.000\+00:00$/', $first);
        self::assertSame($this->expectedShift('2024-01-10T12:00:00Z', $context, 10, 'secret-primary'), $first);

        /** @phpstan-ignore theCodingMachineSafe.class (native DateTime input is intentional) */
        $date = new DateTimeImmutable('2024-01-10T12:00:00+00:00');
        $shifted = $transformation->transformWithContext($date, $options, $context);
        self::assertInstanceOf(DateTimeImmutable::class, $shifted);
        self::assertLessThanOrEqual(10, abs((int) $shifted->diff($date)->format('%r%a')));
    }

    public function testOptionValidationAndNonContextualCall(): void
    {
        $transformation = new DateShiftTransformation(new StaticPseudonymKeyProvider());
        $transformation->validateOptions(['max_days' => 1, 'scope_attribute' => 'tenant']);
        $transformation->validateOptions(['max_days' => 36525, 'scope_attribute' => 'tenant']);
        $this->expectException(InvalidArgumentException::class);
        $transformation->validateOptions([]);
    }

    public function testInvalidOptionTypesAndRangesAreRejected(): void
    {
        $transformation = new DateShiftTransformation(new StaticPseudonymKeyProvider());
        foreach ([['max_days' => '1', 'scope_attribute' => 'tenant'], ['max_days' => 0, 'scope_attribute' => 'tenant'], ['max_days' => 36526, 'scope_attribute' => 'tenant']] as $options) {
            try {
                $transformation->validateOptions($options);
                self::fail('Expected invalid date_shift options.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame(InvalidArgumentException::class, $exception::class);
            }
        }
    }

    public function testInvalidScopeAttributeOptionsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DateShiftTransformation(new StaticPseudonymKeyProvider())->validateOptions(['max_days' => 1, 'scope_attribute' => 123]);
    }

    public function testEmptyScopeAttributeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DateShiftTransformation(new StaticPseudonymKeyProvider())->validateOptions(['max_days' => 1, 'scope_attribute' => '']);
    }

    public function testNonContextualCallIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DateShiftTransformation(new StaticPseudonymKeyProvider())->transform('2024-01-10T12:00:00Z', []);
    }

    public function testInvalidDatesAndMissingScopeAreRejected(): void
    {
        $transformation = new DateShiftTransformation(new StaticPseudonymKeyProvider());
        $options = ['max_days' => 1, 'scope_attribute' => 'tenant'];

        $this->expectException(InvalidArgumentException::class);
        $transformation->transformWithContext('not-a-date', $options, $this->context());
    }

    public function testMalformedRfc3339DateIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DateShiftTransformation(new StaticPseudonymKeyProvider())->transformWithContext('2024-01-10 12:00:00', ['max_days' => 1, 'scope_attribute' => 'tenant'], $this->context());
    }

    public function testMissingScopeAndUnavailableKeyAreRejected(): void
    {
        $transformation = new DateShiftTransformation(new StaticPseudonymKeyProvider());
        $options = ['max_days' => 1, 'scope_attribute' => 'missing'];
        $this->expectException(InvalidArgumentException::class);
        $transformation->transformWithContext('2024-01-10T12:00:00Z', $options, $this->context());
    }

    public function testUnavailableAndEmptyKeysAreRejected(): void
    {
        $options = ['max_days' => 1, 'scope_attribute' => 'tenant'];
        $context = $this->context();
        $this->expectException(InvalidArgumentException::class);
        new DateShiftTransformation(new StaticPseudonymKeyProvider(failure: 'hidden'))->transformWithContext('2024-01-10T12:00:00Z', $options, $context);
    }

    public function testEmptyKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DateShiftTransformation(new StaticPseudonymKeyProvider(fixed: ''))->transformWithContext('2024-01-10T12:00:00Z', ['max_days' => 1, 'scope_attribute' => 'tenant'], $this->context());
    }

    private function context(): ValueTransformationContext
    {
        return new ValueTransformationContext('customer', JsonPointer::fromString('/created_at'), 'primary', new AnonymizationContext(['tenant' => 'äcme']));
    }

    private function expectedShift(string $value, ValueTransformationContext $context, int $maxDays, string $key): string
    {
        $parts = ['php-anonymizer/date-shift/v1', $context->keyId() ?? '', $context->profile(), (string) $context->path(), 'tenant', 'äcme'];
        $message = '';
        foreach ($parts as $part) {
            $message .= pack('N', mb_strlen($part, 'UTF-8')) . $part;
        }

        /** @var array{1:int} $unpacked */
        $unpacked = unpack('N', hash_hmac('sha256', $message, $key, true));
        $offset = ($unpacked[1] % (2 * $maxDays + 1)) - $maxDays;

        /** @phpstan-ignore theCodingMachineSafe.class (native DateTime input is intentional) */
        return new DateTimeImmutable($value)->modify(sprintf('%+d days', $offset))->format(DateTimeInterface::RFC3339_EXTENDED);
    }
}
