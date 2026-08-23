<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerLaravel\Infrastructure\Payload;

use Override;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\DocumentPayloadCodecInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

// @internal
final class YamlPayloadCodec implements DocumentPayloadCodecInterface
{
    #[Override]
    public function id(): string
    {
        return 'yaml';
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function decode(string $payload): array
    {
        $decoded = Yaml::parse($payload, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new ParseException('YAML payload root must be a mapping.');
        }
        foreach (array_keys($decoded) as $key) {
            if (!is_string($key)) {
                throw new ParseException('YAML payload mapping keys must be strings.'); // @codeCoverageIgnore
            }
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<mixed, mixed>
     */
    #[Override]
    public function decodeDocument(string $payload): array
    {
        $decoded = Yaml::parse($payload, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        if (!is_array($decoded)) {
            throw new ParseException('YAML document root must be a mapping or sequence.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Override]
    public function encode(array $data): string
    {
        return Yaml::dump($data, 4, 2);
    }

    /**
     * @param array<mixed, mixed> $data
     */
    #[Override]
    public function encodeDocument(array $data): string
    {
        return Yaml::dump($data, 4, 2);
    }
}
