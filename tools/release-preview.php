<?php

declare(strict_types=1);

use PhpAnonymizer\Release\ReleasePlanner;

$root = $argv[3] ?? dirname(__DIR__);
require_once __DIR__ . '/Release/ReleasePlanner.php';

$planner = new ReleasePlanner();
$branch = $argv[1] ?? getenv('GITHUB_HEAD_REF') ?: trim((string) shell_exec('git branch --show-current'));
$main = $argv[2] ?? getenv('RELEASE_MAIN_BRANCH') ?: 'main';

try {
    $plan = $planner->plan($branch, $main, $root);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(2);
}

printf("release branch: %s\nmain branch: %s\nmatching stable tags: %s\nnext version: %s\n", $plan['branch'], $plan['main'], $plan['tags'] === [] ? '(none)' : implode(', ', $plan['tags']), $plan['version']);
