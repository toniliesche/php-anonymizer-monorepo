<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerLaravel\Infrastructure\Audit;

use PhpAnonymizer\Anonymizer\Application\Anonymization\AuditAnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AuditAnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AuditEntry;
use Psr\Log\LoggerInterface;

final readonly class AuditLoggerAdapter
{
    public function __construct(private AuditAnonymizerInterface $anonymizer, private LoggerInterface $logger)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function anonymizeWithAudit(string $profile, array $data): AuditAnonymizationResult
    {
        $result = $this->anonymizer->anonymizeWithAudit($profile, $data);
        $this->logger->info('Anonymization audit', ['profile' => $profile, 'entries' => array_map(static fn (AuditEntry $entry): array => [
            'fingerprint' => $entry->fingerprint(), 'path' => $entry->path(), 'action' => $entry->action(),
            'handler' => $entry->handler(), 'count' => $entry->count(),
        ], $result->entries())]);

        return $result;
    }
}
