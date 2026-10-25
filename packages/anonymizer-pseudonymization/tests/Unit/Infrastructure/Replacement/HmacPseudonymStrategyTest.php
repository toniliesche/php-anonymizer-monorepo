<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerPseudonymization\Tests\Unit\Infrastructure\Replacement;

use LogicException;
use Override;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizationContext;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Path\JsonPointer;
use PhpAnonymizer\Anonymizer\Application\Anonymization\ReplacementContext;
use PhpAnonymizer\AnonymizerPseudonymization\Application\PseudonymKeyProviderInterface;
use PhpAnonymizer\AnonymizerPseudonymization\Application\PseudonymKeyUnavailable;
use PhpAnonymizer\AnonymizerPseudonymization\Infrastructure\Replacement\HmacPseudonymStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(HmacPseudonymStrategy::class)]
#[CoversClass(PseudonymKeyUnavailable::class)]
#[UsesClass(AnonymizationContext::class)]
#[UsesClass(JsonPointer::class)]
#[UsesClass(ReplacementContext::class)]
final class HmacPseudonymStrategyTest extends TestCase
{
    public function testOutputIsDeterministicAndScopeSensitive(): void
    {
        $strategy = new HmacPseudonymStrategy(new class () implements PseudonymKeyProviderInterface {
            #[Override]
            public function keyFor(string $keyId): string
            {
                return 'secret-' . $keyId;
            }
        });
        $context = new ReplacementContext('customer', JsonPointer::fromString('/email'), 'primary', new AnonymizationContext(['scope' => 'billing', 'tenant' => 'acme']));
        self::assertSame('pseudonymize', $strategy->id());
        $same = $strategy->replaceWithContext('Jörg@example.test', $context);
        self::assertSame($same, $strategy->replaceWithContext('Jörg@example.test', $context));
        self::assertSame(43, mb_strlen($same, 'UTF-8'));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $same);
        self::assertNotSame($same, $strategy->replaceWithContext('Jörg@example.test', new ReplacementContext('customer', JsonPointer::fromString('/name'), 'primary', new AnonymizationContext(['scope' => 'billing', 'tenant' => 'acme']))));
    }

    public function testProviderFailuresDoNotExposeKeyMaterial(): void
    {
        $strategy = new HmacPseudonymStrategy(new class () implements PseudonymKeyProviderInterface {
            #[Override]
            public function keyFor(string $keyId): string
            {
                throw new RuntimeException('secret-key-material');
            }
        });

        try {
            $strategy->replaceWithContext('value', new ReplacementContext('customer', JsonPointer::root(), 'primary', new AnonymizationContext()));
            self::fail('Expected key failure.');
        } catch (PseudonymKeyUnavailable $exception) {
            self::assertStringContainsString('primary', $exception->getMessage());
            self::assertStringNotContainsString('secret-key-material', $exception->getMessage());
        }
    }

    public function testEmptyKeyAndNonContextualCallAreRejected(): void
    {
        $strategy = new HmacPseudonymStrategy(new class () implements PseudonymKeyProviderInterface {
            #[Override]
            public function keyFor(string $keyId): string
            {
                return '';
            }
        });
        $this->expectException(PseudonymKeyUnavailable::class);
        $strategy->replaceWithContext('value', new ReplacementContext('customer', JsonPointer::root(), 'primary', new AnonymizationContext()));
    }

    public function testPlainReplacementCallIsRejected(): void
    {
        $strategy = new HmacPseudonymStrategy(new class () implements PseudonymKeyProviderInterface {
            #[Override]
            public function keyFor(string $keyId): string
            {
                return 'key';
            }
        });
        $this->expectException(LogicException::class);
        $strategy->replace('value');
    }
}
