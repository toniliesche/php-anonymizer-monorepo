<?php

declare(strict_types=1);

use PhpAnonymizer\Release\ReleaseOrchestrator;
use PhpAnonymizer\Release\ReleasePlanner;

$root = dirname(__DIR__);

require_once __DIR__ . '/Release/ReleaseTrain.php';

require_once __DIR__ . '/Release/ReleaseReadiness.php';

require_once __DIR__ . '/Release/ReleasePlanner.php';

require_once __DIR__ . '/Release/ReleaseManifest.php';

require_once __DIR__ . '/Release/ReleaseSplitPlan.php';

require_once __DIR__ . '/Release/ReleaseSplitPlanner.php';

require_once __DIR__ . '/Release/ReleasePublicationPlan.php';

require_once __DIR__ . '/Release/GitPackagePublisher.php';

require_once __DIR__ . '/Release/GitHubReleaseClient.php';

require_once __DIR__ . '/Release/PackagistReleaseVerifier.php';

require_once __DIR__ . '/Release/ReleaseOrchestrator.php';

$branch = getenv('RELEASE_BRANCH') ?: getenv('GITHUB_HEAD_REF') ?: trim((string) shell_exec('git branch --show-current'));
$mainBranch = getenv('RELEASE_MAIN_BRANCH') ?: 'main';
$sourceSha = getenv('RELEASE_SOURCE_SHA') ?: getenv('GITHUB_SHA') ?: trim((string) shell_exec('git rev-parse HEAD'));
$version = getenv('RELEASE_VERSION') ?: ($argv[1] ?? '');
$execute = in_array('--execute', $argv, true) || getenv('RELEASE_EXECUTE') === '1';

try {
    if ($version === '') {
        $version = new ReleasePlanner()->plan($branch, $mainBranch, $root)['version'];
    }
    $orchestrator = new ReleaseOrchestrator();
    if (!$execute) {
        $actions = $orchestrator->dryRun($version, $branch, $mainBranch, $sourceSha, $root);
        printf("Release %s at %s (dry-run)\n", $version, $sourceSha);
        foreach ($actions as $action) {
            printf("- %s\n", $action);
        }

        exit(0);
    }
    $token = getenv('GITHUB_TOKEN') ?: getenv('GH_TOKEN') ?: '';
    $orchestrator->run($version, $branch, $mainBranch, $sourceSha, $root, $token);
    printf("Release %s completed.\n", $version);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");

    exit(2);
}
