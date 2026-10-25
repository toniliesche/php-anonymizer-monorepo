<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerBundle\Tests\Unit\Infrastructure\Audit;

use PhpAnonymizer\Anonymizer\Application\Anonymization\AuditAnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AuditAnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AuditEntry;
use PhpAnonymizer\AnonymizerBundle\Infrastructure\Audit\AuditLoggerAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(AuditLoggerAdapter::class)]
#[CoversClass(AuditAnonymizationResult::class)]
#[CoversClass(AuditEntry::class)]
final class AuditLoggerAdapterTest extends TestCase
{
    public function testLogsSafeAggregateAudit(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('Anonymization audit', [
            'profile' => 'customer',
            'entries' => [[
                'fingerprint' => 'fingerprint',
                'path' => '/email',
                'action' => 'replace',
                'handler' => 'redact',
                'count' => 1,
            ]],
        ]);
        $anonymizer = $this->createMock(AuditAnonymizerInterface::class);
        $anonymizer->expects($this->once())->method('anonymizeWithAudit')->with('customer', ['email' => 'secret'])->willReturn(new AuditAnonymizationResult(
            ['email' => '[REDACTED]'],
            [new AuditEntry('customer', 'fingerprint', '/email', 'replace', 'redact', 1)],
        ));
        $adapter = new AuditLoggerAdapter($anonymizer, $logger);

        self::assertSame('[REDACTED]', $adapter->anonymizeWithAudit('customer', ['email' => 'secret'])->data()['email']);
    }
}
