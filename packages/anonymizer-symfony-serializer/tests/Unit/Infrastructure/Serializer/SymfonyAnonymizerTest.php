<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerSymfonySerializer\Tests\Unit\Infrastructure\Serializer;

use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\PayloadAnonymizationResult;
use PhpAnonymizer\AnonymizerSymfonySerializer\Application\Anonymization\Result\ObjectAnonymizationResult;
use PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyAnonymizer;
use PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyObjectAnonymizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

#[CoversClass(SymfonyAnonymizer::class)]
#[CoversClass(SymfonyObjectAnonymizer::class)]
#[CoversClass(ObjectAnonymizationResult::class)]
#[CoversClass(AnonymizationResult::class)]
#[CoversClass(PayloadAnonymizationResult::class)]
final class SymfonyAnonymizerTest extends TestCase
{
    public function testDispatchesArraysObjectsAndPayloadsThroughOneInterface(): void
    {
        $array = $this->createMock(AnonymizerInterface::class);
        $array->expects($this->once())->method('anonymize')->with('p', ['name' => 'Jane'])->willReturn(['name' => 'John']);
        $array->expects($this->once())->method('anonymizeWithReport')->with('p', ['name' => 'Jane'])->willReturn(new AnonymizationResult(['name' => 'John'], [], []));
        $array->expects($this->once())->method('anonymizePayload')->with('p', 'json', '{}')->willReturn('{"name":"John"}');
        $array->expects($this->once())->method('anonymizePayloadWithReport')->with('p', 'json', '{}')->willReturn(new PayloadAnonymizationResult('{}', new AnonymizationResult([], [], [])));
        $objectCore = $this->createMock(AnonymizerInterface::class);
        $objectCore->expects($this->exactly(2))->method('anonymizeWithReport')->willReturn(new AnonymizationResult(['name' => 'John'], [], []));
        $object = new FacadePerson('Jane');
        $facade = new SymfonyAnonymizer($array, new SymfonyObjectAnonymizer($objectCore, new Serializer([new ObjectNormalizer()])));

        self::assertSame(['name' => 'John'], $facade->anonymize('p', ['name' => 'Jane']));
        $anonymized = $facade->anonymize('p', $object);
        self::assertInstanceOf(FacadePerson::class, $anonymized);
        self::assertSame('John', $anonymized->name);
        self::assertInstanceOf(AnonymizationResult::class, $facade->anonymizeWithReport('p', ['name' => 'Jane']));
        self::assertInstanceOf(ObjectAnonymizationResult::class, $facade->anonymizeWithReport('p', $object));
        self::assertSame('{"name":"John"}', $facade->anonymizePayload('p', 'json', '{}'));
        self::assertSame('{}', $facade->anonymizePayloadWithReport('p', 'json', '{}')->payload());
    }
}

final class FacadePerson
{
    public function __construct(public string $name) {}
}
