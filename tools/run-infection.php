<?php

declare(strict_types=1);

putenv('PHP_VERSION=' . (getenv('PHP_VERSION') ?: '8.5'));
$root = dirname(__DIR__);
$phpOptions = extension_loaded('xdebug')
    ? '-d xdebug.mode=coverage'
    : '-dzend_extension=xdebug.so -d xdebug.mode=coverage';
$command = sprintf(
    '%s %s --configuration=%s --min-covered-msi=95 --initial-tests-php-options=%s --show-mutations=max',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($root . '/vendor/bin/infection'),
    escapeshellarg($root . '/infection.json5'),
    escapeshellarg($phpOptions),
);

passthru($command, $exitCode);

exit($exitCode);
