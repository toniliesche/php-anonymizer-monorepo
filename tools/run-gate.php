<?php

declare(strict_types=1);

use PhpAnonymizer\CI\GateRunner;

require_once __DIR__ . '/CI/GateRunner.php';

$root = dirname(__DIR__);
$name = $argv[1] ?? '';
$command = array_slice($argv, 2);

try {
    if ($name === '' || $command === []) {
        throw new RuntimeException('Usage: php tools/run-gate.php <gate-name> <command> [arguments...]');
    }
    $result = new GateRunner()->run($name, $command, $root);
    printf("Gate diagnostics: %s\n", $result['directory']);

    exit($result['exit_code']);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");

    exit(2);
}
