<?php

declare(strict_types=1);

use PhpAnonymizer\Release\GitPackagePublisher;
use PhpAnonymizer\Release\ReleasePublicationPlan;
use PhpAnonymizer\Release\ReleaseSplitPlan;
use PhpAnonymizer\Release\ReleaseTestFixture;
use PhpAnonymizer\Release\ReleaseTrain;

require_once __DIR__ . '/Release/ReleaseTrain.php';

require_once __DIR__ . '/Release/ReleaseReadiness.php';

require_once __DIR__ . '/Release/ReleaseTestFixture.php';

require_once __DIR__ . '/Release/ReleaseSplitPlan.php';

require_once __DIR__ . '/Release/ReleasePublicationPlan.php';

require_once __DIR__ . '/Release/GitPackagePublisher.php';

$fixture = new ReleaseTestFixture();

try {
    $root = $fixture->createMonorepo();
    $train = new ReleaseTrain();
    $baseSha = runPublishFixtureCommand(['git', '-C', $root, 'rev-parse', 'HEAD']);
    $splitShas = [];
    foreach ($train->packages() as $package) {
        $tree = runPublishFixtureCommand(['git', '-C', $root, 'rev-parse', $baseSha . ':packages/' . $package]);
        $splitShas[$package] = runPublishFixtureCommand(['git', '-C', $root, 'commit-tree', $tree, '-m', 'Split fixture ' . $package]);
    }

    $legacy = $fixture->createLegacyCore();
    $destinations = ['anonymizer' => $legacy['remote']];
    $overrides = ['anonymizer' => $legacy['remote']];
    foreach (array_slice($train->packages(), 1) as $package) {
        $destination = $fixture->createBareDestination('destination-' . $package);
        $destinations[$package] = $destination;
        $overrides[$package] = $destination;
    }

    $legacyMainSha = runPublishFixtureCommand(['git', '--git-dir', $legacy['remote'], 'rev-parse', 'refs/heads/main']);
    $splitPlan = makePublishPlan('2.0.0', $baseSha, $train, $splitShas);
    $publisher = new GitPackagePublisher(repositoryOverrides: $overrides);
    $preflight = $publisher->preflight($splitPlan, $root);
    $published = $publisher->publish($preflight, $root);
    foreach ($train->packages() as $package) {
        if ($published[$package] !== 'published') {
            throw new RuntimeException('Expected the initial release to publish ' . $package);
        }
        $refs = remoteRefs($destinations[$package]);
        if (($refs['refs/heads/2.x'] ?? null) !== $splitShas[$package]
            || ($refs['refs/tags/2.0.0^{}'] ?? $refs['refs/tags/2.0.0'] ?? null) !== $splitShas[$package]
        ) {
            throw new RuntimeException('Branch and annotated version tag do not identify the same split for ' . $package);
        }
    }
    if (runPublishFixtureCommand(['git', '--git-dir', $legacy['remote'], 'rev-parse', 'refs/heads/main']) !== $legacyMainSha) {
        throw new RuntimeException('Publishing replaced the legacy core main branch.');
    }
    $legacyTags = preg_split('/\R+/', trim(runPublishFixtureCommand(['git', '--git-dir', $legacy['remote'], 'tag', '--list']))) ?: [];
    if ($legacyTags !== [...$legacy['tags'], '2.0.0']) {
        throw new RuntimeException('Publishing did not preserve the existing core tags.');
    }

    $retry = $publisher->preflight($splitPlan, $root);
    foreach ($retry->packages as $package) {
        if ($package['push_branch'] || $package['push_tag']) {
            throw new RuntimeException('Matching published refs were not recognized as a safe retry.');
        }
    }
    $retryResults = $publisher->publish($retry, $root);
    if (count(array_filter($retryResults, static fn (string $result): bool => $result !== 'already_published')) !== 0) {
        throw new RuntimeException('Retry attempted to republish matching refs.');
    }

    $partialPackage = 'anonymizer-faker';
    runPublishFixtureCommand(['git', '--git-dir', $destinations[$partialPackage], 'update-ref', '-d', 'refs/heads/2.x']);
    $partial = $publisher->preflight($splitPlan, $root);
    if (!$partial->packages[$partialPackage]['push_branch'] || $partial->packages[$partialPackage]['push_tag']) {
        throw new RuntimeException('Partial publication was not planned as a branch-only retry.');
    }
    $publisher->publish($partial, $root);
    if ((remoteRefs($destinations[$partialPackage])['refs/heads/2.x'] ?? null) !== $splitShas[$partialPackage]) {
        throw new RuntimeException('Partial publication did not restore the missing branch.');
    }

    $nextSplitShas = [];
    foreach ($train->packages() as $package) {
        $tree = runPublishFixtureCommand(['git', '-C', $root, 'rev-parse', $baseSha . ':packages/' . $package]);
        $nextSplitShas[$package] = runPublishFixtureCommand(['git', '-C', $root, 'commit-tree', $tree, '-p', $splitShas[$package], '-m', 'Split fixture ' . $package . ' for 2.0.1']);
    }
    $nextSplitPlan = makePublishPlan('2.0.1', $baseSha, $train, $nextSplitShas);
    $nextPreflight = $publisher->preflight($nextSplitPlan, $root);
    foreach ($nextPreflight->packages as $package => $entry) {
        if (!$entry['push_branch'] || !$entry['push_tag']) {
            throw new RuntimeException('Fast-forward release refs were not planned for publication: ' . $package);
        }
    }
    $publisher->publish($nextPreflight, $root);
    foreach ($train->packages() as $package) {
        $refs = remoteRefs($destinations[$package]);
        if (($refs['refs/heads/2.x'] ?? null) !== $nextSplitShas[$package]
            || ($refs['refs/tags/2.0.1^{}'] ?? $refs['refs/tags/2.0.1'] ?? null) !== $nextSplitShas[$package]
        ) {
            throw new RuntimeException('Fast-forward release was not published for ' . $package);
        }
    }

    $conflictDestinations = [];
    $conflictOverrides = [];
    foreach ($train->packages() as $package) {
        $destination = $fixture->createBareDestination('conflict-' . $package);
        $conflictDestinations[$package] = $destination;
        $conflictOverrides[$package] = $destination;
    }
    $conflictPackage = 'anonymizer-symfony-serializer';
    runPublishFixtureCommand(['git', '--git-dir', $conflictDestinations[$conflictPackage], 'fetch', '--quiet', $root, $splitShas['anonymizer-faker']]);
    runPublishFixtureCommand(['git', '--git-dir', $conflictDestinations[$conflictPackage], 'update-ref', 'refs/tags/2.0.0', $splitShas['anonymizer-faker']]);
    $conflictPublisher = new GitPackagePublisher(repositoryOverrides: $conflictOverrides);
    assertPublishFailure(static fn (): ReleasePublicationPlan => $conflictPublisher->preflight($splitPlan, $root));
    foreach ($conflictDestinations as $package => $destination) {
        if ($package !== $conflictPackage && remoteRefs($destination) !== []) {
            throw new RuntimeException('A conflict in the last destination did not block all package writes.');
        }
    }

    $regressionDestinations = [];
    $regressionOverrides = [];
    foreach ($train->packages() as $package) {
        $destination = $fixture->createBareDestination('regression-' . $package);
        $regressionDestinations[$package] = $destination;
        $regressionOverrides[$package] = $destination;
    }
    $regressionPackage = 'anonymizer-faker';
    runPublishFixtureCommand(['git', '--git-dir', $regressionDestinations[$regressionPackage], 'fetch', '--quiet', $root, $splitShas['anonymizer-personas']]);
    runPublishFixtureCommand(['git', '--git-dir', $regressionDestinations[$regressionPackage], 'update-ref', 'refs/heads/2.x', $splitShas['anonymizer-personas']]);
    $regressionPublisher = new GitPackagePublisher(repositoryOverrides: $regressionOverrides);
    assertPublishFailure(static fn (): ReleasePublicationPlan => $regressionPublisher->preflight($splitPlan, $root));
    foreach ($regressionDestinations as $package => $destination) {
        if ($package !== $regressionPackage && remoteRefs($destination) !== []) {
            throw new RuntimeException('A branch regression did not block all package writes.');
        }
    }

    echo 'Package publication passed for ' . count($train->packages()) . " packages, including safe retries and preflight conflicts.\n";
} finally {
    $fixture->cleanup();
}

