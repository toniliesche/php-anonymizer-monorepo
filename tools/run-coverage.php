<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('PHP_VERSION=' . (getenv('PHP_VERSION') ?: '8.5'));
$php = escapeshellarg(PHP_BINARY);
$phpOptions = extension_loaded('xdebug')
    ? '-d xdebug.mode=coverage'
    : '-dzend_extension=xdebug.so -d xdebug.mode=coverage';
$phpOptions .= ' -d opcache.enable_cli=0 -d opcache.jit=0 -d opcache.jit_buffer_size=0';
$phpunit = escapeshellarg($root . '/vendor/bin/phpunit');
$arguments = implode(' ', array_map(escapeshellarg(...), array_slice($argv, 1)));
$command = sprintf('%s %s %s --configuration=%s --coverage-text --coverage-cobertura=%s %s', $php, $phpOptions, $phpunit, escapeshellarg($root . '/phpunit.xml'), escapeshellarg($root . '/var/coverage.xml'), $arguments);

passthru($command, $exitCode);

exit($exitCode);
