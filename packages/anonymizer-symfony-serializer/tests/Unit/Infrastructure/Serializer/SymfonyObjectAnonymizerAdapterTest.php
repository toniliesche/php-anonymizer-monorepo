<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerSymfonySerializer\Tests\Unit\Infrastructure\Serializer;

use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationContext;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ObjectAnonymizationRequest;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AnonymizationResult;
use PhpAnonymizer\AnonymizerSymfonySerializer\Application\Anonymization\Result\ObjectAnonymizationResult;
use PhpAnonymizer\AnonymizerSymfonySerializer\Infrastructure\Serializer\SymfonyObjectAnonymizerAdapter;
use PhpAnonymizer\AnonymizerSymfonySerializer\Tests\Support\Serializer\AdapterPerson;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

#[CoversClass(SymfonyObjectAnonymizerAdapter::class)]
#[CoversClass(ObjectAnonymizationResult::class)]
#[CoversClass(AnonymizationResult::class)]
#[UsesClass(AnonymizationContext::class)]
final class SymfonyObjectAnonymizerAdapterTest extends TestCase
{
    public function testAnonymizesObjectsThroughTheBuilderObjectPort(): void
    {
        $anonymizer = $this->createMock(AnonymizerInterface::class);
        $anonymizer->expects($this->once())
            ->method('anonymizeWithReport')
            ->with('customer', ['name' => 'Jane'])
            ->willReturn(new AnonymizationResult(['name' => 'John'], ['/name'], []));

        $result = new SymfonyObjectAnonymizerAdapter(new Serializer([new ObjectNormalizer()]))->anonymize(
            new ObjectAnonymizationRequest('customer', new AdapterPerson('Jane')),
            $anonymizer,
        );

        self::assertInstanceOf(ObjectAnonymizationResult::class, $result);
        self::assertInstanceOf(AdapterPerson::class, $result->data());
        self::assertSame('John', $result->data()->name);
        self::assertSame($result->data(), $result->object());
        self::assertSame(['/name'], $result->anonymizedPaths());
        self::assertSame([], $result->missingPaths());
    }
}
