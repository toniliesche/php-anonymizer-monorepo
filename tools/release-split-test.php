<?php

declare(strict_types=1);

use PhpAnonymizer\Release\ReleaseSplitPlan;
use PhpAnonymizer\Release\ReleaseSplitPlanner;
use PhpAnonymizer\Release\ReleaseTestFixture;
use PhpAnonymizer\Release\ReleaseTrain;

require_once __DIR__ . '/Release/ReleaseTrain.php';

require_once __DIR__ . '/Release/ReleaseReadiness.php';

require_once __DIR__ . '/Release/ReleaseTestFixture.php';

require_once __DIR__ . '/Release/ReleaseSplitPlan.php';

require_once __DIR__ . '/Release/ReleaseSplitPlanner.php';

$fixture = new ReleaseTestFixture();

try {
    $repository = $fixture->createMonorepo();
    $train = new ReleaseTrain();
    $planner = new ReleaseSplitPlanner();

    runGit($repository, ['checkout', '-b', 'unmerged-fixture']);
    file_put_contents($repository . '/packages/anonymizer/src/Unmerged.php', "<?php\n");
    runGit($repository, ['add', 'packages/anonymizer/src/Unmerged.php']);
    runGit($repository, ['commit', '-m', 'Add unmerged fixture change']);
    $unmergedSha = runGit($repository, ['rev-parse', 'HEAD']);
    assertSplitFailure(static fn (): ReleaseSplitPlan => $planner->plan('2.0.0', 'release/2.0.x', 'main', $unmergedSha, $repository));
    runGit($repository, ['checkout', 'main']);

    runGit($repository, ['checkout', 'release/2.0.x']);
    file_put_contents($repository . '/packages/anonymizer/src/Released.php', "<?php\n");
    runGit($repository, ['add', 'packages/anonymizer/src/Released.php']);
    runGit($repository, ['commit', '-m', 'Add release fixture change']);
    runGit($repository, ['checkout', 'main']);
    runGit($repository, ['merge', '--no-ff', 'release/2.0.x', '-m', 'Merge release fixture']);
    $sourceSha = runGit($repository, ['rev-parse', 'HEAD']);

    $remote = $fixture->createBareDestination('anonymizer-bundle');
    runGit($repository, ['remote', 'add', 'fixture', $remote]);
    $plan = $planner->plan('2.0.0', 'release/2.0.x', 'main', $sourceSha, $repository);
    if (array_keys($plan->packages) !== $train->packages()) {
        throw new RuntimeException('Release split plan is incomplete or out of release-train order.');
    }
    if ($plan->toArray()['release']['source_sha'] !== $sourceSha) {
        throw new RuntimeException('Release split plan did not preserve the requested source SHA.');
    }

    foreach ($train->packages() as $package) {
        $entry = $plan->packages[$package];
        $composer = json_decode(runGit($repository, ['show', $entry['split_sha'] . ':composer.json']), true, 512, JSON_THROW_ON_ERROR);
        if (($composer['name'] ?? null) !== $train->composerNameFor($package)
            || $entry['repository'] !== $train->repositoryFor($package)
            || $entry['publication_branch'] !== '2.x'
            || preg_match('/^[a-f0-9]{40}$/', $entry['split_sha']) !== 1
        ) {
            throw new RuntimeException('Generated package split has incorrect root metadata: ' . $package);
        }
    }

    $repeatedPlan = $planner->plan('2.0.0', 'release/2.0.x', 'main', $sourceSha, $repository);
    if ($repeatedPlan->toArray() !== $plan->toArray()) {
        throw new RuntimeException('Repeated split planning did not return deterministic SHAs.');
    }
    if (runGit($remote, ['rev-parse', '--all']) !== '') {
        throw new RuntimeException('Split planning wrote refs to a package destination.');
    }
    if (runGit($repository, ['status', '--porcelain']) !== '' || substr_count(runGit($repository, ['worktree', 'list', '--porcelain']), 'worktree ') !== 1) {
        throw new RuntimeException('Split planning left a dirty checkout or a temporary worktree.');
    }

    runGit($repository, ['checkout', 'release/2.0.x']);
    $manifestPath = $repository . '/packages/anonymizer-bundle/composer.json';
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $manifest['name'] = 'php-anonymizer/incorrect-name';
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    runGit($repository, ['add', 'packages/anonymizer-bundle/composer.json']);
    runGit($repository, ['commit', '-m', 'Break package fixture metadata']);
    runGit($repository, ['checkout', 'main']);
    runGit($repository, ['merge', '--no-ff', 'release/2.0.x', '-m', 'Merge invalid package fixture']);
    $invalidSourceSha = runGit($repository, ['rev-parse', 'HEAD']);
    assertSplitFailure(static fn (): ReleaseSplitPlan => $planner->plan('2.0.0', 'release/2.0.x', 'main', $invalidSourceSha, $repository));
    assertSplitFailure(static fn (): ReleaseSplitPlan => $planner->plan('2.0.0', 'release/2.1.x', 'main', $sourceSha, $repository));

    echo 'Release split planning passed for ' . count($train->packages()) . " packages.\n";
} finally {
    $fixture->cleanup();
}

/**
 * @param list<string> $arguments
 */
function runGit(string $repository, array $arguments): string
{
    return runFixtureCommand(['git', '-C', $repository, ...$arguments]);
}

/**
 * @param Closure(): mixed $operation
 */
function assertSplitFailure(Closure $operation): void
{
    try {
        $operation();
    } catch (InvalidArgumentException|RuntimeException) {
        return;
    }

    throw new RuntimeException('Invalid release split input was accepted.');
}

/**
 * @param list<string> $command
 */
function runFixtureCommand(array $command): string
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start a release split fixture command.');
    }
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Release split fixture command failed: ' . $output);
    }

    return trim($output);
}
