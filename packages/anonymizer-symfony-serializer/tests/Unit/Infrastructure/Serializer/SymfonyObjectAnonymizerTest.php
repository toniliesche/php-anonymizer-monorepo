<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerSymfonySerializer\Tests\Unit\Infrastructure\Serializer;

use ArrayObject;
use Closure;
use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResult;
use PhpAnonymizer\AnonymizerSymfonySerializer\Application\Anonymization\Result\ObjectAnonymizationResult;
use PhpAnonymizer\AnonymizerSymfonySerializer\Application\Exception\ObjectDenormalizationFailure;
use PhpAnonymizer\AnonymizerSymfonySerializer\Application\Exception\ObjectNormalizationFailure;
use PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyObjectAnonymizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

#[CoversClass(SymfonyObjectAnonymizer::class)]
#[CoversClass(ObjectAnonymizationResult::class)]
#[CoversClass(AnonymizationResult::class)]
final class SymfonyObjectAnonymizerTest extends TestCase
{
    public function testItCreatesAnImmutableAnonymizedObjectAndPassesContext(): void
    {
        $core = $this->createMock(AnonymizerInterface::class);
        $core->expects($this->once())->method('anonymizeWithReport')->with('default', ['name' => 'Jane'])->willReturn(new AnonymizationResult(['name' => 'John'], [], []));
        $adapter = new SymfonyObjectAnonymizer($core, new Serializer([new ObjectNormalizer()]));
        $source = new Person('Jane');

        $result = $adapter->anonymizeWithReport('default', $source, ['foo' => 'bar']);

        self::assertNotSame($source, $result->object());
        self::assertSame('Jane', $source->name);
        self::assertInstanceOf(Person::class, $result->object());
        self::assertSame('John', $result->object()->name);
        self::assertSame($result->object(), $result->data());
        self::assertSame([], $result->anonymizedPaths());
        self::assertSame([], $result->missingPaths());
    }

    public function testObjectToPopulateIsRejected(): void
    {
        $core = $this->createMock(AnonymizerInterface::class);
        $core->expects($this->never())->method('anonymizeWithReport');
        $adapter = new SymfonyObjectAnonymizer($core, new Serializer([new ObjectNormalizer()]));

        $this->expectException(ObjectDenormalizationFailure::class);
        $adapter->anonymize('default', new Person('Jane'), ['object_to_populate' => new Person('Old')]);
    }

    public function testNormalizationFailureIsWrapped(): void
    {
        $serializer = new TestSerializer(static fn(): never => throw new RuntimeException('broken'));
        $core = $this->createMock(AnonymizerInterface::class);
        $core->expects($this->never())->method('anonymizeWithReport');
        $adapter = new SymfonyObjectAnonymizer($core, $serializer);

        try {
            $adapter->anonymize('default', new Person('Jane'));
            self::fail('Expected normalization failure.');
        } catch (ObjectNormalizationFailure $failure) {
            self::assertSame(0, $failure->getCode());
            self::assertInstanceOf(RuntimeException::class, $failure->getPrevious());
        }
    }

    public function testListNormalizationIsRejected(): void
    {
        $serializer = new TestSerializer(static fn(mixed $value): array => ['Jane']);
        $core = $this->createMock(AnonymizerInterface::class);
        $core->expects($this->never())->method('anonymizeWithReport');
        $adapter = new SymfonyObjectAnonymizer($core, $serializer);

        $this->expectException(ObjectNormalizationFailure::class);
        $adapter->anonymize('default', new Person('Jane'));
    }

    public function testDenormalizationFailureIsWrapped(): void
    {
        $serializer = new TestSerializer(static fn(mixed $value): array => ['name' => 'Jane'], static fn(mixed $data, string $type): never => throw new RuntimeException('broken'));
        $core = $this->createMock(AnonymizerInterface::class);
        $core->expects($this->once())->method('anonymizeWithReport')->willReturn(new AnonymizationResult(['name' => 'John'], [], []));
        $adapter = new SymfonyObjectAnonymizer($core, $serializer);

        try {
            $adapter->anonymize('default', new Person('Jane'));
            self::fail('Expected denormalization failure.');
        } catch (ObjectDenormalizationFailure $failure) {
            self::assertSame(0, $failure->getCode());
            self::assertInstanceOf(RuntimeException::class, $failure->getPrevious());
        }
    }

    public function testWrongDenormalizedClassIsRejected(): void
    {
        $serializer = new TestSerializer(static fn(mixed $value): array => ['name' => 'Jane'], static fn(mixed $data, string $type): stdClass => new stdClass());
        $core = $this->createMock(AnonymizerInterface::class);
        $core->expects($this->once())->method('anonymizeWithReport')->willReturn(new AnonymizationResult(['name' => 'John'], [], []));
        $adapter = new SymfonyObjectAnonymizer($core, $serializer);

        $this->expectException(ObjectDenormalizationFailure::class);
        $adapter->anonymize('default', new Person('Jane'));
    }
}

final class Person
{
    public function __construct(public string $name) {}
}

final readonly class TestSerializer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @param Closure(mixed): (array<int|string, mixed>|string|int|float|bool|ArrayObject<int|string, mixed>|null) $normalize
     * @param Closure(mixed, string): mixed|null $denormalize
     */
    public function __construct(private Closure $normalize, private ?Closure $denormalize = null) {}
    #[Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|ArrayObject|null
    {
        /** @var array<int|string, mixed>|string|int|float|bool|ArrayObject<int|string, mixed>|null $result */
        $result = ($this->normalize)($data);
        return $result;
    }
    #[Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return true;
    }
    #[Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        return ($this->denormalize ?? static fn(): object => new $type())($data, $type);
    }
    #[Override]
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return true;
    }
    #[Override]
    public function getSupportedTypes(?string $format): array
    {
        return ['*' => true];
    }
}
