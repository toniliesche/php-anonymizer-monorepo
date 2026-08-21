<?php

declare(strict_types=1);

use PhpAnonymizer\Release\ReleasePlanner;

$root = dirname(__DIR__);

require_once __DIR__ . '/Release/ReleasePlanner.php';

if (getenv('CI') !== 'true' && getenv('GITHUB_ACTIONS') !== 'true') {
    fwrite(STDERR, "release:verify is intended for a release workflow or CI environment.\n");

    exit(2);
}
if (trim((string) shell_exec('git rev-parse --is-shallow-repository')) === 'true') {
    fwrite(STDERR, "Release verification requires full Git history.\n");

    exit(2);
}
if (trim((string) shell_exec('git status --porcelain')) !== '') {
    fwrite(STDERR, "Release verification requires a clean worktree.\n");

    exit(2);
}

$branch = getenv('GITHUB_HEAD_REF') ?: trim((string) shell_exec('git branch --show-current'));
$main = getenv('RELEASE_MAIN_BRANCH') ?: 'main';

try {
    $plan = new ReleasePlanner()->plan($branch, $main, $root);
    $existing = trim((string) shell_exec('git tag --list ' . escapeshellarg($plan['version'])));
    if ($existing !== '') {
        throw new RuntimeException('Release tag already exists: ' . $plan['version']);
    }
    printf("Release verification passed for %s.\n", $plan['version']);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");

    exit(2);
}
