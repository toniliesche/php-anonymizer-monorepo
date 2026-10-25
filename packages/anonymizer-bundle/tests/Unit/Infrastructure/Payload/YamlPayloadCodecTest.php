<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Unit\Infrastructure\Payload;

use PhpAnonymizer\AnonymizerBundle\Infrastructure\Payload\YamlPayloadCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Exception\ParseException;

#[CoversClass(YamlPayloadCodec::class)]
final class YamlPayloadCodecTest extends TestCase
{
    public function testRoundTripsMappings(): void
    {
        $codec = new YamlPayloadCodec();
        $data = ['email' => 'hello', 'nested' => ['active' => true]];

        self::assertSame($data, $codec->decode($codec->encode($data)));
    }

    public function testRejectsNonMappingRoots(): void
    {
        $this->expectExceptionObject(new ParseException('YAML payload root must be a mapping.'));
        new YamlPayloadCodec()->decode('- value');
    }

    public function testAcceptsEmptyMappingAndPreservesConfiguredIndentation(): void
    {
        $codec = new YamlPayloadCodec();
        self::assertSame([], $codec->decode('{}'));
        self::assertStringContainsString("nested:\n  value: true", $codec->encode(['nested' => ['value' => true]]));
        self::assertSame("nested:\n  value: true\n", $codec->encode(['nested' => ['value' => true]]));
        self::assertSame("one:\n  two:\n    three:\n      four: { leaf: value }\n", $codec->encode([
            'one' => ['two' => ['three' => ['four' => ['leaf' => 'value']]]],
        ]));
    }

    public function testRejectsScalarAndMalformedRoots(): void
    {
        $codec = new YamlPayloadCodec();
        try {
            $codec->decode('value');
            self::fail('A scalar root must be rejected.');
        } catch (ParseException $exception) {
            self::assertSame('YAML payload root must be a mapping.', $exception->getMessage());
        }

        $this->expectException(ParseException::class);
        $codec->decode('key: [unterminated');
    }

    public function testRejectsNonStringMappingKeys(): void
    {
        $this->expectException(ParseException::class);
        new YamlPayloadCodec()->decode("1: value\n");
    }
}
