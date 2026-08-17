<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$composer = getenv('COMPOSER_BINARY') ?: 'composer';
$packages = glob($root . '/packages/*/composer.json') ?: [];
sort($packages);

if ($packages === []) {
    fwrite(STDERR, "No package manifests found.\n");
    exit(2);
}

foreach ($packages as $manifest) {
    $packageDirectory = dirname($manifest);
    $relativeManifest = substr($manifest, strlen($root) + 1);
    $commands = [
        [$composer, 'normalize', '--dry-run', '--no-update-lock', '--no-check-lock', $relativeManifest],
        [$composer, 'validate', '--strict', '--no-check-lock', '--working-dir=' . $packageDirectory],
    ];

    foreach ($commands as $command) {
        $process = proc_open($command, [
            0 => ['file', 'php://stdin', 'r'],
            1 => ['file', 'php://stdout', 'w'],
            2 => ['file', 'php://stderr', 'w'],
        ], $pipes, $root);

        if (!is_resource($process)) {
            fwrite(STDERR, sprintf("Could not start Composer for %s.\n", $relativeManifest));
            exit(2);
        }

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            fwrite(STDERR, sprintf("Composer metadata check failed for %s.\n", $relativeManifest));
            exit($exitCode);
        }
    }
}

printf("Validated %d package manifests.\n", count($packages));
