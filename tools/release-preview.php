<?php

declare(strict_types=1);

$branch = $argv[1] ?? getenv('GITHUB_HEAD_REF') ?: trim((string) shell_exec('git branch --show-current'));
$main = $argv[2] ?? getenv('RELEASE_MAIN_BRANCH') ?: 'main';

if (preg_match('/^release\/(\d+)\.(\d+)\.x$/', $branch, $matches) !== 1) {
    fwrite(STDERR, "Release preview requires a release/X.Y.x branch; received: {$branch}\n");
    exit(2);
}

$prefix = $matches[1] . '.' . $matches[2] . '.';
exec(sprintf('git rev-parse --verify %s', escapeshellarg($main)), $refOutput, $refStatus);
if ($refStatus !== 0) {
    fwrite(STDERR, sprintf("Release preview cannot find the main branch ref: %s\n", $main));
    exit(2);
}

$tags = preg_split('/\R+/', trim((string) shell_exec(sprintf('git tag --merged %s --list %s', escapeshellarg($main), escapeshellarg($prefix . '*'))))) ?: [];
$stable = array_values(array_filter($tags, static fn(string $tag): bool => preg_match('/^\d+\.\d+\.\d+$/', $tag) === 1));
usort($stable, version_compare(...));
$nextPatch = $stable === [] ? 0 : ((int) explode('.', array_last($stable))[2]) + 1;
$version = $prefix . $nextPatch;

printf("release branch: %s\nmain branch: %s\nmatching stable tags: %s\nnext version: %s\n", $branch, $main, $stable === [] ? '(none)' : implode(', ', $stable), $version);