/**
 * @param array<string, string> $splitShas
 */
function makePublishPlan(string $version, string $sourceSha, ReleaseTrain $train, array $splitShas): ReleaseSplitPlan
{
    $packages = [];
    foreach ($train->packages() as $package) {
        $packages[$package] = [
            'composer_name' => $train->composerNameFor($package),
            'repository' => $train->repositoryFor($package),
            'publication_branch' => $train->publicationBranchFor($version),
            'split_sha' => $splitShas[$package],
        ];
    }

    return new ReleaseSplitPlan($version, 'release/2.0.x', 'main', $sourceSha, $packages);
}

/**
 * @return array<string, string>
 */
function remoteRefs(string $repository): array
{
    $output = runPublishFixtureCommand(['git', 'ls-remote', '--heads', '--tags', $repository]);
    $refs = [];
    foreach (preg_split('/\R+/', $output) ?: [] as $line) {
        if (preg_match('/^([a-f0-9]{40}|[a-f0-9]{64})\s+(\S+)$/', $line, $matches) === 1) {
            $refs[$matches[2]] = $matches[1];
        }
    }

    return $refs;
}

/**
 * @param Closure(): mixed $operation
 */
function assertPublishFailure(Closure $operation): void
{
    try {
        $operation();
    } catch (InvalidArgumentException|RuntimeException) {
        return;
    }

    throw new RuntimeException('An unsafe publication destination was accepted.');
}

/**
 * @param list<string> $command
 */
function runPublishFixtureCommand(array $command): string
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start a package publication fixture command.');
    }
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Package publication fixture command failed: ' . $output);
    }

    return trim($output);
}
