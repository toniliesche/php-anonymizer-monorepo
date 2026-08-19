<?php

declare(strict_types=1);

use PhpAnonymizer\Release\ReleasePlanner;

$root = dirname(__DIR__);
require_once __DIR__ . '/Release/ReleasePlanner.php';

$branch = getenv('GITHUB_HEAD_REF') ?: trim((string) shell_exec('git branch --show-current'));
$main = getenv('RELEASE_MAIN_BRANCH') ?: 'main';
$dryRun = in_array('--dry-run', $argv, true) || getenv('RELEASE_DRY_RUN') === '1';
try {
    $plan = new ReleasePlanner()->plan($branch, $main, $root);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(2);
}

$actions = [
    'annotated tag ' . $plan['version'],
    'build deterministic package ZIP and TAR.GZ archives',
    'publish GitHub release ' . $plan['version'],
    'open or reuse main-to-develop synchronization PR',
];
printf("Release %s (%s)\n", $plan['version'], $dryRun ? 'dry-run' : 'workflow');
foreach ($actions as $action) {
    printf("- %s\n", $action);
}
if (!$dryRun) {
    fwrite(STDERR, "Publishing is performed by the GitHub Actions release workflow; use --dry-run locally.\n");
    exit(2);
}
