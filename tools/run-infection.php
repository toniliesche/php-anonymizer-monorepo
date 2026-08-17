<?php

declare(strict_types=1);

putenv('PHP_VERSION=' . (getenv('PHP_VERSION') ?: '8.5'));
$root = dirname(__DIR__);
$command = sprintf(
    '%s %s --configuration=%s --min-covered-msi=90 --initial-tests-php-options=%s',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($root . '/vendor/bin/infection'),
    escapeshellarg($root . '/infection.json5'),
    escapeshellarg('-dzend_extension=xdebug.so -d xdebug.mode=coverage'),
);

passthru($command, $exitCode);
exit($exitCode);
