<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerPseudonymization\Infrastructure\Replacement;

use LogicException;
use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ContextualReplacementStrategyInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ReplacementContext;
use PhpAnonymizer\AnonymizerPseudonymization\Application\PseudonymKeyProviderInterface;
use PhpAnonymizer\AnonymizerPseudonymization\Application\PseudonymKeyUnavailable;
use Throwable;

final readonly class HmacPseudonymStrategy implements ContextualReplacementStrategyInterface
{
    public function __construct(private PseudonymKeyProviderInterface $keys) {}

    #[Override]
    public function id(): string
    {
        return 'pseudonymize';
    }

    #[Override]
    public function replace(string $value): string
    {
        throw new LogicException('The pseudonymize strategy requires the contextual anonymizer API.');
    }

    #[Override]
    public function replaceWithContext(string $value, ReplacementContext $context): string
    {
        try {
            $key = $this->keys->keyFor($context->keyId());
        } catch (Throwable) {
            throw PseudonymKeyUnavailable::forId($context->keyId());
        }
        if ($key === '') {
            throw PseudonymKeyUnavailable::forId($context->keyId());
        }

        $message = $this->message($value, $context);
        $digest = hash_hmac('sha256', $message, $key, true);
        return rtrim(strtr(base64_encode($digest), '+/', '-_'), '=');
    }

    private function message(string $value, ReplacementContext $context): string
    {
        $parts = ['php-anonymizer/pseudonym/v1', $context->keyId(), $context->profile(), (string) $context->path()];
        foreach ($context->attributes() as $name => $attribute) {
            $parts[] = $name;
            $parts[] = $attribute;
        }
        $parts[] = $value;
        $message = '';
        foreach ($parts as $part) {
            $message .= pack('N', mb_strlen($part, 'UTF-8')) . $part;
        }
        return $message;
    }
}
