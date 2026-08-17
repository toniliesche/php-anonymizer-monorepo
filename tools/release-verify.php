<?php

declare(strict_types=1);

if (getenv('CI') !== 'true' && getenv('GITHUB_ACTIONS') !== 'true') {
    fwrite(STDERR, "release:verify is intended for a release workflow or CI environment.\n");
    exit(2);
}

passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/release-preview.php'), $exitCode);
exit($exitCode);
