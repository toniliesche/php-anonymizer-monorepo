<?php

declare(strict_types=1);

/** @codeCoverageIgnoreFile Framework wiring. */

namespace PhpAnonymizer\AnonymizerLaravel\Infrastructure\Audit;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal Laravel audit service registrar
 *
 * @codeCoverageIgnore Framework wiring.
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
            /** @var LoggerInterface $loggerInstance */
            $loggerInstance = $app->make($logger);

            return new AuditLoggerAdapter($app->make(AnonymizerInterface::class), $loggerInstance);
        });
    }
}
