<?php

declare(strict_types=1);

use PhpAnonymizer\CI\CheckoutVerifier;
use PhpAnonymizer\CI\GateRunner;

require_once __DIR__ . '/CI/CheckoutVerifier.php';

require_once __DIR__ . '/CI/GateRunner.php';

$temporary = sys_get_temp_dir() . '/php-anonymizer-ci-helper-test-' . bin2hex(random_bytes(8));
mkdir($temporary, 0o700);
$repository = $temporary . '/repository';
mkdir($repository, 0o700);

try {
    gitHelper($repository, ['init', '--initial-branch=main']);
    gitHelper($repository, ['config', 'user.email', 'ci-fixture@example.test']);
    gitHelper($repository, ['config', 'user.name', 'CI Fixture']);
    file_put_contents($repository . '/README.md', "fixture\n");
    gitHelper($repository, ['add', 'README.md']);
    gitHelper($repository, ['commit', '-m', 'main baseline']);
    $baseline = gitHelper($repository, ['rev-parse', 'HEAD']);
    gitHelper($repository, ['tag', '-a', '1.0.0', '-m', 'fixture tag']);
    $remote = $temporary . '/remote.git';
    gitHelper($temporary, ['init', '--bare', '--initial-branch=main', $remote]);
    gitHelper($repository, ['remote', 'add', 'origin', $remote]);
    gitHelper($repository, ['push', '--quiet', 'origin', 'main', '--tags']);
    gitHelper($repository, ['checkout', '-b', 'feature']);
    file_put_contents($repository . '/change.txt', "feature\n");
    gitHelper($repository, ['add', 'change.txt']);
    gitHelper($repository, ['commit', '-m', 'feature change']);
    $revision = gitHelper($repository, ['rev-parse', 'HEAD']);
    gitHelper($repository, ['push', '--quiet', 'origin', 'feature']);

    $verified = new CheckoutVerifier()->verify($repository, $revision);
    if ($verified['revision'] !== $revision || $verified['baseline'] !== $baseline || $verified['tags'] !== ['1.0.0']) {
        throw new RuntimeException('Checkout verifier returned incorrect revision, baseline, or tags.');
    }
    gitHelper($repository, ['checkout', '--detach', $baseline]);
    $staleEvent = new CheckoutVerifier()->verify($repository, $baseline, 'origin/feature');
    if ($staleEvent['baseline'] !== $revision) {
        throw new RuntimeException('Checkout verifier rejected a valid event revision behind the advanced baseline.');
    }

    try {
        new CheckoutVerifier()->verify($repository, str_repeat('0', 40));

        throw new RuntimeException('Checkout verifier accepted a mismatched event revision.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'does not match')) {
            throw $exception;
        }
    }

    mkdir($repository . '/var', 0o700);
    putenv('GITHUB_RUN_ID=fixture-run');
    putenv('CI_DIAGNOSTICS_DIR=' . $temporary . '/diagnostics');
    putenv('GITHUB_TOKEN=fixture-secret');
    $code = 'fwrite(STDOUT, "MSI: fixture value\n");fwrite(STDERR, getenv("GITHUB_TOKEN")."\n");file_put_contents("var/coverage.xml", "fixture coverage");exit(7);';
    $result = new GateRunner()->run('fixture', [PHP_BINARY, '-r', $code], $repository);
    putenv('GITHUB_RUN_ID');
    putenv('CI_DIAGNOSTICS_DIR');
    putenv('GITHUB_TOKEN');
    $log = (string) file_get_contents($result['directory'] . '/fixture.log');
    $summary = (string) file_get_contents($result['directory'] . '/mutation-summary.txt');
    $metadata = json_decode((string) file_get_contents($result['directory'] . '/metadata.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($result['exit_code'] !== 7 || $metadata['exit_code'] !== 7 || $metadata['outcome'] !== 'failure'
        || !is_file($result['directory'] . '/coverage.xml') || !str_contains($summary, 'MSI: fixture value')
        || str_contains($log, 'fixture-secret') || !str_contains($log, '[REDACTED]')
    ) {
        throw new RuntimeException('Gate runner did not preserve the status or diagnostics safely.');
    }
    putenv('CI_DIAGNOSTICS_DIR=' . $temporary . '/success-diagnostics');
    $success = new GateRunner()->run('success', [PHP_BINARY, '-r', 'fwrite(STDOUT, "quality passed\n");'], $repository);
    $successMetadata = json_decode((string) file_get_contents($success['directory'] . '/metadata.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($success['exit_code'] !== 0 || $successMetadata['outcome'] !== 'success') {
        throw new RuntimeException('Gate runner did not record a successful outcome.');
    }
    putenv('CI_DIAGNOSTICS_DIR=' . $temporary . '/diagnostics');
    echo "CI checkout and gate diagnostics fixtures passed.\n";
} finally {
    removeCiFixture($temporary);
}

/**
 * @param list<string> $arguments
 */
function gitHelper(string $cwd, array $arguments): string
{
    $process = proc_open(['git', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start CI Git fixture.');
    }
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('CI Git fixture command failed: ' . $stderr);
    }

    return trim($stdout);
}

function removeCiFixture(string $directory): void
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}
