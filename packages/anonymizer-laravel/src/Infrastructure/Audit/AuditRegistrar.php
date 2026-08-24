<?php

declare(strict_types=1);

/** @codeCoverageIgnoreFile */

namespace PhpAnonymizer\AnonymizerLaravel\Infrastructure\Audit;

// Coverage rationale: Laravel framework wiring is covered by Testbench integration tests.
// @codeCoverageIgnoreStart

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

/**
 * @internal Laravel audit service registrar
 */
final readonly class AuditRegistrar
{
    /**
     * @param callable(string, mixed): mixed $configuration
     */
    public function register(Container $app, callable $configuration): void
    {
        if (!(bool) $configuration('anonymizer.audit.enabled', false)) {
            return;
        }
        $logger = $configuration('anonymizer.audit.logger_service', null);
        if (!is_string($logger) || $logger === '') {
            throw new InvalidArgumentException('anonymizer.audit.logger_service is required when audit is enabled.');
        }
        $app->singleton(AuditLoggerAdapter::class, static function (Container $app) use ($logger): AuditLoggerAdapter {
            $loggerInstance = Assert::isInstanceOf($app->make($logger), LoggerInterface::class, sprintf('Audit logger service "%s" must implement %s.', $logger, LoggerInterface::class));

            return new AuditLoggerAdapter($app->make(AnonymizerInterface::class), $loggerInstance);
        });
    }
}
// @codeCoverageIgnoreEnd
