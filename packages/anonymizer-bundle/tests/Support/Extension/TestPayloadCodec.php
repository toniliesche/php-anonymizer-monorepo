<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Support\Extension;

use Override;
use PhpAnonymizer\Anonymizer\Application\Extension\Payload\PayloadCodecInterface;
use UnexpectedValueException;

final class TestPayloadCodec implements PayloadCodecInterface
{
    #[Override]
    public function id(): string
    {
        return 'pipe';
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function decode(string $payload): array
    {
        return ['email' => $payload];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Override]
    public function encode(array $data): string
    {
        $email = $data['email'] ?? null;
        if (!is_string($email)) {
            throw new UnexpectedValueException('Expected an email string.');
        }

        return $email;
    }
}
